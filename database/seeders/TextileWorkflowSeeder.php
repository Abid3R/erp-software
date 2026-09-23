<?php

namespace Database\Seeders;

use App\Actions\Export\AdvanceShipmentStatus;
use App\Actions\Export\AllocateProformaToLc;
use App\Actions\Export\CreateCommercialInvoiceFromSource;
use App\Actions\Export\GeneratePackingListFromInvoice;
use App\Actions\Export\PostCommercialInvoice;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Payments\RecordCustomerReceipt;
use App\Actions\Process\CreateReworkOrder;
use App\Actions\Process\GenerateProductionPlanFromSalesOrder;
use App\Actions\Process\GenerateRolls;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Actions\Process\RecordSubcontractCharge;
use App\Enums\CommercialInvoiceStatus;
use App\Enums\ExportShipmentStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\LetterOfCreditStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProcessMode;
use App\Enums\ProformaInvoiceStatus;
use App\Exceptions\ExportException;
use App\Exceptions\PostingException;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExportShipment;
use App\Models\LabDip;
use App\Models\LetterOfCredit;
use App\Models\Machine;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseRequisition;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

/**
 * Seeder ② of two — TEXTILE WORKFLOW. Runs every textile + export process end to
 * end through the REAL domain actions (never raw inserts) so every screen, batch,
 * QC record, journal and report shows genuine, reconciled data. Depends on the
 * masters from {@see TextileMasterDataSeeder}. Idempotent: each block skips if its
 * marker records already exist.
 *
 * What it exercises:
 *   1. Purchasing → opening stock of yarn, dyes and chemicals.
 *   2. In-house ONE-PART dyeing chain, run as real process orders:
 *      Knit → Pretreat → Dye → Wash-off → Finish (issue → cost → produce → QC).
 *   3. In-house TWO-PART dyeing chain, run as real process orders:
 *      Knit → Pretreat → Dye 1 → Inter. Wash → Dye 2 → Wash-off → Finish.
 *   4. Knitting SUB-CONTRACT (job-work): issue yarn → service charge → receive grey → QC.
 *   5. Fabric rolls on the roll-tracked finished output.
 *   6. A QC-reject → rework order.
 *   7. A purchase requisition (the MRP output) for replenishment.
 *   8. A finished-fabric sales order + its production plan (dyeing phase auto-expands
 *      into one-part / two-part sub-steps from the approved lab dips).
 *   9. Export chain: PI → allocate to LC → commercial invoice (posted AR) → packing
 *      list → shipment → customer receipt.
 */
class TextileWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'DEMO')->first();
        if ($company === null) {
            $this->command->error('Demo company (DEMO) not found. Run the main DatabaseSeeder first.');

            return;
        }

        app(CompanyContext::class)->runFor($company, function () use ($company): void {
            $wh = Warehouse::query()->where('code', 'MAIN')->first();
            $kg = Unit::query()->where('code', 'KG')->first() ?? Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

            $this->inHouseChains($wh, $kg);
            $this->subcontract($wh, $kg);
            $this->reworkCase($wh);
            $this->purchaseRequisition();
            $this->salesOrderAndPlan($wh);
            $this->exportChain($company, $wh, $kg);

            $this->command->info('Textile workflow seeded: one-part + two-part dyeing chains, sub-contract, rolls, rework, requisition, production plan (expanded) and the export chain.');
        });
    }

    // ---- 1–3. In-house one-part & two-part dyeing chains -----------------------

    private function inHouseChains(?Warehouse $wh, Unit $kg): void
    {
        if (ProcessOrder::query()->where('mode', ProcessMode::InHouse->value)->exists()) {
            return;
        }

        $p = fn (string $sku): ?Product => Product::query()->where('sku', $sku)->first();
        $wip = fn (string $sku, string $name, string $stage): Product => Product::updateOrCreate(
            ['sku' => $sku],
            ['unit_id' => $kg->getKey(), 'name' => $name, 'cost_price' => 0, 'selling_price' => 0, 'is_active' => true,
                'is_textile' => true, 'textile_type' => $stage],
        );

        // Opening stock of the consumables the chains draw on.
        foreach ([
            ['RM-YARN', 2000, 250], ['RM-DYE', 100, 800], ['RM-DYE2', 50, 850], ['RM-CHEM', 200, 150],
            ['RM-SALT', 200, 15], ['RM-SODA', 100, 60], ['RM-PEROX', 100, 90],
        ] as [$sku, $qty, $cost]) {
            $product = $p($sku);
            if ($product !== null) {
                app(ReceiveStock::class)->handle($wh, $product, (string) $qty, (string) $cost, InventoryTransactionType::Opening, null);
            }
        }

        $type = fn (string $code): ?ProcessType => ProcessType::query()->where('code', $code)->first();
        $mc = fn (string $code): ?Machine => Machine::query()->where('code', $code)->first();
        $navyDip = LabDip::query()->where('colour', 'Navy Blue')->first();
        $tealDip = LabDip::query()->where('colour', 'Teal')->first();

        // ---------- ONE-PART chain (Navy, Single Jersey): Knit → Pretreat → Dye → Wash-off → Finish.
        $yarnBatch = Batch::create(['product_id' => $p('RM-YARN')->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 2000, 'notes' => 'Opening yarn lot']);
        $knitSpec = [
            'fabric_composition' => '80% Cotton 20% Polyester', 'gsm' => '160', 'fabric_width' => '72 Inch Open', 'colour' => 'Grey',
            'specifications' => ['machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '4.1+5.0+0.5', 'yarn_count' => '30s', 'fabric_type' => 'Single Jersey'],
        ];

        $greyB = $this->runProcess($type('KNIT'), $mc('KNIT-01'), $wh, $p('SF-GREY'), 480,
            [[$p('RM-YARN'), 470, $yarnBatch]], ['labour' => 6000, 'machine_hours' => 12, 'utility' => 1500, 'overhead' => 1000],
            wastage: 20, inspected: 480, passed: 470, rejected: 10, attributes: $knitSpec);

        $preB = $this->runProcess($type('PRETREAT'), $mc('DYE-01'), $wh, $wip('WIP-PRE-SJ', 'WIP — Pretreated Fabric (SJ)', 'grey_fabric'), 465,
            [[$p('SF-GREY'), 470, $greyB], [$p('RM-PEROX'), 10, null], [$p('RM-CHEM'), 15, null]],
            ['labour' => 1500, 'machine_hours' => 4, 'utility' => 1200, 'overhead' => 400], wastage: 5);

        $dyeB = $this->runProcess($type('DYE'), $mc('DYE-01'), $wh, $p('SF-DYED'), 455,
            [[$wip('WIP-PRE-SJ', 'WIP — Pretreated Fabric (SJ)', 'grey_fabric'), 460, $preB], [$p('RM-DYE'), 14, null], [$p('RM-SALT'), 18, null], [$p('RM-SODA'), 7, null]],
            ['labour' => 5000, 'machine_hours' => 8, 'utility' => 3000, 'overhead' => 1200], wastage: 5,
            inspected: 455, passed: 450, rejected: 5, labDip: $navyDip);

        $washB = $this->runProcess($type('WASHOFF'), $mc('DYE-01'), $wh, $wip('WIP-WASH-SJ', 'WIP — Washed Dyed Fabric (Navy)', 'dyed_fabric'), 448,
            [[$p('SF-DYED'), 450, $dyeB], [$p('RM-CHEM'), 5, null]],
            ['labour' => 1200, 'machine_hours' => 3, 'utility' => 900, 'overhead' => 300], wastage: 2);

        $finNavy = $this->runProcess($type('COMP') ?? $type('FINISH'), $mc('FIN-01'), $wh, $p('FG-FAB'), 443,
            [[$wip('WIP-WASH-SJ', 'WIP — Washed Dyed Fabric (Navy)', 'dyed_fabric'), 445, $washB]],
            ['labour' => 3000, 'machine_hours' => 6, 'utility' => 900, 'overhead' => 500], wastage: 2,
            inspected: 443, passed: 443, rejected: 0);

        // ---------- TWO-PART chain (Teal, Fleece): Knit → Pretreat → Dye 1 → Inter. Wash → Dye 2 → Wash-off → Finish.
        $greyFlB = $this->runProcess($type('KNIT'), $mc('KNIT-01'), $wh, $p('SF-GREY-FL'), 570,
            [[$p('RM-YARN'), 560, $yarnBatch]], ['labour' => 7000, 'machine_hours' => 14, 'utility' => 1800, 'overhead' => 1200],
            wastage: 30, inspected: 570, passed: 560, rejected: 10, attributes: [
                'fabric_composition' => '100% Cotton', 'gsm' => '320', 'fabric_width' => '68 Inch Open', 'colour' => 'Grey',
                'specifications' => ['machine_diameter' => '30', 'gauge' => '20', 'yarn_count' => '24s', 'fabric_type' => 'Fleece'],
            ]);

        $preFlB = $this->runProcess($type('PRETREAT'), $mc('DYE-01'), $wh, $wip('WIP-PRE-FL', 'WIP — Pretreated Fabric (Fleece)', 'grey_fabric'), 555,
            [[$p('SF-GREY-FL'), 560, $greyFlB], [$p('RM-PEROX'), 12, null], [$p('RM-CHEM'), 18, null]],
            ['labour' => 1800, 'machine_hours' => 5, 'utility' => 1400, 'overhead' => 500], wastage: 5);

        $dye1B = $this->runProcess($type('DYE1'), $mc('DYE-01'), $wh, $wip('WIP-D1-FL', 'WIP — Part-1 Dyed Fabric (Fleece)', 'dyed_fabric'), 545,
            [[$wip('WIP-PRE-FL', 'WIP — Pretreated Fabric (Fleece)', 'grey_fabric'), 550, $preFlB], [$p('RM-DYE'), 16, null], [$p('RM-SALT'), 20, null], [$p('RM-SODA'), 8, null]],
            ['labour' => 3000, 'machine_hours' => 6, 'utility' => 2200, 'overhead' => 800], wastage: 5,
            inspected: 545, passed: 540, rejected: 5, labDip: $tealDip);

        $iwashB = $this->runProcess($type('IWASH'), $mc('DYE-01'), $wh, $wip('WIP-IW-FL', 'WIP — Intermediate-Washed Fabric (Fleece)', 'dyed_fabric'), 538,
            [[$wip('WIP-D1-FL', 'WIP — Part-1 Dyed Fabric (Fleece)', 'dyed_fabric'), 540, $dye1B], [$p('RM-CHEM'), 6, null]],
            ['labour' => 900, 'machine_hours' => 2, 'utility' => 700, 'overhead' => 250], wastage: 2);

        $dye2B = $this->runProcess($type('DYE2'), $mc('DYE-01'), $wh, $wip('WIP-D2-FL', 'WIP — Part-2 Dyed Fabric (Fleece)', 'dyed_fabric'), 530,
            [[$wip('WIP-IW-FL', 'WIP — Intermediate-Washed Fabric (Fleece)', 'dyed_fabric'), 535, $iwashB], [$p('RM-DYE2'), 12, null], [$p('RM-SALT'), 12, null], [$p('RM-SODA'), 5, null]],
            ['labour' => 3000, 'machine_hours' => 6, 'utility' => 2200, 'overhead' => 800], wastage: 5,
            inspected: 530, passed: 525, rejected: 5, labDip: $tealDip);

        $woFlB = $this->runProcess($type('WASHOFF'), $mc('DYE-01'), $wh, $wip('WIP-WO-FL', 'WIP — Washed Dyed Fabric (Teal)', 'dyed_fabric'), 522,
            [[$wip('WIP-D2-FL', 'WIP — Part-2 Dyed Fabric (Fleece)', 'dyed_fabric'), 525, $dye2B], [$p('RM-CHEM'), 6, null]],
            ['labour' => 1200, 'machine_hours' => 3, 'utility' => 900, 'overhead' => 300], wastage: 3);

        $finTeal = $this->runProcess($type('COMP') ?? $type('FINISH'), $mc('FIN-01'), $wh, $p('FG-FAB-TEAL'), 518,
            [[$wip('WIP-WO-FL', 'WIP — Washed Dyed Fabric (Teal)', 'dyed_fabric'), 520, $woFlB]],
            ['labour' => 3500, 'machine_hours' => 7, 'utility' => 1000, 'overhead' => 600], wastage: 2,
            inspected: 518, passed: 518, rejected: 0);

        // 5. Rolls on every completed roll-tracked output (grey knitting + finished fabric).
        ProcessOrder::query()->where('status', 'completed')->whereNotNull('output_batch_id')
            ->with('outputProduct')->get()
            ->each(function (ProcessOrder $o): void {
                if ($o->outputProduct?->is_roll_tracked && (float) $o->produced_quantity > 0 && $o->rolls()->doesntExist()) {
                    app(GenerateRolls::class)->handle($o, (string) $o->produced_quantity, 4);
                }
            });
    }

    // ---- 4. Knitting sub-contract (job-work) ----------------------------------

    private function subcontract(?Warehouse $wh, Unit $kg): void
    {
        if (ProcessOrder::query()->where('mode', ProcessMode::Subcontract->value)->exists()) {
            return;
        }

        $subYarn = Product::updateOrCreate(['sku' => 'RM-YARN-SC'],
            ['unit_id' => $kg->getKey(), 'name' => 'Cotton Yarn 30s (sub-contract)', 'cost_price' => 250, 'selling_price' => 0, 'is_active' => true, 'is_textile' => true, 'textile_type' => 'yarn']);
        app(ReceiveStock::class)->handle($wh, $subYarn, '2000', '250', InventoryTransactionType::Opening, null);
        $subYarnBatch = Batch::create(['product_id' => $subYarn->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 2000, 'notes' => 'Yarn for sub-contract knitting']);

        $sub = Supplier::query()->where('code', 'SUP-KNIT')->first()
            ?? Supplier::updateOrCreate(['code' => 'SUP-KNIT'], ['name' => 'Acme Knitting Mills', 'is_active' => true]);
        $svc = Product::query()->where('sku', 'NS-KNIT')->first();
        $grey = Product::query()->where('sku', 'SF-GREY')->first();
        $knit = ProcessType::query()->where('code', 'KNIT')->first();

        $order = ProcessOrder::create([
            'process_type_id' => $knit->getKey(), 'mode' => ProcessMode::Subcontract,
            'warehouse_id' => $wh->getKey(), 'output_product_id' => $grey->getKey(),
            'subcontractor_id' => $sub->getKey(), 'service_item_id' => $svc->getKey(),
            'service_rate' => 25.0, 'bill_quantity' => 2000, 'service_currency' => config('erp.currency.code'),
            'planned_quantity' => 1960, 'status' => 'planned',
            'fabric_composition' => '80% Cotton 20% Polyester', 'gsm' => '160', 'colour' => 'Grey',
            'specifications' => ['machine_diameter' => '30', 'gauge' => '24', 'yarn_count' => '30s', 'fabric_type' => 'Single Jersey'],
            'notes' => 'Knitting job-work — Acme Knitting Mills',
        ]);
        $order->inputs()->create(['product_id' => $subYarn->getKey(), 'planned_quantity' => 2000, 'batch_id' => $subYarnBatch->getKey()]);

        app(IssueProcessMaterials::class)->handle($order);
        app(RecordSubcontractCharge::class)->handle($order->refresh());
        app(RecordProcessProduction::class)->handle($order->refresh(), '1960', '40');
        app(RecordQualityInspection::class)->handle($order->refresh(), '1960', '1960', '0', null, 'QC done');
    }

    // ---- 6. QC-reject → rework ------------------------------------------------

    private function reworkCase(?Warehouse $wh): void
    {
        if (ProcessOrder::query()->where('notes', 'like', 'Rework source%')->exists()) {
            return;
        }

        $knit = ProcessType::query()->where('code', 'KNIT')->first();
        $yarn = Product::query()->where('sku', 'RM-YARN')->first();
        $grey = Product::query()->where('sku', 'SF-GREY')->first();

        $order = ProcessOrder::create([
            'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(),
            'output_product_id' => $grey->getKey(), 'planned_quantity' => 120, 'status' => 'planned',
            'colour' => 'Grey', 'notes' => 'Rework source (shade variation)',
        ]);
        $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 120]);
        app(IssueProcessMaterials::class)->handle($order);
        app(RecordProcessProduction::class)->handle($order->refresh(), '120');
        app(RecordQualityInspection::class)->handle($order->refresh(), '120', '105', '15', 'Shade variation', 'QC reject → rework');
        app(CreateReworkOrder::class)->handle($order->refresh(), '15', 'Re-processing shade-varied fabric');
    }

    // ---- 7. Purchase requisition (MRP output) ---------------------------------

    private function purchaseRequisition(): void
    {
        if (PurchaseRequisition::query()->where('number', 'REQ-YARN-01')->exists()) {
            return;
        }
        $yarn = Product::query()->where('sku', 'RM-YARN')->first();
        if ($yarn === null) {
            return;
        }
        $req = PurchaseRequisition::create(['number' => 'REQ-YARN-01', 'needed_by' => now()->addDays(10)->toDateString(), 'status' => 'approved']);
        $req->lines()->create(['product_id' => $yarn->getKey(), 'quantity' => 3000]);
    }

    // ---- 8. Finished-fabric sales order + production plan (auto-expanded) ------

    private function salesOrderAndPlan(?Warehouse $wh): void
    {
        $so = SalesOrder::query()->where('so_number', 'SO-TEX-1')->first();
        if ($so === null) {
            $customer = Customer::query()->where('code', 'CUST-001')->first() ?? Customer::query()->first();
            $navy = Product::query()->where('sku', 'FG-FAB')->first();
            $teal = Product::query()->where('sku', 'FG-FAB-TEAL')->first();

            $so = SalesOrder::create([
                'so_number' => 'SO-TEX-1', 'customer_id' => $customer?->getKey(),
                'warehouse_id' => $wh?->getKey(), 'order_date' => now()->toDateString(),
                'delivery_date' => now()->addDays(30)->toDateString(), 'status' => 'confirmed',
            ]);
            if ($navy !== null) {
                $so->lines()->create(['product_id' => $navy->getKey(), 'quantity_ordered' => 400, 'unit_price' => 620]);
            }
            if ($teal !== null) {
                $so->lines()->create(['product_id' => $teal->getKey(), 'quantity_ordered' => 500, 'unit_price' => 690]);
            }
        }

        // Plan generation reads the approved lab dips and expands the dyeing phase
        // into one-part (Navy) and two-part (Teal) sub-steps automatically.
        app(GenerateProductionPlanFromSalesOrder::class)->handle($so->refresh()->load('lines.product'));
    }

    // ---- 9. Export chain ------------------------------------------------------

    private function exportChain(Company $company, ?Warehouse $wh, Unit $kg): void
    {
        if (LetterOfCredit::query()->exists()) {
            return;
        }

        $fabric = Product::query()->where('sku', 'FG-FAB')->first();
        $buyer = Customer::query()->where('code', 'EXP-001')->first()
            ?? Customer::create(['company_id' => $company->getKey(), 'code' => 'EXP-001', 'name' => 'Global Textiles Importers LLC', 'is_active' => true]);

        $lc = LetterOfCredit::create([
            'lc_date' => now()->subDays(20), 'customer_id' => $buyer->getKey(),
            'beneficiary' => $company->legal_name ?? $company->name,
            'issuing_bank' => 'Citibank N.A., New York', 'advising_bank' => 'Standard Chartered, Dhaka',
            'amount' => 100000, 'currency_code' => 'USD', 'exchange_rate' => 110,
            'issue_date' => now()->subDays(20), 'expiry_date' => now()->addMonths(3),
            'latest_shipment_date' => now()->addMonths(2), 'payment_terms' => '60 days L/C',
            'port_of_loading' => 'Chattogram', 'port_of_discharge' => 'New York',
            'status' => LetterOfCreditStatus::Confirmed, 'description' => 'Navy compacted fabric',
        ]);

        $pi1 = $this->makePi($buyer, $fabric, $wh, 300, 200, 'At sight L/C');
        $pi2 = $this->makePi($buyer, $fabric, $wh, 250, 100, '60 days L/C');

        $allocate = app(AllocateProformaToLc::class);
        $allocate->handle($pi1, $lc);
        $allocate->handle($pi2, $lc->refresh());

        $ci = app(CreateCommercialInvoiceFromSource::class)->fromProforma($pi1, [
            'consignee' => 'Global Textiles Importers LLC, NY', 'country_of_origin' => 'Bangladesh',
            'destination_country' => 'United States', 'incoterm' => 'FOB',
        ]);
        $ci->update(['status' => CommercialInvoiceStatus::Approved]);
        foreach ($ci->lines as $line) {
            $line->update(['hs_code' => '5208.52', 'unit' => 'KG']);
        }

        try {
            app(PostCommercialInvoice::class)->handle($ci->refresh()->load('lines', 'customer'));
        } catch (ExportException|PostingException $e) {
            $this->command->warn('Commercial invoice not posted ('.$e->getMessage().') — left approved.');
        }

        app(GeneratePackingListFromInvoice::class)->handle($ci->refresh());

        $shipment = ExportShipment::create([
            'shipment_date' => now()->subDays(2), 'customer_id' => $buyer->getKey(),
            'proforma_invoice_id' => $pi1->getKey(), 'letter_of_credit_id' => $lc->getKey(),
            'commercial_invoice_id' => $ci->getKey(),
            'port_of_loading' => 'Chattogram', 'port_of_discharge' => 'New York',
            'vessel_flight' => 'MV Bay Bridge V.221', 'container_no' => 'MSKU-7788990', 'seal_no' => 'SL-44521',
            'freight_forwarder' => 'Expeditors Intl', 'bl_awb_no' => 'BL-CTG-90477',
            'status' => ExportShipmentStatus::Draft,
        ]);
        $ci->update(['export_shipment_id' => $shipment->getKey()]);
        $advance = app(AdvanceShipmentStatus::class);
        $advance->handle($shipment, ExportShipmentStatus::ReadyForShipment);
        $advance->handle($shipment->refresh(), ExportShipmentStatus::Shipped);

        if ($ci->refresh()->status === CommercialInvoiceStatus::Posted) {
            $received = (string) $ci->toBase($ci->total())->dividedBy(2, 2, \Brick\Math\RoundingMode::HALF_UP);
            try {
                app(RecordCustomerReceipt::class)->handle(
                    $buyer, $received, PaymentMethod::Bank, now()->toDateString(),
                    'export-demo-'.$ci->getKey(), $ci->number, 'Advance against '.$ci->number,
                );
            } catch (\Throwable $e) {
                $this->command->warn('Receipt not recorded: '.$e->getMessage());
            }
        }
    }

    private function makePi(Customer $buyer, ?Product $product, ?Warehouse $wh, float $qty, float $price, string $terms): ProformaInvoice
    {
        $pi = ProformaInvoice::create([
            'pi_date' => now()->subDays(15), 'customer_id' => $buyer->getKey(),
            'warehouse_id' => $wh?->getKey(), 'currency_code' => 'USD', 'exchange_rate' => 110,
            'payment_terms' => $terms, 'incoterm' => 'FOB', 'status' => ProformaInvoiceStatus::Approved,
        ]);
        $pi->lines()->create([
            'product_id' => $product?->getKey(), 'description' => $product?->name,
            'quantity' => $qty, 'unit_price' => $price,
        ]);

        return $pi->refresh()->load('lines');
    }

    /**
     * Run one process order fully (issue → cost → produce → optional QC) and return
     * its output batch. QC is recorded only when $inspected is given (the process's
     * requires_qc types); otherwise production completes the order directly.
     *
     * @param  array<int, array{0: Product, 1: int|float, 2: Batch|null}>  $inputs
     * @param  array<string, int|float>  $costs
     * @param  array<string, mixed>  $attributes
     */
    private function runProcess(
        ?ProcessType $type, ?Machine $machine, ?Warehouse $wh, ?Product $output, int|float $plannedQty,
        array $inputs, array $costs = [], int|float $wastage = 0,
        int|float|null $inspected = null, int|float|null $passed = null, int|float|null $rejected = null,
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
        if ($costs !== []) {
            app(RecordProcessCosts::class)->handle($order->refresh(), $costs);
        }
        app(RecordProcessProduction::class)->handle($order->refresh(), (string) $plannedQty, (string) $wastage);
        if ($inspected !== null) {
            app(RecordQualityInspection::class)->handle(
                $order->refresh(), (string) $inspected, (string) ($passed ?? $inspected), (string) ($rejected ?? 0),
                ($rejected ?? 0) > 0 ? 'Minor defects' : null, 'QC done',
            );
        }

        return $order->refresh()->outputBatch;
    }
}
