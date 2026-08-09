<?php

use App\Actions\Workflow\ApproveStep;
use App\Actions\Workflow\RejectStep;
use App\Actions\Workflow\SubmitForApproval;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalFlow;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * Two non-overlapping flows for Payments: under 100k needs a manager; 100k+ needs
 * manager then director (spec #25 example). Configured as data, not code.
 */
function approvalSetup(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    Role::findOrCreate('manager');
    Role::findOrCreate('director');

    $small = ApprovalFlow::create([
        'company_id' => $company->getKey(), 'name' => 'Payments < 100k',
        'subject_type' => Payment::class, 'min_amount' => 0, 'max_amount' => 99999.99, 'is_active' => true,
    ]);
    $small->steps()->create(['sequence' => 1, 'role' => 'manager', 'name' => 'Manager approval']);

    $large = ApprovalFlow::create([
        'company_id' => $company->getKey(), 'name' => 'Payments >= 100k',
        'subject_type' => Payment::class, 'min_amount' => 100000, 'max_amount' => null, 'is_active' => true,
    ]);
    $large->steps()->create(['sequence' => 1, 'role' => 'manager', 'name' => 'Manager approval']);
    $large->steps()->create(['sequence' => 2, 'role' => 'director', 'name' => 'Director approval']);

    return $company;
}

function approver(Company $company, string $role): User
{
    $user = User::factory()->create();
    $user->companies()->attach($company);
    $user->assignRole($role);

    return $user;
}

function approvablePayment(Company $company, string $amount): Payment
{
    return Payment::create([
        'company_id' => $company->getKey(),
        'direction' => PaymentDirection::Payment,
        'date' => '2026-06-01',
        'amount' => $amount,
        'method' => PaymentMethod::Bank,
        'idempotency_key' => 'K-'.Str::random(10),
    ]);
}

it('selects the single-step flow and approves a small payment', function () {
    $company = approvalSetup();
    $manager = approver($company, 'manager');
    $payment = approvablePayment($company, '50000');

    $request = app(SubmitForApproval::class)->handle($payment, '50000');
    expect($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->current_step)->toBe(1);

    $result = app(ApproveStep::class)->handle($request, $manager);
    expect($result->status)->toBe(ApprovalStatus::Approved)
        ->and($result->actions()->count())->toBe(1);
});

it('requires sequential manager then director for a large payment', function () {
    $company = approvalSetup();
    $manager = approver($company, 'manager');
    $director = approver($company, 'director');
    $payment = approvablePayment($company, '150000');

    $request = app(SubmitForApproval::class)->handle($payment, '150000');
    expect($request->current_step)->toBe(1);

    $afterManager = app(ApproveStep::class)->handle($request, $manager);
    expect($afterManager->status)->toBe(ApprovalStatus::Pending)
        ->and($afterManager->current_step)->toBe(2);

    $afterDirector = app(ApproveStep::class)->handle($afterManager, $director);
    expect($afterDirector->status)->toBe(ApprovalStatus::Approved);
});

it('forbids approving a step without the required role', function () {
    $company = approvalSetup();
    $director = approver($company, 'director'); // not a manager
    $payment = approvablePayment($company, '150000'); // step 1 needs manager

    $request = app(SubmitForApproval::class)->handle($payment, '150000');

    expect(fn () => app(ApproveStep::class)->handle($request, $director))
        ->toThrow(ApprovalException::class);
});

it('forbids approving for a user outside the company', function () {
    $company = approvalSetup();
    $outsider = User::factory()->create();
    $outsider->assignRole('manager'); // right role, wrong company (no membership)
    $payment = approvablePayment($company, '50000');

    $request = app(SubmitForApproval::class)->handle($payment, '50000');

    expect(fn () => app(ApproveStep::class)->handle($request, $outsider))
        ->toThrow(ApprovalException::class);
});

it('rejects a request and allows resubmission', function () {
    $company = approvalSetup();
    $manager = approver($company, 'manager');
    $payment = approvablePayment($company, '50000');

    $request = app(SubmitForApproval::class)->handle($payment, '50000');
    $rejected = app(RejectStep::class)->handle($request, $manager, 'Defer to next month');
    expect($rejected->status)->toBe(ApprovalStatus::Rejected);

    $resubmitted = app(SubmitForApproval::class)->handle($payment, '50000');
    expect($resubmitted->status)->toBe(ApprovalStatus::Pending)
        ->and($resubmitted->getKey())->not->toBe($request->getKey());
});

it('throws when no approval flow matches', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $payment = approvablePayment($company, '50000');

    expect(fn () => app(SubmitForApproval::class)->handle($payment, '50000'))
        ->toThrow(ApprovalException::class);
});
