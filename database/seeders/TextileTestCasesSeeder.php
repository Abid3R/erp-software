<?php

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\CreateReworkOrder;
use App\Actions\Process\GenerateRolls;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Actions\Process\RecordSubcontractCharge;
use App\Enums\InventoryTransactionType;
use App\Enums\LabDipStatus;
use App\Enums\ProcessMode;
use App\Enums\ProcessOrderStatus;
use App\Enums\ProductionPlanStatus;
use App\Enums\ProductionStageStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DyeingSpecification;
use App\Models\FabricRoll;
use App\Models\LabDip;
use App\Models\Machine;
use App\Models\ProcessOrder;
use App\Models\ProcessRoute;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

/**
 * Hand-testing data for the Textile module: ~10 records in each module spread
 * across their lifecycle stages, so every screen and every stage-specific action
 * can be exercised by clicking through the DEMO company. All records are prefixed
 * "TC" / noted "TESTCASE" and each section is idempotent (skips if already seeded).
 *
 *   php artisan db:seed --class="Database\Seeders\TextileTestCasesSeeder"
 */
class TextileTestCasesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'DEMO')->first();
        if ($company === null) {
            $this->command->error('Demo company (DEMO) not found. Run the main seeder first.');

            return;
        }

        app(CompanyContext::class)->runFor($company, function () use ($company): void {
            $wh = Warehouse::query()->where('code', 'MAIN')->first()
                ?? Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
            $kg = Unit::query()->where('code', 'KG')->first() ?? Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

            $knit = ProcessType::query()->where('code', 'KNIT')->first();
            if ($knit === null) {
                $this->command->error('KNIT process type missing — run TextileDemoSeeder first.');

                return;
            }

            $this->fabricSpecs($kg);
            $this->dyeingSpecs($kg);
            $this->labDips();
            $this->processOrders($wh, $kg, $knit);
            $this->subcontracts($wh, $kg, $knit);
            $this->productionPlans($knit);
            $this->processRoutes($knit);
            $this->approveDyeingSpecs();
            $this->rollsAndRework($wh, $kg, $knit);

            $this->command->info('Textile test cases ready — ~10 per module across stages, plus routes, rolls, rework & recipe approvals (all prefixed TC / noted TESTCASE).');
        });
    }

    /** 10 fabric specifications, each with a yarn consumption recipe. */
    private function fabricSpecs(Unit $kg): void
    {
        if (Product::query()->where('sku', 'TC-FAB-01')->exists()) {
            $this->command->info('  Fabric specs already seeded — skipping.');

            return;
        }

        $types = ['Single Jersey', 'Pique', 'Interlock', 'Rib 1x1', 'Fleece', 'Terry', 'Lacoste', 'Waffle', 'Flat Back Rib', 'Double Jersey'];
        for ($i = 1; $i <= 10; $i++) {
            $sku = sprintf('TC-FAB-%02d', $i);
            $product = Product::updateOrCreate(
                ['sku' => $sku],
                ['unit_id' => $kg->getKey(), 'name' => 'TC Fabric '.$i.' ('.$types[$i - 1].')', 'cost_price' => 0, 'selling_price' => 0, 'is_active' => true],
            );
            $spec = ProductSpecification::updateOrCreate(
                ['product_id' => $product->getKey()],
                [
                    'fabric_composition' => $i % 2 ? '100% Cotton' : '95% Cotton 5% Elastane',
                    'gsm' => (string) (140 + $i * 10),
                    'fabric_width' => (60 + $i).' Inch '.($i % 2 ? 'Open' : 'Tube'),
                    'yarn_count' => (24 + $i).'s',
                    'fabric_type' => $types[$i - 1],
                    'quality' => $i % 2 ? 'Combed' : 'Carded',
                    'machine_diameter' => (string) (26 + $i),
                    'gauge' => (string) (20 + ($i % 5) * 2),
                    'stitch_length' => number_format(2.6 + $i / 10, 1),
                ],
            );
            $yarn = Product::updateOrCreate(
                ['sku' => 'TC-YARN'],
                ['unit_id' => $kg->getKey(), 'name' => 'TC Cotton Yarn', 'cost_price' => 240, 'selling_price' => 0, 'is_active' => true],
            );
            $spec->consumptions()->updateOrCreate(
                ['product_id' => $yarn->getKey()],
                ['basis' => 'per_unit', 'rate' => 1.0, 'wastage_percent' => 3 + ($i % 4)],
            );
        }
        $this->command->info('  Fabric specs: 10 created.');
    }

    /** 10 dyeing specifications, each with a dye + chemical recipe. */
    private function dyeingSpecs(Unit $kg): void
    {
        if (Product::query()->where('sku', 'TC-DYED-01')->exists()) {
            $this->command->info('  Dyeing specs already seeded — skipping.');

            return;
        }

        $dye = Product::updateOrCreate(['sku' => 'TC-DYE'], ['unit_id' => $kg->getKey(), 'name' => 'TC Reactive Dye', 'cost_price' => 800, 'selling_price' => 0, 'is_active' => true]);
        $salt = Product::updateOrCreate(['sku' => 'TC-SALT'], ['unit_id' => $kg->getKey(), 'name' => 'TC Salt (Glauber)', 'cost_price' => 15, 'selling_price' => 0, 'is_active' => true]);
        $soda = Product::updateOrCreate(['sku' => 'TC-SODA'], ['unit_id' => $kg->getKey(), 'name' => 'TC Soda Ash', 'cost_price' => 60, 'selling_price' => 0, 'is_active' => true]);

        $processes = ['reactive', 'disperse', 'reactive', 'pigment', 'reactive', 'vat', 'reactive', 'disperse', 'reactive', 'direct'];
        $colours = ['Navy', 'Red', 'Black', 'Olive', 'Maroon', 'Royal Blue', 'Bottle Green', 'Mustard', 'Teal', 'Purple'];
        for ($i = 1; $i <= 10; $i++) {
            $product = Product::updateOrCreate(
                ['sku' => sprintf('TC-DYED-%02d', $i)],
                ['unit_id' => $kg->getKey(), 'name' => 'TC Dyed Fabric '.$i.' ('.$colours[$i - 1].')', 'cost_price' => 0, 'selling_price' => 0, 'is_active' => true],
            );
            $spec = DyeingSpecification::updateOrCreate(
                ['product_id' => $product->getKey()],
                [
                    'dyeing_process' => $processes[$i - 1], 'substrate' => 'Single Jersey Cotton',
                    'gsm' => (string) (150 + $i * 5), 'colour' => $colours[$i - 1], 'colour_ref' => 'Pantone TC-'.(200 + $i),
                    'liquor_ratio' => '1:'.(6 + ($i % 4)), 'temperature' => 60, 'dyeing_time' => 45 + $i,
                    'ph' => 10.5, 'shade_percentage' => 1 + $i * 0.5,
                    'fastness_wash' => '4-5', 'fastness_rubbing' => '4', 'fastness_light' => '4',
                    'notes' => 'TESTCASE dyeing spec #'.$i,
                ],
            );
            $spec->consumptions()->updateOrCreate(['product_id' => $dye->getKey()], ['basis' => 'percent_owf', 'rate' => 1 + $i * 0.5, 'wastage_percent' => 0]);
            $spec->consumptions()->updateOrCreate(['product_id' => $salt->getKey()], ['basis' => 'g_per_litre', 'rate' => 40, 'wastage_percent' => 0]);
            $spec->consumptions()->updateOrCreate(['product_id' => $soda->getKey()], ['basis' => 'g_per_litre', 'rate' => 15, 'wastage_percent' => 0]);
        }
        $this->command->info('  Dyeing specs: 10 created.');
    }

    /** 10 lab dips spread across all statuses. */
    private function labDips(): void
    {
        if (LabDip::query()->where('sample_ref', 'like', 'TC-%')->exists()) {
            $this->command->info('  Lab dips already seeded — skipping.');

            return;
        }

        $customer = Customer::query()->first();
        $statuses = [
            LabDipStatus::Draft, LabDipStatus::Submitted, LabDipStatus::InLab, LabDipStatus::InternalApproved,
            LabDipStatus::SentToCustomer, LabDipStatus::CustomerApproved, LabDipStatus::Rejected,
            LabDipStatus::CustomerApproved, LabDipStatus::InternalApproved, LabDipStatus::InLab,
        ];
        $colours = ['Navy', 'Red', 'Black', 'Olive', 'Maroon', 'Royal Blue', 'Grey Melange', 'Mustard', 'Teal', 'Pink'];
        foreach ($statuses as $i => $status) {
            LabDip::create([
                'customer_id' => $customer?->getKey(),
                'colour' => $colours[$i], 'colour_ref' => 'Pantone TC-'.(100 + $i),
                'dyeing_process' => $i % 2 ? 'reactive' : 'disperse',
                'substrate' => 'Single Jersey', 'gsm' => (string) (150 + $i * 5),
                'liquor_ratio' => '1:'.(6 + ($i % 4)), 'temperature' => 60, 'dyeing_time' => 45 + $i,
                'ph' => 10.5, 'shade_percentage' => 1 + $i * 0.5,
                'fastness_wash' => '4-5', 'fastness_rubbing' => '4', 'fastness_light' => '4',
                'recipe' => 'Reactive '.$colours[$i].' '.(1 + $i * 0.5).'% + salt + soda',
                'sample_ref' => sprintf('TC-LD-%02d', $i + 1),
                'request_date' => now()->subDays(20 - $i), 'status' => $status,
                'remarks' => 'TESTCASE lab dip #'.($i + 1),
            ]);
        }
        $this->command->info('  Lab dips: 10 created across statuses.');
    }

    /** 10 in-house knitting process orders across draft/planned/in-progress/qc/completed/cancelled. */
    private function processOrders(Warehouse $wh, Unit $kg, ProcessType $knit): void
    {
        if (ProcessOrder::query()->where('notes', 'like', 'TESTCASE PO%')->exists()) {
            $this->command->info('  Process orders already seeded — skipping.');

            return;
        }

        [$yarn, $grey, $machine] = $this->knitFixtures($wh, $kg);

        // target stage => how many.
        $plan = ['draft' => 2, 'planned' => 2, 'in_progress' => 2, 'qc' => 2, 'completed' => 1, 'cancelled' => 1];
        $n = 0;
        foreach ($plan as $target => $count) {
            for ($j = 0; $j < $count; $j++) {
                $n++;
                $qty = 100 + $n * 5;
                $order = ProcessOrder::create([
                    'process_type_id' => $knit->getKey(), 'mode' => ProcessMode::InHouse,
                    'machine_id' => $machine->getKey(), 'warehouse_id' => $wh->getKey(),
                    'output_product_id' => $grey->getKey(), 'planned_quantity' => $qty, 'status' => 'planned',
                    'fabric_composition' => '100% Cotton', 'gsm' => '160', 'colour' => 'Grey',
                    'specifications' => ['machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '2.8'],
                    'notes' => 'TESTCASE PO #'.$n.' ('.$target.')',
                ]);
                $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => $qty]);
                $this->advanceKnit($order, $target, $qty);
            }
        }
        $this->command->info('  Process orders: 10 created across stages.');
    }

    /** 10 knitting sub-contracts across draft/planned/in-progress/charged/qc/completed/cancelled. */
    private function subcontracts(Warehouse $wh, Unit $kg, ProcessType $knit): void
    {
        if (ProcessOrder::query()->where('notes', 'like', 'TESTCASE SC%')->exists()) {
            $this->command->info('  Sub-contracts already seeded — skipping.');

            return;
        }

        [$yarn, $grey] = $this->knitFixtures($wh, $kg);
        $sub = Supplier::query()->where('code', 'TC-SUB')->first()
            ?? Supplier::updateOrCreate(['code' => 'TC-SUB'], ['name' => 'TC Knitting House', 'is_active' => true]);
        $svc = Product::updateOrCreate(
            ['sku' => 'TC-SVC'],
            ['unit_id' => $kg->getKey(), 'name' => 'TC Knitting Service', 'cost_price' => 0, 'selling_price' => 0, 'is_service' => true, 'is_active' => true],
        );

        $plan = ['draft' => 2, 'planned' => 1, 'in_progress' => 2, 'charged' => 1, 'qc' => 2, 'completed' => 1, 'cancelled' => 1];
        $n = 0;
        foreach ($plan as $target => $count) {
            for ($j = 0; $j < $count; $j++) {
                $n++;
                $qty = 200 + $n * 10;
                $order = ProcessOrder::create([
                    'process_type_id' => $knit->getKey(), 'mode' => ProcessMode::Subcontract,
                    'warehouse_id' => $wh->getKey(), 'output_product_id' => $grey->getKey(),
                    'subcontractor_id' => $sub->getKey(), 'service_item_id' => $svc->getKey(),
                    'service_rate' => 24 + $n, 'bill_quantity' => $qty, 'service_currency' => config('erp.currency.code'),
                    'planned_quantity' => $qty, 'status' => 'planned',
                    'fabric_composition' => '80% Cotton 20% PE', 'gsm' => '280/290', 'colour' => 'Grey',
                    'notes' => 'TESTCASE SC #'.$n.' ('.$target.')',
                ]);
                $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => $qty]);
                $this->advanceSub($order, $target, $qty);
            }
        }
        $this->command->info('  Sub-contracts: 10 created across stages.');
    }

    /** 10 production plans across statuses, with rich order/fabric specification + stages. */
    private function productionPlans(ProcessType $knit): void
    {
        if (ProductionPlan::query()->where('notes', 'like', 'TESTCASE PLAN%')->exists()) {
            $this->command->info('  Production plans already seeded — skipping.');

            return;
        }

        $dye = ProcessType::query()->where('code', 'DYE')->first();
        $fin = ProcessType::query()->where('code', 'COMP')->first() ?? ProcessType::query()->where('category', 'finishing')->first();
        $customer = Customer::query()->first();

        $statuses = [
            ProductionPlanStatus::Draft, ProductionPlanStatus::Scheduled, ProductionPlanStatus::InProgress,
            ProductionPlanStatus::OnHold, ProductionPlanStatus::Completed, ProductionPlanStatus::Cancelled,
            ProductionPlanStatus::Scheduled, ProductionPlanStatus::InProgress, ProductionPlanStatus::Draft,
            ProductionPlanStatus::Completed,
        ];
        $buyers = ['H&M', 'Zara', 'Primark', 'C&A', 'Tesco', 'Walmart', 'Lidl', 'Uniqlo', 'Next', 'Kiabi'];
        for ($i = 0; $i < 10; $i++) {
            $qty = 500 + $i * 100;
            $plan = ProductionPlan::create([
                'customer_id' => $customer?->getKey(),
                'buyer' => $buyers[$i], 'style_no' => 'ST-'.(1000 + $i), 'po_no' => 'PO-'.(5000 + $i),
                'colour' => ['Navy', 'Red', 'Black', 'White', 'Olive', 'Grey', 'Blue', 'Green', 'Beige', 'Pink'][$i],
                'fabric_composition' => $i % 2 ? '100% Cotton' : '95% Cotton 5% Elastane',
                'gsm' => (string) (150 + $i * 10), 'fabric_width' => (60 + $i).' Inch Open',
                'fabric_type' => 'Single Jersey',
                'planned_quantity' => $qty, 'order_quantity' => $qty * 4, 'order_unit' => 'PCS', 'unit' => 'KG',
                'plan_date' => now()->subDays(5)->toDateString(),
                'booking_date' => now()->subDays(10)->toDateString(),
                'start_date' => now()->addDays($i)->toDateString(),
                'due_date' => now()->addDays(30 + $i)->toDateString(),
                'shipment_date' => now()->addDays(40 + $i)->toDateString(),
                'status' => $statuses[$i], 'notes' => 'TESTCASE PLAN #'.($i + 1),
            ]);
            $seq = 1;
            foreach (array_filter([$knit, $dye, $fin]) as $type) {
                $plan->stages()->create([
                    'process_type_id' => $type->getKey(), 'sequence' => $seq,
                    'planned_quantity' => $qty, 'status' => ProductionStageStatus::Pending,
                    'planned_start' => now()->addDays($i + $seq * 3)->toDateString(),
                    'planned_end' => now()->addDays($i + $seq * 3 + 2)->toDateString(),
                ]);
                $seq++;
            }
        }
        $this->command->info('  Production plans: 10 created across statuses (with specs + stages).');
    }

    /** A few configurable process routes on TC fabric products. */
    private function processRoutes(ProcessType $knit): void
    {
        if (ProcessRoute::query()->where('name', 'like', 'TC %')->exists()) {
            $this->command->info('  Process routes already seeded — skipping.');

            return;
        }

        $dye = ProcessType::query()->where('code', 'DYE')->first();
        $comp = ProcessType::query()->where('code', 'COMP')->first() ?? ProcessType::query()->where('category', 'finishing')->first();

        $routes = [
            ['TC Route — Knit → Dye → Finish', 'TC-FAB-01', array_filter([$knit, $dye, $comp])],
            ['TC Route — Knit → Finish', 'TC-FAB-02', array_filter([$knit, $comp])],
            ['TC Route — Dye → Compact', 'TC-FAB-03', array_filter([$dye, $comp])],
        ];
        foreach ($routes as [$name, $sku, $steps]) {
            $product = Product::query()->where('sku', $sku)->first();
            $route = ProcessRoute::create(['name' => $name, 'product_id' => $product?->getKey(), 'is_active' => true, 'notes' => 'TESTCASE route']);
            $seq = 1;
            foreach ($steps as $type) {
                $route->steps()->create(['process_type_id' => $type->getKey(), 'sequence' => $seq++]);
            }
        }
        $this->command->info('  Process routes: 3 created.');
    }

    /** Approve half the TC dyeing specs and give them recipe numbers/versions. */
    private function approveDyeingSpecs(): void
    {
        $specs = DyeingSpecification::query()->where('notes', 'like', 'TESTCASE%')->get();
        if ($specs->isEmpty() || $specs->whereNotNull('approved_at')->isNotEmpty()) {
            $this->command->info('  Dyeing-spec approvals already set — skipping.');

            return;
        }
        foreach ($specs as $i => $spec) {
            $spec->update([
                'recipe_number' => 'DR-'.(100 + $i),
                'version' => 1 + ($i % 3),
                'approval_status' => $i % 2 === 0 ? 'approved' : 'draft',
                'approved_at' => $i % 2 === 0 ? now() : null,
            ]);
        }
        $this->command->info('  Dyeing-spec recipe numbers/versions + approvals set.');
    }

    /** Generate rolls on completed roll-tracked runs, and a couple of QC-reject → rework cases. */
    private function rollsAndRework(Warehouse $wh, Unit $kg, ProcessType $knit): void
    {
        // Ensures TC-GREY is flagged roll-tracked before we generate rolls against it.
        [$yarn, $grey] = $this->knitFixtures($wh, $kg);

        // Rolls on completed roll-tracked process orders.
        if (! FabricRoll::query()->exists()) {
            ProcessOrder::query()->where('notes', 'like', 'TESTCASE PO%')->where('status', 'completed')
                ->whereNotNull('output_batch_id')->with('outputProduct')->get()
                ->each(function (ProcessOrder $o): void {
                    if ($o->outputProduct?->is_roll_tracked && (float) $o->produced_quantity > 0) {
                        app(GenerateRolls::class)->handle($o, (string) $o->produced_quantity, 3);
                    }
                });
            $this->command->info('  Fabric rolls generated on completed runs.');
        } else {
            $this->command->info('  Fabric rolls already seeded — skipping.');
        }

        // QC-reject → rework cases.
        if (ProcessOrder::query()->where('notes', 'like', 'TESTCASE RW%')->exists()) {
            $this->command->info('  Rework cases already seeded — skipping.');

            return;
        }
        for ($i = 1; $i <= 2; $i++) {
            $qty = 100 + $i * 10;
            $order = ProcessOrder::create([
                'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(),
                'output_product_id' => $grey->getKey(), 'planned_quantity' => $qty, 'status' => 'planned',
                'colour' => 'Grey', 'notes' => 'TESTCASE RW source #'.$i,
            ]);
            $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => $qty]);
            app(IssueProcessMaterials::class)->handle($order);
            app(RecordProcessProduction::class)->handle($order->refresh(), (string) $qty);
            // QC with rejects so the run can be reworked.
            app(RecordQualityInspection::class)->handle($order->refresh(), (string) $qty, (string) ($qty - 15), '15', 'Shade variation', 'TESTCASE QC reject');
            app(CreateReworkOrder::class)->handle($order->refresh(), '15', 'TESTCASE RW rework #'.$i);
        }
        $this->command->info('  Rework cases: 2 QC-reject runs + their reworks created.');
    }

    // ---- helpers -------------------------------------------------------------

    /** @return array{0: Product, 1: Product, 2: Machine} */
    private function knitFixtures(Warehouse $wh, Unit $kg): array
    {
        $yarn = Product::updateOrCreate(
            ['sku' => 'TC-YARN'],
            ['unit_id' => $kg->getKey(), 'name' => 'TC Cotton Yarn', 'cost_price' => 240, 'selling_price' => 0, 'is_active' => true],
        );
        // Ensure plenty of stock for the completed/in-progress runs (idempotent top-up once).
        if (! ProcessOrder::query()->where('notes', 'like', 'TESTCASE%')->exists()) {
            app(ReceiveStock::class)->handle($wh, $yarn, '500000', '240', InventoryTransactionType::Opening, null);
        }
        $grey = Product::updateOrCreate(
            ['sku' => 'TC-GREY'],
            ['unit_id' => $kg->getKey(), 'name' => 'TC Grey Fabric', 'cost_price' => 0, 'selling_price' => 0, 'is_active' => true,
                'is_textile' => true, 'textile_type' => 'grey_fabric', 'is_roll_tracked' => true, 'gsm' => '160', 'width' => '72 Inch Open'],
        );
        $machine = Machine::query()->where('code', 'KNIT-01')->first()
            ?? Machine::updateOrCreate(['code' => 'TC-KNIT'], ['name' => 'TC Knitting Machine', 'type' => 'knitting', 'hourly_cost' => 120, 'is_active' => true]);

        return [$yarn, $grey, $machine];
    }

    private function advanceKnit(ProcessOrder $order, string $target, int $qty): void
    {
        if ($target === 'draft') {
            $order->update(['status' => ProcessOrderStatus::Draft]);

            return;
        }
        if ($target === 'cancelled') {
            $order->update(['status' => ProcessOrderStatus::Cancelled]);

            return;
        }
        if ($target === 'planned') {
            return;
        }

        app(IssueProcessMaterials::class)->handle($order);                       // → in_progress
        if ($target === 'in_progress') {
            return;
        }

        app(RecordProcessCosts::class)->handle($order->refresh(), ['labour' => 500, 'machine_hours' => 4]);
        app(RecordProcessProduction::class)->handle($order->refresh(), (string) $qty); // KNIT requires QC → qc
        if ($target === 'qc') {
            return;
        }

        // completed
        app(RecordQualityInspection::class)->handle($order->refresh(), (string) $qty, (string) $qty, '0', null, 'TESTCASE QC');
    }

    private function advanceSub(ProcessOrder $order, string $target, int $qty): void
    {
        if ($target === 'draft') {
            $order->update(['status' => ProcessOrderStatus::Draft]);

            return;
        }
        if ($target === 'cancelled') {
            $order->update(['status' => ProcessOrderStatus::Cancelled]);

            return;
        }
        if ($target === 'planned') {
            return;
        }

        app(IssueProcessMaterials::class)->handle($order);                       // → in_progress
        if ($target === 'in_progress') {
            return;
        }

        app(RecordSubcontractCharge::class)->handle($order->refresh());          // records the knitting charge
        if ($target === 'charged') {
            return;
        }

        app(RecordProcessProduction::class)->handle($order->refresh(), (string) $qty); // → qc
        if ($target === 'qc') {
            return;
        }

        app(RecordQualityInspection::class)->handle($order->refresh(), (string) $qty, (string) $qty, '0', null, 'TESTCASE QC');
    }
}
