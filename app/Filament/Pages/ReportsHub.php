<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BatchResource;
use App\Filament\Resources\BillOfMaterialsResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\JournalResource;
use App\Filament\Resources\ManufacturingOrderResource;
use App\Filament\Resources\StockResource;
use App\Filament\Resources\SupplierResource;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

/**
 * Reports hub — a single professional landing page that groups every report by
 * business area (Sales, Purchases, Inventory, Manufacturing, Accounts,
 * Receivables & Payables) and links to the existing report pages/resources.
 * Navigation only; each linked report computes from live ERP data as before.
 */
class ReportsHub extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'All Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Reports';

    protected static string $view = 'filament.pages.reports-hub';

    /**
     * @return array<int, array{
     *   heading: string, icon: string, color: string,
     *   items: array<int, array{label: string, description: string, url: string, icon: string}>
     * }>
     */
    public function getCategories(): array
    {
        return [
            [
                'heading' => 'Sales',
                'icon' => 'heroicon-o-shopping-cart',
                'color' => 'primary',
                'items' => [
                    ['label' => 'Sales Register', 'description' => 'Sales orders with ordered & delivered value', 'url' => SalesRegister::getUrl(), 'icon' => 'heroicon-o-clipboard-document-list'],
                    ['label' => 'Customer Statements', 'description' => 'Per-customer ledger activity & running balance', 'url' => CustomerResource::getUrl(), 'icon' => 'heroicon-o-user-group'],
                ],
            ],
            [
                'heading' => 'Export',
                'icon' => 'heroicon-o-globe-asia-australia',
                'color' => 'primary',
                'items' => [
                    ['label' => 'PI Register', 'description' => 'Proforma invoices in a period, with LC & status', 'url' => PiRegister::getUrl(), 'icon' => 'heroicon-o-document-text'],
                    ['label' => 'LC Register', 'description' => 'Letters of credit with live allocated / remaining', 'url' => LcRegister::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                    ['label' => 'LC Utilization', 'description' => 'Allocated vs remaining and utilisation % per LC', 'url' => LcUtilizationReport::getUrl(), 'icon' => 'heroicon-o-chart-pie'],
                    ['label' => 'LC Outstanding', 'description' => 'Active LCs with value still available, by expiry', 'url' => LcOutstandingReport::getUrl(), 'icon' => 'heroicon-o-clock'],
                    ['label' => 'Export Sales Register', 'description' => 'Posted commercial invoices as base-currency sales', 'url' => ExportSalesRegister::getUrl(), 'icon' => 'heroicon-o-currency-dollar'],
                    ['label' => 'Commercial Invoice Register', 'description' => 'All commercial invoices, foreign & base totals', 'url' => CommercialInvoiceRegister::getUrl(), 'icon' => 'heroicon-o-document-currency-dollar'],
                    ['label' => 'Shipment Register', 'description' => 'Consignments with vessel, container, BL/AWB', 'url' => ShipmentRegister::getUrl(), 'icon' => 'heroicon-o-globe-asia-australia'],
                    ['label' => 'Customer Export History', 'description' => 'Per-customer PIs, invoices, sales & shipments', 'url' => CustomerExportHistory::getUrl(), 'icon' => 'heroicon-o-user-group'],
                    ['label' => 'PI vs LC', 'description' => 'Each PI against its LC amount and remaining', 'url' => PiVsLcReport::getUrl(), 'icon' => 'heroicon-o-scale'],
                    ['label' => 'Order → PI → LC → Shipment', 'description' => 'The full export chain and its progress per deal', 'url' => OrderVsPiVsLcVsShipmentReport::getUrl(), 'icon' => 'heroicon-o-link'],
                    ['label' => 'Export Receivable', 'description' => 'Amounts billed on posted export invoices', 'url' => ExportReceivableReport::getUrl(), 'icon' => 'heroicon-o-arrow-down-circle'],
                ],
            ],
            [
                'heading' => 'Purchases',
                'icon' => 'heroicon-o-truck',
                'color' => 'warning',
                'items' => [
                    ['label' => 'Purchase Register', 'description' => 'Supplier invoices with net value', 'url' => PurchaseRegister::getUrl(), 'icon' => 'heroicon-o-clipboard-document-list'],
                    ['label' => 'Supplier Statements', 'description' => 'Per-supplier ledger activity & running balance', 'url' => SupplierResource::getUrl(), 'icon' => 'heroicon-o-building-office'],
                ],
            ],
            [
                'heading' => 'Inventory',
                'icon' => 'heroicon-o-cube',
                'color' => 'info',
                'items' => [
                    ['label' => 'Stock Valuation', 'description' => 'On-hand qty × moving-average cost', 'url' => StockValuation::getUrl(), 'icon' => 'heroicon-o-scale'],
                    ['label' => 'Stock Movement (Ledger)', 'description' => 'Every stock in/out movement per product', 'url' => StockLedger::getUrl(), 'icon' => 'heroicon-o-arrows-right-left'],
                    ['label' => 'Stock Levels', 'description' => 'Current on-hand balances by warehouse', 'url' => StockResource::getUrl(), 'icon' => 'heroicon-o-square-3-stack-3d'],
                ],
            ],
            [
                'heading' => 'Manufacturing',
                'icon' => 'heroicon-o-cog-6-tooth',
                'color' => 'gray',
                'items' => [
                    ['label' => 'Production Report', 'description' => 'Knitting / dyeing / finishing runs, filterable by process', 'url' => ProductionReport::getUrl(), 'icon' => 'heroicon-o-clipboard-document-check'],
                    ['label' => 'Production Costing', 'description' => 'Cost breakdown & actual unit cost per run', 'url' => ProductionCostingReport::getUrl(), 'icon' => 'heroicon-o-calculator'],
                    ['label' => 'Material Consumption', 'description' => 'Materials consumed by production in a period', 'url' => MaterialConsumptionReport::getUrl(), 'icon' => 'heroicon-o-arrow-down-on-square'],
                    ['label' => 'Production Wastage', 'description' => 'Wastage and QC rejects per order', 'url' => ProductionWastageReport::getUrl(), 'icon' => 'heroicon-o-trash'],
                    ['label' => 'QC Report', 'description' => 'Quality inspections with pass/reject outcomes', 'url' => QualityReport::getUrl(), 'icon' => 'heroicon-o-clipboard-document-check'],
                    ['label' => 'Machine Performance', 'description' => 'Runs, output, wastage and cost per machine', 'url' => MachinePerformanceReport::getUrl(), 'icon' => 'heroicon-o-cpu-chip'],
                    ['label' => 'Finished Production Register', 'description' => 'Finished goods produced (manufacturing orders)', 'url' => ProductionRegister::getUrl(), 'icon' => 'heroicon-o-clipboard-document-list'],
                    ['label' => 'WIP Valuation', 'description' => 'Capitalised work-in-progress on open orders', 'url' => WipValuation::getUrl(), 'icon' => 'heroicon-o-beaker'],
                    ['label' => 'MRP', 'description' => 'What to purchase or manufacture for demand', 'url' => Mrp::getUrl(), 'icon' => 'heroicon-o-square-3-stack-3d'],
                    ['label' => 'Batch Traceability', 'description' => 'Trace any batch back to its inputs and forward to its use', 'url' => BatchResource::getUrl(), 'icon' => 'heroicon-o-qr-code'],
                    ['label' => 'Manufacturing Orders', 'description' => 'Production orders and their status', 'url' => ManufacturingOrderResource::getUrl(), 'icon' => 'heroicon-o-wrench-screwdriver'],
                    ['label' => 'Bills of Materials', 'description' => 'Product recipes and component costs', 'url' => BillOfMaterialsResource::getUrl(), 'icon' => 'heroicon-o-beaker'],
                ],
            ],
            [
                'heading' => 'Accounts',
                'icon' => 'heroicon-o-calculator',
                'color' => 'success',
                'items' => [
                    ['label' => 'Profit & Loss / Balance Sheet / Trial Balance', 'description' => 'Financial statements from the ledger', 'url' => Reports::getUrl(), 'icon' => 'heroicon-o-chart-bar-square'],
                    ['label' => 'General Ledger', 'description' => 'Account-wise posted journal detail', 'url' => GeneralLedger::getUrl(), 'icon' => 'heroicon-o-book-open'],
                    ['label' => 'Payment Voucher Register', 'description' => 'Supplier payments in a period', 'url' => PaymentVoucherRegister::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                    ['label' => 'Receipt Voucher Register', 'description' => 'Customer receipts in a period', 'url' => ReceiptVoucherRegister::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                    ['label' => 'Journals', 'description' => 'All posted & draft journal entries', 'url' => JournalResource::getUrl(), 'icon' => 'heroicon-o-document-text'],
                ],
            ],
            [
                'heading' => 'Receivables & Payables',
                'icon' => 'heroicon-o-arrows-right-left',
                'color' => 'danger',
                'items' => [
                    ['label' => 'Receivables Aging', 'description' => 'Outstanding AR bucketed by age', 'url' => ReceivablesAging::getUrl(), 'icon' => 'heroicon-o-arrow-down-circle'],
                    ['label' => 'Payables Aging', 'description' => 'Outstanding AP bucketed by age', 'url' => PayablesAging::getUrl(), 'icon' => 'heroicon-o-arrow-up-circle'],
                ],
            ],
        ];
    }
}
