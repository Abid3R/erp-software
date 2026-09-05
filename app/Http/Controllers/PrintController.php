<?php

namespace App\Http\Controllers;

use App\Domain\Hr\RosterGenerator;
use App\Domain\Reporting\PartyStatement;
use App\Models\CommercialInvoice;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\PackingList;
use App\Models\ProformaInvoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Roster;
use App\Models\SalesOrder;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Support\CompanyContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Bare, print-optimized document views (spec #23, #31). Rendered as HTML so the
 * browser + the OS Bengali font shape Bangla correctly (unlike server-side PDF) —
 * the standard, license-clean way to print Bangla vouchers. Company access is
 * verified per document; the client cannot print another tenant's records.
 */
class PrintController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function payment(Payment $payment): View
    {
        $this->authorizeCompany((int) $payment->company_id);

        return view('print.payment', [
            'payment' => $payment->load('party', 'company'),
            'company' => $payment->company,
            'setting' => $payment->company?->reportSettingOrNew(),
        ]);
    }

    public function journal(Journal $journal): View
    {
        $this->authorizeCompany((int) $journal->company_id);

        return view('print.journal', [
            'journal' => $journal->load('lines.account', 'company'),
            'company' => $journal->company,
            'setting' => $journal->company?->reportSettingOrNew(),
        ]);
    }

    public function roster(Roster $roster): View
    {
        $this->authorizeCompany((int) $roster->company_id);

        $entries = $roster->entries()->with(['employee', 'shift'])->orderBy('date')->get();

        $dates = RosterGenerator::dateRange(
            Carbon::parse($roster->start_date)->format('Y-m-d'),
            Carbon::parse($roster->end_date)->format('Y-m-d'),
        );
        $employees = $entries->pluck('employee')->filter()->unique('id')->sortBy('employee_code')->values();

        /** @var array<int, array<string, string>> $grid */
        $grid = [];
        foreach ($entries as $entry) {
            $key = Carbon::parse($entry->date)->format('Y-m-d');
            $grid[$entry->employee_id][$key] = $entry->is_off ? 'OFF' : $entry->shift->name;
        }

        return view('print.roster', [
            'roster' => $roster,
            'company' => $roster->company,
            'setting' => $roster->company?->reportSettingOrNew(),
            'dates' => $dates,
            'employees' => $employees,
            'grid' => $grid,
        ]);
    }

    public function payslip(Payslip $payslip): View
    {
        $this->authorizeCompany((int) $payslip->company_id);

        return view('print.payslip', [
            'payslip' => $payslip->load(['employee.department', 'employee.designation', 'run', 'company']),
            'company' => $payslip->company,
            'setting' => $payslip->company?->reportSettingOrNew(),
        ]);
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizeCompany((int) $purchaseOrder->company_id);

        return view('print.purchase-order', [
            'po' => $purchaseOrder->load('lines.product', 'supplier', 'warehouse', 'company'),
            'company' => $purchaseOrder->company,
            'setting' => $purchaseOrder->company?->reportSettingOrNew(),
        ]);
    }

    public function salesOrder(SalesOrder $salesOrder): View
    {
        $this->authorizeCompany((int) $salesOrder->company_id);

        return view('print.sales-order', [
            'so' => $salesOrder->load('lines.product', 'customer', 'warehouse', 'company'),
            'company' => $salesOrder->company,
            'setting' => $salesOrder->company?->reportSettingOrNew(),
        ]);
    }

    public function quotation(Quotation $quotation): View
    {
        $this->authorizeCompany((int) $quotation->company_id);

        return view('print.quotation', [
            'quotation' => $quotation->load('lines.product', 'customer', 'warehouse', 'company'),
            'company' => $quotation->company,
            'setting' => $quotation->company?->reportSettingOrNew(),
        ]);
    }

    public function expense(Expense $expense): View
    {
        $this->authorizeCompany((int) $expense->company_id);

        return view('print.expense', [
            'expense' => $expense->load('lines.account', 'supplier', 'company'),
            'company' => $expense->company,
            'setting' => $expense->company?->reportSettingOrNew(),
        ]);
    }

    public function deliveryOrder(DeliveryOrder $deliveryOrder): View
    {
        $this->authorizeCompany((int) $deliveryOrder->company_id);

        return view('print.delivery-order', [
            'do' => $deliveryOrder->load('lines.product', 'customer', 'warehouse', 'salesOrder', 'company'),
            'company' => $deliveryOrder->company,
            'setting' => $deliveryOrder->company?->reportSettingOrNew(),
        ]);
    }

    public function proformaInvoice(ProformaInvoice $proformaInvoice): View
    {
        $this->authorizeCompany((int) $proformaInvoice->company_id);

        return view('print.proforma-invoice', [
            'pi' => $proformaInvoice->load('lines.product', 'customer', 'salesOrder', 'letterOfCredit', 'taxRate', 'company'),
            'company' => $proformaInvoice->company,
            'setting' => $proformaInvoice->company?->reportSettingOrNew(),
        ]);
    }

    public function commercialInvoice(CommercialInvoice $commercialInvoice): View
    {
        $this->authorizeCompany((int) $commercialInvoice->company_id);

        return view('print.commercial-invoice', [
            'ci' => $commercialInvoice->load('lines.product', 'customer', 'proformaInvoice', 'letterOfCredit', 'deliveryOrder', 'taxRate', 'company'),
            'company' => $commercialInvoice->company,
            'setting' => $commercialInvoice->company?->reportSettingOrNew(),
        ]);
    }

    public function packingList(PackingList $packingList): View
    {
        $this->authorizeCompany((int) $packingList->company_id);

        return view('print.packing-list', [
            'pl' => $packingList->load('lines.product', 'customer', 'commercialInvoice', 'shipment', 'company'),
            'company' => $packingList->company,
            'setting' => $packingList->company?->reportSettingOrNew(),
        ]);
    }

    public function goodsReceipt(GoodsReceipt $goodsReceipt): View
    {
        $this->authorizeCompany((int) $goodsReceipt->company_id);

        return view('print.goods-receipt', [
            'grn' => $goodsReceipt->load('lines.product', 'supplier', 'warehouse', 'purchaseOrder', 'company'),
            'company' => $goodsReceipt->company,
            'setting' => $goodsReceipt->company?->reportSettingOrNew(),
        ]);
    }

    public function stockAdjustment(StockAdjustment $stockAdjustment): View
    {
        $this->authorizeCompany((int) $stockAdjustment->company_id);

        return view('print.stock-adjustment', [
            'adjustment' => $stockAdjustment->load('lines.product', 'warehouse', 'company'),
            'company' => $stockAdjustment->company,
            'setting' => $stockAdjustment->company?->reportSettingOrNew(),
        ]);
    }

    public function supplierInvoice(SupplierInvoice $supplierInvoice): View
    {
        $this->authorizeCompany((int) $supplierInvoice->company_id);

        return view('print.supplier-invoice', [
            'invoice' => $supplierInvoice->load('lines.account', 'supplier', 'purchaseOrder', 'company'),
            'company' => $supplierInvoice->company,
            'setting' => $supplierInvoice->company?->reportSettingOrNew(),
        ]);
    }

    public function stockTransfer(StockTransfer $stockTransfer): View
    {
        $this->authorizeCompany((int) $stockTransfer->company_id);

        return view('print.stock-transfer', [
            'transfer' => $stockTransfer->load('lines.product', 'fromWarehouse', 'toWarehouse', 'company'),
            'company' => $stockTransfer->company,
            'setting' => $stockTransfer->company?->reportSettingOrNew(),
        ]);
    }

    public function customerStatement(Customer $customer): View
    {
        $this->authorizeCompany((int) $customer->company_id);

        return view('print.party-statement', [
            'party' => $customer,
            'kind' => 'Customer',
            'statement' => PartyStatement::forCustomer($customer),
            'company' => $customer->company,
            'setting' => $customer->company?->reportSettingOrNew(),
        ]);
    }

    public function supplierStatement(Supplier $supplier): View
    {
        $this->authorizeCompany((int) $supplier->company_id);

        return view('print.party-statement', [
            'party' => $supplier,
            'kind' => 'Supplier',
            'statement' => PartyStatement::forSupplier($supplier),
            'company' => $supplier->company,
            'setting' => $supplier->company?->reportSettingOrNew(),
        ]);
    }

    private function authorizeCompany(int $companyId): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->companies()->whereKey($companyId)->exists(), 403);

        $this->context->set($companyId);
    }
}
