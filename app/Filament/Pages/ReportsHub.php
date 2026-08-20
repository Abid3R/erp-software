<?php

namespace App\Filament\Pages;

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
