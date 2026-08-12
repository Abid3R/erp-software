<?php

namespace Database\Seeders;

use App\Actions\Hr\GeneratePayroll;
use App\Actions\Hr\GenerateRoster;
use App\Actions\Purchasing\CreateRfqFromRequisition;
use App\Actions\Payments\RecordCustomerReceipt;
use App\Actions\Purchasing\ReceiveGoods;
use App\Actions\Sales\RecordSale;
use App\Actions\Workflow\SubmitForApproval;
use App\Enums\AccountType;
use App\Enums\DocumentCategory;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\ApprovalFlow;
use App\Models\Attendance;
use App\Models\BillOfMaterials;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Department;
use App\Models\Designation;
use App\Models\FixedAsset;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Journal;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Opportunity;
use App\Models\ManufacturingOrder;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseReturn;
use App\Models\SupplierPrice;
use App\Models\Quotation;
use App\Models\ReportSetting;
use App\Models\RfqQuote;
use App\Models\Roster;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Admin credentials come from configuration (spec #59 — never hard-code a
     * production password). A demo company is provisioned so the admin has a
     * company membership (required for panel access) and later modules have an
     * organization to hang data on.
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $adminCfg */
        $adminCfg = config('erp.admin');

        $admin = User::query()->updateOrCreate(
            ['email' => $adminCfg['email']],
            [
                'name' => $adminCfg['name'],
                'password' => Hash::make($adminCfg['password']),
                'email_verified_at' => now(),
            ],
        );

        /** @var array{code: string, symbol: string} $currency */
        $currency = ['code' => config('erp.currency.code'), 'symbol' => config('erp.currency.symbol')];

        $company = Company::query()->updateOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Demo Company Ltd.',
                'legal_name' => 'Demo Company Limited',
                'currency_code' => $currency['code'],
                'currency_symbol' => $currency['symbol'],
                'timezone' => config('app.timezone', 'UTC'),
                'fiscal_year_start_month' => 1,
                'default_tax_rate' => 15.000,
                'allow_negative_stock' => false,
                'is_active' => true,
            ],
        );

        // Attach admin as the default member of the demo company.
        $admin->companies()->syncWithoutDetaching([
            $company->getKey() => ['is_default' => true],
        ]);

        // A branch and warehouse for the demo company.
        $branch = $company->branches()->updateOrCreate(
            ['code' => 'HO'],
            ['name' => 'Head Office', 'is_active' => true],
        );

        $company->warehouses()->updateOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'branch_id' => $branch->getKey(), 'is_active' => true],
        );

        // Regenerate Shield permissions (created by a command, not migrations, so
        // they must be re-established after migrate:fresh).
        Artisan::call('shield:generate', [
            '--all' => true, '--option' => 'permissions', '--panel' => 'admin', '--no-interaction' => true,
        ]);

        // Roles: super_admin (full access via Shield's gate bypass) + a read-only
        // Accountant, an Editor (report customisation), and approval roles.
        $superAdmin = Role::findOrCreate('super_admin');
        $superAdmin->givePermissionTo(Permission::all());

        $accountant = Role::findOrCreate('accountant');
        $accountant->syncPermissions(
            Permission::query()
                ->where('name', 'like', 'view_%')
                ->orWhere('name', 'like', 'page_%')
                ->orWhere('name', 'like', 'widget_%')
                ->pluck('name'),
        );

        // Editor: read-only across the app plus the report-customisation pages.
        $editor = Role::findOrCreate('editor');
        $editor->syncPermissions(
            Permission::query()
                ->where('name', 'like', 'page_%')
                ->orWhere('name', 'like', 'view_%')
                ->pluck('name'),
        );

        Role::findOrCreate('manager');
        Role::findOrCreate('director');

        // HR: manages employees, departments, designations.
        $hr = Role::findOrCreate('hr');
        $hr->syncPermissions(
            Permission::query()
                ->where('name', 'like', '%_employee')
                ->orWhere('name', 'like', '%_department')
                ->orWhere('name', 'like', '%_designation')
                ->orWhere('name', 'like', '%_shift')
                ->orWhere('name', 'like', '%_attendance')
                ->orWhere('name', 'like', '%_roster')
                ->orWhere('name', 'like', '%_leave_type')
                ->orWhere('name', 'like', '%_leave_request')
                ->orWhere('name', 'like', '%_payroll_run')
                ->orWhere('name', 'like', '%_holiday')
                ->pluck('name'),
        );

        // Admin is the super admin and also an approver (manager + director).
        $admin->syncRoles(['super_admin', 'manager', 'director']);

        // Demo catalog — created within the company context so company_id is
        // auto-stamped and scoped lookups resolve correctly.
        app(CompanyContext::class)->runFor($company, function () {
            $piece = Unit::updateOrCreate(['code' => 'PCS'], ['name' => 'Piece', 'factor' => 1]);
            Unit::updateOrCreate(['code' => 'CTN'], [
                'name' => 'Carton', 'base_unit_id' => $piece->getKey(), 'factor' => 24,
            ]);

            $category = Category::updateOrCreate(['name' => 'General'], ['is_active' => true]);
            $brand = Brand::updateOrCreate(['name' => 'Generic'], ['is_active' => true]);

            $items = [
                ['A4 Paper Ream', 'PAP-A4', 320.00, 420.00, 100],
                ['Ballpoint Pen', 'PEN-BP', 8.00, 15.00, 50],
                ['Stapler', 'STP-01', 180.00, 260.00, 10],
            ];

            foreach ($items as [$name, $sku, $cost, $price, $reorder]) {
                Product::updateOrCreate(['sku' => $sku], [
                    'name' => $name,
                    'unit_id' => $piece->getKey(),
                    'category_id' => $category->getKey(),
                    'brand_id' => $brand->getKey(),
                    'cost_price' => $cost,
                    'selling_price' => $price,
                    'reorder_level' => $reorder,
                    'is_active' => true,
                ]);
            }

            // Current fiscal year, open.
            $year = (int) date('Y');
            AccountingPeriod::updateOrCreate(
                ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31"],
                ['name' => "FY{$year}", 'fiscal_year' => $year, 'status' => PeriodStatus::Open],
            );

            // Default chart of accounts (spec #11).
            $coa = [
                ['1000', 'Cash', AccountType::Asset],
                ['1010', 'Bank', AccountType::Asset],
                ['1100', 'Accounts Receivable', AccountType::Asset],
                ['1200', 'Inventory', AccountType::Asset],
                ['1250', 'Input VAT Receivable', AccountType::Asset],
                ['1500', 'Fixed Assets', AccountType::Asset],
                ['1600', 'Accumulated Depreciation', AccountType::Asset],
                ['2000', 'Accounts Payable', AccountType::Liability],
                ['2100', 'Goods Received Not Invoiced', AccountType::Liability],
                ['2200', 'Output VAT Payable', AccountType::Liability],
                ['3000', 'Share Capital', AccountType::Equity],
                ['3100', 'Retained Earnings', AccountType::Equity],
                ['4000', 'Sales Revenue', AccountType::Revenue],
                ['4100', 'Sales Returns', AccountType::Revenue],
                ['5000', 'Cost of Goods Sold', AccountType::Expense],
                ['5100', 'Inventory Adjustment', AccountType::Expense],
                ['5200', 'Operating Expenses', AccountType::Expense],
                ['5300', 'Depreciation Expense', AccountType::Expense],
            ];

            foreach ($coa as [$code, $accountName, $type]) {
                Account::updateOrCreate(
                    ['code' => $code],
                    ['name' => $accountName, 'type' => $type, 'is_postable' => true, 'is_active' => true],
                );
            }

            $customer = Customer::updateOrCreate(['code' => 'CUST-001'], ['name' => 'Acme Retail', 'is_active' => true]);
            Supplier::updateOrCreate(['code' => 'SUP-001'], ['name' => 'Global Supplies Ltd.', 'is_active' => true]);

            // Demo transactions so the dashboard shows real figures. Guarded so a
            // repeat `db:seed` does not double-post to the immutable ledger.
            $warehouse = Warehouse::query()->where('code', 'MAIN')->first();
            $demoProduct = Product::query()->where('sku', 'PAP-A4')->first();

            if ($warehouse !== null && $demoProduct !== null && Journal::query()->doesntExist()) {
                $today = date('Y-m-d');
                app(ReceiveGoods::class)->handle($warehouse, $demoProduct, '100', '320', $today);
                app(RecordSale::class)->handle($warehouse, $demoProduct, '15', '420', $today, customer: $customer);
                app(RecordCustomerReceipt::class)->handle($customer, '3000', PaymentMethod::Cash, $today, 'SEED-RCPT-1');
            }

            // Demo HR org: departments, designations, and a small reporting line.
            if (Employee::query()->doesntExist()) {
                $eng = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
                $salesDept = Department::create(['name' => 'Sales', 'code' => 'SALES']);
                $managerRole = Designation::create(['title' => 'Manager', 'level' => 2]);
                $engineer = Designation::create(['title' => 'Software Engineer', 'level' => 4]);
                $salesExec = Designation::create(['title' => 'Sales Executive', 'level' => 4]);

                $boss = Employee::create([
                    'employee_code' => 'EMP-001', 'first_name' => 'Karim', 'last_name' => 'Rahman',
                    'department_id' => $eng->getKey(), 'designation_id' => $managerRole->getKey(),
                    'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2024-01-15',
                    'base_salary' => 120000,
                ]);
                Employee::create([
                    'employee_code' => 'EMP-002', 'first_name' => 'Nadia', 'last_name' => 'Islam',
                    'department_id' => $eng->getKey(), 'designation_id' => $engineer->getKey(),
                    'manager_id' => $boss->getKey(), 'employment_type' => 'permanent', 'status' => 'active',
                    'join_date' => '2024-03-01', 'base_salary' => 70000,
                ]);
                Employee::create([
                    'employee_code' => 'EMP-003', 'first_name' => 'Rahim', 'last_name' => 'Uddin',
                    'department_id' => $salesDept->getKey(), 'designation_id' => $salesExec->getKey(),
                    'manager_id' => $boss->getKey(), 'employment_type' => 'contract', 'status' => 'active',
                    'join_date' => '2024-06-01', 'base_salary' => 45000,
                ]);
            }

            // Demo shifts + attendance (metrics auto-computed on save).
            if (Shift::query()->doesntExist()) {
                $morning = Shift::create(['name' => 'Morning', 'code' => 'MORNING', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 60]);
                Shift::create(['name' => 'Night', 'code' => 'NIGHT', 'start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 30]);

                $emp = Employee::query()->where('employee_code', 'EMP-002')->first();
                if ($emp !== null) {
                    Attendance::create(['employee_id' => $emp->getKey(), 'shift_id' => $morning->getKey(), 'date' => '2026-08-03', 'check_in' => '09:12', 'check_out' => '17:45', 'status' => 'late']);
                    Attendance::create(['employee_id' => $emp->getKey(), 'shift_id' => $morning->getKey(), 'date' => '2026-08-04', 'check_in' => '08:58', 'check_out' => '17:05', 'status' => 'present']);
                }
            }

            // Demo confirmed Sales Order (open for delivery).
            if (SalesOrder::query()->doesntExist()) {
                $soCustomer = Customer::query()->where('code', 'CUST-001')->first();
                $soWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $soProduct = Product::query()->where('sku', 'PAP-A4')->first();
                if ($soCustomer !== null && $soWarehouse !== null && $soProduct !== null) {
                    $so = SalesOrder::create([
                        'so_number' => 'SO-0001', 'customer_id' => $soCustomer->getKey(),
                        'warehouse_id' => $soWarehouse->getKey(), 'order_date' => date('Y-m-d'),
                        'status' => 'confirmed',
                    ]);
                    $so->lines()->create(['product_id' => $soProduct->getKey(), 'quantity_ordered' => 20, 'unit_price' => 420]);
                }
            }

            // Demo sent Quotation (ready to convert to a sales order).
            if (Quotation::query()->doesntExist()) {
                $qtCustomer = Customer::query()->where('code', 'CUST-001')->first();
                $qtWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $qtProduct = Product::query()->where('sku', 'PAP-A4')->first();
                if ($qtCustomer !== null && $qtWarehouse !== null && $qtProduct !== null) {
                    $qt = Quotation::create([
                        'number' => 'QT-0001', 'customer_id' => $qtCustomer->getKey(),
                        'warehouse_id' => $qtWarehouse->getKey(), 'quote_date' => date('Y-m-d'),
                        'valid_until' => date('Y-m-d', strtotime('+14 days')), 'status' => 'sent',
                    ]);
                    $qt->lines()->create(['product_id' => $qtProduct->getKey(), 'quantity' => 25, 'unit_price' => 430]);
                }
            }

            // Demo DMS document attached to the quotation (private-disk file).
            if (Document::query()->doesntExist()) {
                $demoQuote = Quotation::query()->where('number', 'QT-0001')->first();
                if ($demoQuote !== null) {
                    $path = 'documents/demo-quotation-terms.txt';
                    Storage::disk(Document::DISK)->put($path, "Demo quotation terms & conditions.\nNet 30. Prices valid 14 days.\n");
                    $demoQuote->documents()->create([
                        'category' => DocumentCategory::Quotation->value,
                        'title' => 'Quotation terms & conditions',
                        'file_path' => $path,
                        'original_name' => 'quotation-terms.txt',
                        'notes' => 'Attached at issue.',
                    ]);
                }
            }

            // Demo draft Sales Return (goods coming back; post to reverse revenue/COGS).
            if (SalesReturn::query()->doesntExist()) {
                $srCustomer = Customer::query()->where('code', 'CUST-001')->first();
                $srWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $srProduct = Product::query()->where('sku', 'PAP-A4')->first();
                if ($srCustomer !== null && $srWarehouse !== null && $srProduct !== null) {
                    $sr = SalesReturn::create([
                        'number' => 'SR-0001', 'customer_id' => $srCustomer->getKey(),
                        'warehouse_id' => $srWarehouse->getKey(), 'return_date' => date('Y-m-d'),
                        'status' => 'draft',
                    ]);
                    $sr->lines()->create(['product_id' => $srProduct->getKey(), 'quantity' => 3, 'unit_price' => 420]);
                }
            }

            // Demo draft Stock Adjustment (physical count found extra stock).
            if (StockAdjustment::query()->doesntExist()) {
                $adjWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $adjProduct = Product::query()->where('sku', 'PAP-A4')->first();
                if ($adjWarehouse !== null && $adjProduct !== null) {
                    $adj = StockAdjustment::create([
                        'number' => 'ADJ-0001', 'warehouse_id' => $adjWarehouse->getKey(),
                        'adjustment_date' => date('Y-m-d'), 'reason' => 'Physical count', 'status' => 'draft',
                    ]);
                    $adj->lines()->create(['product_id' => $adjProduct->getKey(), 'direction' => 'in', 'quantity' => 5, 'unit_cost' => 320]);
                }
            }

            // Demo draft Stock Transfer (MAIN -> a second store warehouse).
            if (StockTransfer::query()->doesntExist()) {
                $mainWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $trfProduct = Product::query()->where('sku', 'PAP-A4')->first();
                if ($mainWarehouse !== null && $trfProduct !== null) {
                    $store = Warehouse::updateOrCreate(
                        ['company_id' => $mainWarehouse->company_id, 'code' => 'STORE'],
                        ['name' => 'Retail Store', 'branch_id' => $mainWarehouse->branch_id],
                    );
                    $trf = StockTransfer::create([
                        'number' => 'TRF-0001', 'from_warehouse_id' => $mainWarehouse->getKey(),
                        'to_warehouse_id' => $store->getKey(), 'transfer_date' => date('Y-m-d'), 'status' => 'draft',
                    ]);
                    $trf->lines()->create(['product_id' => $trfProduct->getKey(), 'quantity' => 10]);
                }
            }

            // Demo sourcing chain: an approved requisition -> RFQ with two supplier
            // quotes (ready for comparative statement + award).
            if (PurchaseRequisition::query()->doesntExist()) {
                $reqProduct = Product::query()->where('sku', 'PAP-A4')->first();
                $reqWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $sup1 = Supplier::query()->where('code', 'SUP-001')->first();
                $sup2 = Supplier::updateOrCreate(['code' => 'SUP-002'], ['name' => 'Beta Supplies Ltd.', 'is_active' => true]);
                if ($reqProduct !== null && $reqWarehouse !== null && $sup1 !== null) {
                    $req = PurchaseRequisition::create(['number' => 'REQ-0001', 'needed_by' => date('Y-m-d', strtotime('+7 days')), 'status' => 'approved']);
                    $req->lines()->create(['product_id' => $reqProduct->getKey(), 'quantity' => 100]);

                    $rfq = app(CreateRfqFromRequisition::class)->handle($req, (int) $reqWarehouse->getKey());
                    $rfqLine = $rfq->lines()->first();
                    if ($rfqLine !== null) {
                        RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $rfqLine->getKey(), 'supplier_id' => $sup1->getKey(), 'unit_price' => 320]);
                        RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $rfqLine->getKey(), 'supplier_id' => $sup2->getKey(), 'unit_price' => 310]);
                    }
                }
            }

            // Demo buying price (auto-fills PO lines) + a draft purchase return.
            if (SupplierPrice::query()->doesntExist()) {
                $bpSupplier = Supplier::query()->where('code', 'SUP-001')->first();
                $bpProduct = Product::query()->where('sku', 'PAP-A4')->first();
                $bpWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                if ($bpSupplier !== null && $bpProduct !== null) {
                    SupplierPrice::create(['supplier_id' => $bpSupplier->getKey(), 'product_id' => $bpProduct->getKey(), 'unit_price' => 315]);

                    if ($bpWarehouse !== null) {
                        $pr = PurchaseReturn::create([
                            'number' => 'PR-0001', 'supplier_id' => $bpSupplier->getKey(),
                            'warehouse_id' => $bpWarehouse->getKey(), 'return_date' => date('Y-m-d'), 'status' => 'draft',
                        ]);
                        $pr->lines()->create(['product_id' => $bpProduct->getKey(), 'quantity' => 5]);
                    }
                }
            }

            // Demo approved Purchase Order (open for receiving).
            if (PurchaseOrder::query()->doesntExist()) {
                $supplier = Supplier::query()->where('code', 'SUP-001')->first();
                $poWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $poProduct = Product::query()->where('sku', 'PAP-A4')->first();
                if ($supplier !== null && $poWarehouse !== null && $poProduct !== null) {
                    $po = PurchaseOrder::create([
                        'po_number' => 'PO-0001', 'supplier_id' => $supplier->getKey(),
                        'warehouse_id' => $poWarehouse->getKey(), 'order_date' => date('Y-m-d'),
                        'status' => 'approved',
                    ]);
                    $po->lines()->create(['product_id' => $poProduct->getKey(), 'quantity_ordered' => 50, 'unit_price' => 320]);
                }
            }

            // Demo Bill of Materials: a garment made from its raw materials, with a
            // planned manufacturing order (realistic BOM example).
            if (BillOfMaterials::query()->doesntExist()) {
                $mainWarehouse = Warehouse::query()->where('code', 'MAIN')->first();
                $meter = Unit::updateOrCreate(['code' => 'MTR'], ['name' => 'Meter', 'factor' => 1]);

                // Raw materials (each is a product with its own cost).
                $material = fn (string $sku, string $name, int $unitId, float $cost): Product => Product::create([
                    'unit_id' => $unitId, 'sku' => $sku, 'name' => $name, 'cost_price' => $cost, 'selling_price' => 0,
                ]);
                $fabric = $material('RM-FABRIC', 'Cotton Fabric', $meter->getKey(), 180);   // per metre
                $thread = $material('RM-THREAD', 'Sewing Thread', $piece->getKey(), 25);
                $collar = $material('RM-COLLAR', 'Knit Collar', $piece->getKey(), 40);
                $button = $material('RM-BUTTON', 'Button', $piece->getKey(), 2);

                // Finished garment.
                $jersey = Product::create([
                    'unit_id' => $piece->getKey(), 'sku' => 'FG-JERSEY', 'name' => 'Polo Jersey',
                    'cost_price' => 0, 'selling_price' => 650,
                ]);

                // Recipe: 1 jersey = 1.5m fabric + 0.2 thread + 1 collar + 3 buttons
                // (standard material cost ≈ 321).
                $bom = BillOfMaterials::create(['product_id' => $jersey->getKey(), 'name' => 'Polo Jersey v1', 'output_quantity' => 1]);
                $bom->components()->create(['component_product_id' => $fabric->getKey(), 'quantity' => 1.5]);
                $bom->components()->create(['component_product_id' => $thread->getKey(), 'quantity' => 0.2]);
                $bom->components()->create(['component_product_id' => $collar->getKey(), 'quantity' => 1]);
                $bom->components()->create(['component_product_id' => $button->getKey(), 'quantity' => 3]);

                if ($mainWarehouse !== null) {
                    ManufacturingOrder::create([
                        'reference' => 'MO-0001', 'bill_of_materials_id' => $bom->getKey(),
                        'product_id' => $jersey->getKey(), 'warehouse_id' => $mainWarehouse->getKey(),
                        'quantity' => 100, 'status' => 'planned',
                    ]);
                }
            }

            // Demo CRM: a couple of leads and pipeline opportunities.
            if (Lead::query()->doesntExist()) {
                Lead::create(['name' => 'Kamal Ahmed', 'company_name' => 'Ahmed Textiles', 'email' => 'kamal@ahmed.test', 'phone' => '01711000000', 'source' => 'Trade show', 'status' => 'qualified']);
                Lead::create(['name' => 'Rina Das', 'company_name' => 'Das Garments', 'email' => 'rina@das.test', 'source' => 'Website', 'status' => 'new']);

                $crmCustomer = Customer::query()->where('code', 'CUST-001')->first();
                Opportunity::create(['name' => 'Jersey bulk order', 'customer_id' => $crmCustomer?->getKey(), 'stage' => 'proposal', 'expected_value' => 500000, 'probability' => 50, 'expected_close_date' => date('Y-m-d', strtotime('+30 days'))]);
                Opportunity::create(['name' => 'Uniform contract', 'stage' => 'negotiation', 'expected_value' => 800000, 'probability' => 75, 'expected_close_date' => date('Y-m-d', strtotime('+45 days'))]);
            }

            // Demo fixed asset (depreciable).
            if (FixedAsset::query()->doesntExist()) {
                FixedAsset::create([
                    'asset_code' => 'FA-0001', 'name' => 'Delivery Van', 'category' => 'Vehicle',
                    'acquisition_date' => '2026-01-01', 'acquisition_cost' => 1200000, 'salvage_value' => 200000,
                    'useful_life_months' => 60, 'status' => 'active',
                ]);
            }

            // Public holidays (excluded from leave working-day counts).
            if (Holiday::query()->doesntExist()) {
                Holiday::create(['name' => 'Independence Day', 'date' => '2026-03-26']);
                Holiday::create(['name' => 'Victory Day', 'date' => '2026-12-16']);
            }

            // Leave types + a demo pending leave request (days auto-computed).
            if (LeaveType::query()->doesntExist()) {
                $annual = LeaveType::create(['name' => 'Annual Leave', 'code' => 'ANNUAL', 'annual_quota' => 20]);
                LeaveType::create(['name' => 'Sick Leave', 'code' => 'SICK', 'annual_quota' => 14]);
                LeaveType::create(['name' => 'Casual Leave', 'code' => 'CASUAL', 'annual_quota' => 10]);

                $leaveEmp = Employee::query()->where('employee_code', 'EMP-002')->first();
                if ($leaveEmp !== null) {
                    LeaveRequest::create([
                        'employee_id' => $leaveEmp->getKey(), 'leave_type_id' => $annual->getKey(),
                        'start_date' => '2026-09-10', 'end_date' => '2026-09-12',
                        'reason' => 'Family trip', 'status' => 'pending',
                    ]);
                }
            }

            // Demo payroll run with sample allowances/deductions on one payslip.
            if (PayrollRun::query()->doesntExist()) {
                $payrollEmpIds = Employee::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($payrollEmpIds !== []) {
                    $run = app(GeneratePayroll::class)->handle(
                        'Payroll '.now()->format('M Y'), (int) now()->year, (int) now()->month, $payrollEmpIds,
                    );
                    $slip = $run->payslips()->first();
                    if ($slip !== null) {
                        $slip->update([
                            'allowances' => [['label' => 'House Rent', 'amount' => '5000'], ['label' => 'Medical', 'amount' => '1500']],
                            'deductions' => [['label' => 'Provident Fund', 'amount' => '2000']],
                        ]);
                    }
                }
            }

            // Demo auto-generated roster for the current week.
            if (Roster::query()->doesntExist()) {
                $empIds = Employee::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $shiftIds = Shift::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($empIds !== [] && $shiftIds !== []) {
                    app(GenerateRoster::class)->handle(
                        'Weekly Roster',
                        now()->startOfWeek()->format('Y-m-d'),
                        now()->startOfWeek()->addDays(6)->format('Y-m-d'),
                        $empIds, $shiftIds, 1, 48,
                    );
                }
            }

            // Demo report branding so documents show a letterhead/footer.
            ReportSetting::updateOrCreate(
                ['company_id' => app(CompanyContext::class)->currentId()],
                [
                    'show_logo' => true,
                    'header_note' => '123 Demo Road, Dhaka 1207 · +880 1700-000000',
                    'footer_note' => 'Thank you for your business.',
                    'signatory_left' => 'Prepared by',
                    'signatory_right' => 'Authorised signature',
                ],
            );

            // Approval flow for large supplier payments + a demo pending request,
            // so the Approvals inbox has content for the admin (manager/director).
            if (ApprovalRequest::query()->doesntExist()) {
                $flow = ApprovalFlow::updateOrCreate(
                    ['subject_type' => Payment::class, 'min_amount' => 25000],
                    ['name' => 'Payments >= 25k', 'max_amount' => null, 'is_active' => true],
                );
                $flow->steps()->updateOrCreate(['sequence' => 1], ['role' => 'manager', 'name' => 'Manager approval']);
                $flow->steps()->updateOrCreate(['sequence' => 2], ['role' => 'director', 'name' => 'Director approval']);

                $supplier = Supplier::query()->where('code', 'SUP-001')->first();
                if ($supplier !== null) {
                    $pending = Payment::create([
                        'direction' => PaymentDirection::Payment,
                        'party_type' => $supplier->getMorphClass(),
                        'party_id' => $supplier->getKey(),
                        'date' => date('Y-m-d'),
                        'amount' => 50000,
                        'method' => PaymentMethod::Bank,
                        'idempotency_key' => 'SEED-APPROVAL-1',
                    ]);
                    app(SubmitForApproval::class)->handle($pending, '50000');
                }
            }
        });

        $this->command->info("Seeded admin {$adminCfg['email']} + demo company ({$company->code}) + demo catalog.");
    }
}
