<?php

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\GenerateProductionPlanFromSalesOrder;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Actions\Process\RecordSubcontractCharge;
use App\Enums\InventoryTransactionType;
use App\Enums\ProcessMode;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DyeingSpecification;
use App\Models\LabDip;
use App\Models\Machine;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\SalesOrder;
use App\Models\Supplier;
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
            $wh = Warehouse::query()->where('code', 'MAIN')->first();
            $kg = Unit::query()->where('code', 'KG')->first() ?? Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

            // Products across the textile chain (idempotent).
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

            // Flag the textile products so the Textile dashboard KPIs classify stock by stage.
            $yarn->update(['is_textile' => true, 'textile_type' => 'yarn']);
            $grey->update(['is_textile' => true, 'textile_type' => 'grey_fabric', 'is_roll_tracked' => true]);
            $dyed->update(['is_textile' => true, 'textile_type' => 'dyed_fabric']);
            $finished->update(['is_textile' => true, 'textile_type' => 'finished_fabric', 'is_roll_tracked' => true]);

            $type = fn (string $code) => ProcessType::query()->where('code', $code)->first();
            $machine = fn (string $code) => Machine::query()->where('code', $code)->first();

            $customer = Customer::query()->where('code', 'CUST-001')->first()
                ?? Customer::updateOrCreate(['code' => 'CUST-001'], ['name' => 'Acme Retail', 'is_active' => true]);

            // Fabric specification master for the grey fabric — auto-fills knitting orders.
            $greySpec = ProductSpecification::updateOrCreate(
                ['product_id' => $grey->getKey()],
                [
                    'fabric_composition' => '80% Cotton 20% Polyester', 'gsm' => '280/290',
                    'fabric_width' => '72 Inch Open', 'colour' => 'Grey',
                    'yarn_count' => '30s', 'fabric_type' => 'Single Jersey', 'quality' => 'Combed',
                    'machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '4.1+5.0+0.5',
                ],
            );
            // Consumption: 1.0 kg yarn per kg grey fabric + 5% knitting loss.
            $greySpec->consumptions()->updateOrCreate(
                ['product_id' => $yarn->getKey()],
                ['basis' => 'per_unit', 'rate' => 1.0, 'wastage_percent' => 5],
            );

            // --- In-house chain (knitting → dyeing → finishing). Seeded once. ---
            if (! ProcessOrder::query()->where('mode', ProcessMode::InHouse->value)->exists()) {
                app(ReceiveStock::class)->handle($wh, $yarn, '1000', '250', InventoryTransactionType::Opening, null);
                app(ReceiveStock::class)->handle($wh, $dye, '100', '800', InventoryTransactionType::Opening, null);
                app(ReceiveStock::class)->handle($wh, $chem, '200', '150', InventoryTransactionType::Opening, null);
                $yarnBatch = Batch::create(['product_id' => $yarn->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 1000, 'notes' => 'Opening yarn lot']);

                $labDip = LabDip::create([
                    'customer_id' => $customer->getKey(), 'colour' => 'Navy Blue', 'colour_ref' => 'Pantone 19-3832',
                    'dyeing_process' => 'reactive', 'substrate' => 'Single Jersey Cotton', 'gsm' => '160',
                    'liquor_ratio' => '1:8', 'temperature' => 60, 'dyeing_time' => 60, 'ph' => 11, 'shade_percentage' => 3,
                    'fastness_wash' => '4-5', 'fastness_rubbing' => '4', 'fastness_light' => '4',
                    'recipe' => 'Reactive Navy 3% + salt 40 g/L + soda ash 15 g/L', 'sample_ref' => 'SMP-NAVY-01',
                    'request_date' => now()->subDays(14), 'status' => \App\Enums\LabDipStatus::CustomerApproved,
                    'remarks' => 'Approved by customer for bulk.',
                ]);

                // 1) Knitting: yarn → grey fabric (with a real knitting spec sheet).
                $greyBatch = $this->runProcess(
                    $type('KNIT'), $machine('KNIT-01'), $wh, $grey, 480,
                    [[$yarn, 500, $yarnBatch]],
                    ['labour' => 6000, 'machine_hours' => 12, 'utility' => 1500, 'overhead' => 1000],
                    wastage: 20, inspected: 480, passed: 470, rejected: 10,
                    attributes: [
                        'fabric_composition' => '80% Cotton 20% Polyester',
                        'gsm' => '160', 'fabric_width' => '72 Inch Open', 'colour' => 'Grey',
                        'specifications' => [
                            'machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '4.1+5.0+0.5',
                            'yarn_count' => '30s', 'fabric_type' => 'Single Jersey', 'quality' => 'Combed',
                        ],
                    ],
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
            }

            // --- Knitting SUB-CONTRACT (job-work). Seeded once. ---
            if (! ProcessOrder::query()->where('mode', ProcessMode::Subcontract->value)->exists()) {
            // Send yarn to an outside knitter for a service charge per KG; grey fabric
            // returns carrying yarn + knitting cost.
            $subYarn = $mk('RM-YARN-SC', 'Cotton Yarn 30s (sub-contract)', 250, 0);
            app(ReceiveStock::class)->handle($wh, $subYarn, '2000', '250', InventoryTransactionType::Opening, null);
            $subYarnBatch = Batch::create(['product_id' => $subYarn->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 2000, 'notes' => 'Yarn for sub-contract knitting']);

            $subcontractor = Supplier::query()->where('code', 'SUP-KNIT')->first()
                ?? Supplier::updateOrCreate(['code' => 'SUP-KNIT'], ['name' => 'Acme Knitting Mills', 'is_active' => true]);
            $knitService = Product::updateOrCreate(
                ['sku' => 'NS-KNIT'],
                ['unit_id' => $kg->getKey(), 'name' => 'Service Charge for Knitting', 'cost_price' => 0, 'selling_price' => 0, 'is_service' => true, 'is_active' => true],
            );

            $this->runSubcontract(
                $type('KNIT'), $wh, $subcontractor, $knitService, $grey, $subYarn, $subYarnBatch,
                plannedQty: 1960, rate: 25.0, billQty: 2000, wastage: 40,
                attributes: [
                    'fabric_composition' => '80% Cotton 20% Polyester', 'gsm' => '280/290',
                    'fabric_width' => '72 Inch Open', 'colour' => 'Grey',
                    'specifications' => [
                        'machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '4.1+5.0+0.5',
                        'yarn_count' => '30s', 'fabric_type' => 'Single Jersey', 'quality' => 'Combed',
                    ],
                ],
            );
            }

            // Show the finished goods flowing to sales: a confirmed order for the fabric.
            $so = SalesOrder::query()->where('so_number', 'SO-TEX-1')->first();
            if ($so === null) {
                $so = SalesOrder::create([
                    'so_number' => 'SO-TEX-1', 'customer_id' => $customer->getKey(),
                    'warehouse_id' => $wh->getKey(), 'order_date' => now()->toDateString(),
                    'delivery_date' => now()->addDays(30)->toDateString(), 'status' => 'confirmed',
                ]);
                $so->lines()->create(['product_id' => $finished->getKey(), 'quantity_ordered' => 300, 'unit_price' => 620]);
            }

            // Dyeing recipe on the navy lab dip: reactive dye 3% owf, salt 40 g/L, soda 15 g/L.
            $navy = LabDip::query()->where('colour', 'Navy Blue')->first();
            if ($navy !== null) {
                // Ensure the dye-house params exist (liquor ratio drives the g/L calc).
                $navy->forceFill(array_filter([
                    'dyeing_process' => $navy->dyeing_process ?: 'reactive',
                    'substrate' => $navy->substrate ?: 'Single Jersey Cotton',
                    'gsm' => $navy->gsm ?: '160',
                    'liquor_ratio' => $navy->liquor_ratio ?: '1:8',
                    'temperature' => $navy->temperature ?: 60,
                    'shade_percentage' => $navy->shade_percentage ?: 3,
                ]))->save();

                $salt = $mk('RM-SALT', 'Salt (Glauber)', 15, 0);
                $soda = $mk('RM-SODA', 'Soda Ash', 60, 0);
                foreach ([[$dye, 'percent_owf', 3], [$salt, 'g_per_litre', 40], [$soda, 'g_per_litre', 15]] as [$p, $basis, $rate]) {
                    $navy->consumptions()->updateOrCreate(
                        ['product_id' => $p->getKey()],
                        ['basis' => $basis, 'rate' => $rate, 'wastage_percent' => 0],
                    );
                }
            }

            // Dyeing specification master for the navy dyed fabric (standard dyeing program).
            $dyeingSpec = DyeingSpecification::updateOrCreate(
                ['product_id' => $dyed->getKey()],
                [
                    'dyeing_process' => 'reactive', 'substrate' => 'Single Jersey Cotton', 'gsm' => '160',
                    'colour' => 'Navy', 'colour_ref' => 'Pantone 19-3832', 'liquor_ratio' => '1:8',
                    'temperature' => 60, 'dyeing_time' => 60, 'ph' => 11, 'shade_percentage' => 3,
                    'fastness_wash' => '4-5', 'fastness_rubbing' => '4', 'fastness_light' => '4',
                ],
            );
            $salt = $mk('RM-SALT', 'Salt (Glauber)', 15, 0);
            $soda = $mk('RM-SODA', 'Soda Ash', 60, 0);
            foreach ([[$dye, 'percent_owf', 3], [$salt, 'g_per_litre', 40], [$soda, 'g_per_litre', 15]] as [$p, $basis, $rate]) {
                $dyeingSpec->consumptions()->updateOrCreate(
                    ['product_id' => $p->getKey()],
                    ['basis' => $basis, 'rate' => $rate, 'wastage_percent' => 0],
                );
            }

            // Master production plan (Time & Action) derived from the customer order.
            app(GenerateProductionPlanFromSalesOrder::class)->handle($so->refresh()->load('lines'));

            $this->command->info('Textile demo seeded: in-house knitting → dyeing → finishing, a knitting sub-contract (job-work), a finished-fabric sales order and a production plan.');
        });
    }

    /**
     * Run one process order fully and return its output batch.
     *
     * @param  array<int, array{0: Product, 1: int, 2: Batch|null}>  $inputs
     * @param  array<string, int>  $costs
     * @param  array<string, mixed>  $attributes
     */
    private function runProcess(
        ProcessType $type, ?Machine $machine, Warehouse $wh, Product $output, int $plannedQty,
        array $inputs, array $costs, int $wastage, int $inspected, int $passed, int $rejected,
        ?LabDip $labDip = null, array $attributes = [],
    ): Batch {
        $order = ProcessOrder::create(array_merge([
            'process_type_id' => $type->getKey(), 'machine_id' => $machine?->getKey(),
            'warehouse_id' => $wh->getKey(), 'output_product_id' => $output->getKey(),
            'lab_dip_id' => $labDip?->getKey(), 'planned_quantity' => $plannedQty, 'status' => 'planned',
        ], $attributes));
        foreach ($inputs as [$product, $qty, $batch]) {
            $order->inputs()->create(['product_id' => $product->getKey(), 'planned_quantity' => $qty, 'batch_id' => $batch?->getKey()]);
        }

        app(IssueProcessMaterials::class)->handle($order);
        app(RecordProcessCosts::class)->handle($order->refresh(), $costs);
        app(RecordProcessProduction::class)->handle($order->refresh(), (string) $plannedQty, (string) $wastage);
        app(RecordQualityInspection::class)->handle($order->refresh(), (string) $inspected, (string) $passed, (string) $rejected, $rejected > 0 ? 'Minor defects' : null, 'QC done');

        return $order->refresh()->outputBatch;
    }

    /**
     * Run one knitting sub-contract (job-work) order fully: issue yarn → record the
     * knitting service charge (payable to the sub-contractor) → receive grey fabric → QC.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function runSubcontract(
        ProcessType $type, Warehouse $wh, Supplier $sub, Product $serviceItem, Product $output,
        Product $yarn, ?Batch $yarnBatch, int $plannedQty, float $rate, int $billQty, int $wastage,
        array $attributes = [],
    ): ProcessOrder {
        $order = ProcessOrder::create(array_merge([
            'process_type_id' => $type->getKey(), 'mode' => ProcessMode::Subcontract,
            'warehouse_id' => $wh->getKey(), 'output_product_id' => $output->getKey(),
            'subcontractor_id' => $sub->getKey(), 'service_item_id' => $serviceItem->getKey(),
            'service_rate' => $rate, 'bill_quantity' => $billQty, 'service_currency' => config('erp.currency.code'),
            'planned_quantity' => $plannedQty, 'status' => 'planned',
        ], $attributes));
        $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => $billQty, 'batch_id' => $yarnBatch?->getKey()]);

        app(IssueProcessMaterials::class)->handle($order);
        app(RecordSubcontractCharge::class)->handle($order->refresh());
        app(RecordProcessProduction::class)->handle($order->refresh(), (string) $plannedQty, (string) $wastage);
        // KNIT requires QC — pass the received quantity.
        app(RecordQualityInspection::class)->handle($order->refresh(), (string) $plannedQty, (string) $plannedQty, '0', null, 'QC done');

        return $order->refresh();
    }
}
