<?php

use App\Actions\Workflow\SubmitForApproval;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\ApprovalFlow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Support\CompanyContext;
use Spatie\Permission\Models\Role;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('notifies HR and managers when a leave request is submitted', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $hr = memberWithRoles($company, 'hr');
    $outsider = memberWithRoles($company); // company member, no role

    $type = LeaveType::create(['name' => 'Annual', 'code' => 'ANNUAL', 'annual_quota' => 20]);
    $employee = Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara',
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);

    LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-09-10', 'end_date' => '2026-09-11', 'status' => 'pending',
    ]);

    expect($hr->notifications()->count())->toBe(1)
        ->and($outsider->notifications()->count())->toBe(0);
});

it('notifies approvers when a document is submitted for approval', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    Role::findOrCreate('manager');

    $manager = memberWithRoles($company, 'manager');
    $flow = ApprovalFlow::create([
        'company_id' => $company->getKey(), 'name' => 'Payments', 'subject_type' => Payment::class,
        'min_amount' => 0, 'max_amount' => null, 'is_active' => true,
    ]);
    $flow->steps()->create(['sequence' => 1, 'role' => 'manager']);

    $payment = Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Payment,
        'date' => '2026-06-01', 'amount' => '1000', 'method' => PaymentMethod::Cash,
        'idempotency_key' => 'NOTIF-1',
    ]);

    app(SubmitForApproval::class)->handle($payment, '1000');

    expect($manager->notifications()->count())->toBe(1);
});
