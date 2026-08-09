<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('records an audit entry when an auditable model is created', function () {
    $customer = Customer::factory()->create();

    $log = AuditLog::query()
        ->where('auditable_type', $customer->getMorphClass())
        ->where('auditable_id', $customer->getKey())
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('created')
        ->and($log->new_values['name'])->toBe($customer->name)
        ->and($log->company_id)->toBe($customer->company_id);
});

it('records old and new values on update', function () {
    $customer = Customer::factory()->create(['name' => 'Original']);

    $customer->update(['name' => 'Renamed']);

    $log = AuditLog::query()
        ->where('auditable_id', $customer->getKey())
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['name'])->toBe('Original')
        ->and($log->new_values['name'])->toBe('Renamed');
});

it('is append-only — raw update is blocked at the database level', function () {
    $customer = Customer::factory()->create();
    $log = AuditLog::query()->firstOrFail();

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $log->getKey())->update(['event' => 'hacked'])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $log->getKey())->delete()))
        ->toThrow(QueryException::class);
});
