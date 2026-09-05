<?php

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Enums\InventoryTransactionType;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LabDip;
use App\Models\Machine;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

/**
 * Demonstrable textile workflow for the DEMO company. Runs the real production
 * actions end to end (issue → costs → produce → QC) so every screen and report —
 * process orders, batches + traceability, lab dips, QC, and the production
 * reports — shows genuine, reconciled data. Idempotent: skips if process orders
 * already exist.
 *
 *   php artisan db:seed --class="Database\Seeders\TextileDemoSeeder"
 */
class TextileDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'DEMO')->first();
        if ($company === null) {
            $this->command->error('Demo company (DEMO) not found. Run the main seeder first.');

            return;
        }

        app(CompanyContext::class)->runFor($company, function () use ($company): void {
            if (ProcessOrder::query()->exists()) {
                $this->command->info('Textile demo already seeded — skipping.');

                return;
            }

            $wh = Warehouse::query()->where('code', 'MAIN')->first();
            $kg = Unit::query()->where('code', 'KG')->first() ?? Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

            // Products across the textile chain.
            $mk = fn (string $sku, string $name, float $cost, float $sell) => Product::updateOrCreate(
                ['sku' => $sku],
                ['unit_id' => $kg->getKey(), 'name' => $name, 'cost_price' => $cost, 'selling_price' => $sell, 'is_active' => true],
            );
            $yarn = $mk('RM-YARN', 'Cotton Yarn 30s', 250, 0);
            $dye = $mk('RM-DYE', 'Reactive Dye', 800, 0);
            $chem = $mk('RM-CHEM', 'Dyeing Chemical', 150, 0);
            $grey = $mk('SF-GREY', 'Grey Fabric (Single Jersey)', 0, 0);
            $dyed = $mk('SF-DYED', 'Dyed Fabric (Navy)', 0, 0);
            $finished = $mk('FG-FAB', 'Finished Fabric (Navy, Compacted)', 0, 620);

            // Stock the raw materials.
            app(ReceiveStock::class)->handle($wh, $yarn, '1000', '250', InventoryTransactionType::Opening, null);
            app(ReceiveStock::class)->handle($wh, $dye, '100', '800', InventoryTransactionType::Opening, null);
            app(ReceiveStock::class)->handle($wh, $chem, '200', '150', InventoryTransactionType::Opening, null);
            $yarnBatch = Batch::create(['product_id' => $yarn->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 1000, 'notes' => 'Opening yarn lot']);

            $type = fn (string $code) => ProcessType::query()->where('code', $code)->first();
            $machine = fn (string $code) => Machine::query()->where('code', $code)->first();

            $customer = Customer::query()->where('code', 'CUST-001')->first()
                ?? Customer::updateOrCreate(['code' => 'CUST-001'], ['name' => 'Acme Retail', 'is_active' => true]);

            $labDip = LabDip::create([
                'customer_id' => $customer->getKey(), 'colour' => 'Navy Blue', 'colour_ref' => 'Pantone 19-3832',
                'recipe' => 'Reactive Navy 3% + salt 40 g/L + soda ash 15 g/L', 'sample_ref' => 'SMP-NAVY-01',
                'request_date' => now()->subDays(14), 'status' => \App\Enums\LabDipStatus::CustomerApproved,
                'remarks' => 'Approved by customer for bulk.',
            ]);

            // 1) Knitting: yarn → grey fabric.
            $greyBatch = $this->runProcess(
                $type('KNIT'), $machine('KNIT-01'), $wh, $grey, 480,
                [[$yarn, 500, $yarnBatch]],
                ['labour' => 6000, 'machine_hours' => 12, 'utility' => 1500, 'overhead' => 1000],
                wastage: 20, inspected: 480, passed: 470, rejected: 10,
            );

            // 2) Dyeing: grey + dye + chemical → dyed fabric (uses the approved lab dip).
            $dyedBatch = $this->runProcess(
                $type('DYE'), $machine('DYE-01'), $wh, $dyed, 460,
                [[$grey, 470, $greyBatch], [$dye, 15, null], [$chem, 25, null]],
                ['labour' => 5000, 'machine_hours' => 8, 'utility' => 3000, 'overhead' => 1200],
                wastage: 10, inspected: 460, passed: 455, rejected: 5, labDip: $labDip,
            );

            // 3) Finishing (Compacting): dyed → finished fabric.
            $this->runProcess(
                $type('COMP') ?? $type('FINISH'), $machine('FIN-01'), $wh, $finished, 450,
                [[$dyed, 455, $dyedBatch]],
                ['labour' => 3000, 'machine_hours' => 6, 'utility' => 900, 'overhead' => 500],
                wastage: 5, inspected: 450, passed: 450, rejected: 0,
            );

            // Show the finished goods flowing to sales: a confirmed order for the fabric.
            if (SalesOrder::query()->where('so_number', 'SO-TEX-1')->doesntExist()) {
                $so = SalesOrder::create([
                    'so_number' => 'SO-TEX-1', 'customer_id' => $customer->getKey(),
                    'warehouse_id' => $wh->getKey(), 'order_date' => now()->toDateString(), 'status' => 'confirmed',
                ]);
                $so->lines()->create(['product_id' => $finished->getKey(), 'quantity_ordered' => 300, 'unit_price' => 620]);
            }

            $this->command->info('Textile demo seeded: knitting → dyeing → finishing (with lab dip, QC, batches, costing) + a finished-fabric sales order.');
        });
    }

    /**
     * Run one process order fully and return its output batch.
     *
     * @param  array<int, array{0: Product, 1: int, 2: Batch|null}>  $inputs
     * @param  array<string, int>  $costs
     */
    private function runProcess(
        ProcessType $type, ?Machine $machine, Warehouse $wh, Product $output, int $plannedQty,
        array $inputs, array $costs, int $wastage, int $inspected, int $passed, int $rejected, ?LabDip $labDip = null,
    ): Batch {
        $order = ProcessOrder::create([
            'process_type_id' => $type->getKey(), 'machine_id' => $machine?->getKey(),
            'warehouse_id' => $wh->getKey(), 'output_product_id' => $output->getKey(),
            'lab_dip_id' => $labDip?->getKey(), 'planned_quantity' => $plannedQty, 'status' => 'planned',
        ]);
        foreach ($inputs as [$product, $qty, $batch]) {
            $order->inputs()->create(['product_id' => $product->getKey(), 'planned_quantity' => $qty, 'batch_id' => $batch?->getKey()]);
        }

        app(IssueProcessMaterials::class)->handle($order);
        app(RecordProcessCosts::class)->handle($order->refresh(), $costs);
        app(RecordProcessProduction::class)->handle($order->refresh(), (string) $plannedQty, (string) $wastage);
        app(RecordQualityInspection::class)->handle($order->refresh(), (string) $inspected, (string) $passed, (string) $rejected, $rejected > 0 ? 'Minor defects' : null, 'QC done');

        return $order->refresh()->outputBatch;
    }
}
