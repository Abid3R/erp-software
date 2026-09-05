<?php

namespace Database\Seeders;

use App\Actions\Export\AdvanceShipmentStatus;
use App\Actions\Export\AllocateProformaToLc;
use App\Actions\Export\CreateCommercialInvoiceFromSource;
use App\Actions\Export\GeneratePackingListFromInvoice;
use App\Actions\Export\PostCommercialInvoice;
use App\Actions\Payments\RecordCustomerReceipt;
use App\Enums\CommercialInvoiceStatus;
use App\Enums\ExportShipmentStatus;
use App\Enums\LetterOfCreditStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProformaInvoiceStatus;
use App\Exceptions\ExportException;
use App\Exceptions\PostingException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExportShipment;
use App\Models\LetterOfCredit;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

/**
 * Demonstrable export workflow for the DEMO company. Runs the real export actions
 * end to end (PI → allocate to LC → commercial invoice → post AR → packing list →
 * shipment → customer receipt) so every export screen and report shows genuine,
 * reconciled data. Idempotent: skips if letters of credit already exist.
 *
 *   php artisan db:seed --class="Database\Seeders\ExportDemoSeeder"
 */
class ExportDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'DEMO')->first();
        if ($company === null) {
            $this->command->error('Demo company (DEMO) not found. Run the main seeder first.');

            return;
        }

        app(CompanyContext::class)->runFor($company, function () use ($company): void {
            if (LetterOfCredit::query()->exists()) {
                $this->command->info('Export demo already seeded — skipping.');

                return;
            }

            $kg = Unit::query()->where('code', 'KG')->first() ?? Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
            $wh = Warehouse::query()->where('code', 'MAIN')->first();

            // Reuse the finished fabric if the textile demo ran; else create a sellable product.
            $fabric = Product::updateOrCreate(
                ['sku' => 'FG-FAB'],
                ['unit_id' => $kg->getKey(), 'name' => 'Finished Fabric (Navy, Compacted)', 'cost_price' => 480, 'selling_price' => 620, 'is_active' => true],
            );

            $buyer = Customer::query()->where('code', 'EXP-001')->first()
                ?? Customer::create(['company_id' => $company->getKey(), 'code' => 'EXP-001', 'name' => 'Global Textiles Importers LLC', 'is_active' => true]);

            // 1) A confirmed LC from the buyer's bank — USD 100,000 at 110 BDT/USD.
            $lc = LetterOfCredit::create([
                'lc_date' => now()->subDays(20), 'customer_id' => $buyer->getKey(),
                'beneficiary' => $company->legal_name ?? $company->name,
                'issuing_bank' => 'Citibank N.A., New York', 'advising_bank' => 'Standard Chartered, Dhaka',
                'amount' => 100000, 'currency_code' => 'USD', 'exchange_rate' => 110,
                'issue_date' => now()->subDays(20), 'expiry_date' => now()->addMonths(3),
                'latest_shipment_date' => now()->addMonths(2), 'payment_terms' => '60 days L/C',
                'port_of_loading' => 'Chattogram', 'port_of_discharge' => 'New York',
                'status' => LetterOfCreditStatus::Confirmed, 'description' => 'Navy compacted fabric, 550 kg',
            ]);

            // 2) Two proforma invoices, approved and allocated to the LC.
            $pi1 = $this->makePi($buyer, $fabric, $wh, 300, 200, 'At sight L/C');   // USD 60,000
            $pi2 = $this->makePi($buyer, $fabric, $wh, 250, 100, '60 days L/C');    // USD 25,000

            $allocate = app(AllocateProformaToLc::class);
            $allocate->handle($pi1, $lc);
            $allocate->handle($pi2, $lc->refresh());
            // LC now shows 85,000 allocated, 15,000 remaining, Partially utilised.

            // 3) Commercial invoice from PI-1 → approve → post AR (in Taka).
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
                $this->command->warn('Commercial invoice not posted (' . $e->getMessage() . ') — left approved.');
            }

            // 4) Packing list from the invoice.
            app(GeneratePackingListFromInvoice::class)->handle($ci->refresh());

            // 5) Shipment tying the documents together, advanced to Shipped.
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

            // 6) A partial customer receipt against the export receivable.
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

            $this->command->info('Export demo seeded: LC + 2 PIs (allocated) + commercial invoice (posted) + packing list + shipment + customer receipt.');
        });
    }

    private function makePi(Customer $buyer, Product $product, ?Warehouse $wh, float $qty, float $price, string $terms): ProformaInvoice
    {
        $pi = ProformaInvoice::create([
            'pi_date' => now()->subDays(15), 'customer_id' => $buyer->getKey(),
            'warehouse_id' => $wh?->getKey(), 'currency_code' => 'USD', 'exchange_rate' => 110,
            'payment_terms' => $terms, 'incoterm' => 'FOB', 'status' => ProformaInvoiceStatus::Approved,
        ]);
        $pi->lines()->create([
            'product_id' => $product->getKey(), 'description' => $product->name,
            'quantity' => $qty, 'unit_price' => $price,
        ]);

        return $pi->refresh()->load('lines');
    }
}
