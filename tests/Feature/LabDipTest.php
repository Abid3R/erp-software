<?php

use App\Enums\LabDipStatus;
use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Journal;
use App\Models\LabDip;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('auto-numbers lab dips and starts them in draft', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $a = LabDip::create(['colour' => 'Navy Blue', 'status' => 'draft']);
    $b = LabDip::create(['colour' => 'Crimson', 'status' => 'draft']);

    expect($a->reference)->toBe('LD-0001')
        ->and($b->reference)->toBe('LD-0002')
        ->and($a->status)->toBe(LabDipStatus::Draft);
});

it('only exposes approved lab dips to the approved scope', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    LabDip::create(['colour' => 'A', 'status' => LabDipStatus::Draft]);
    $internal = LabDip::create(['colour' => 'B', 'status' => LabDipStatus::InternalApproved]);
    $customer = LabDip::create(['colour' => 'C', 'status' => LabDipStatus::CustomerApproved]);
    LabDip::create(['colour' => 'D', 'status' => LabDipStatus::Rejected]);

    $approved = LabDip::query()->approved()->pluck('id');

    expect($approved)->toHaveCount(2)
        ->toContain($internal->getKey())
        ->toContain($customer->getKey());
});

it('never creates any inventory or accounting entries', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $labDip = LabDip::create(['colour' => 'Navy Blue', 'status' => LabDipStatus::Draft]);
    // Walk it through the whole workflow.
    foreach ([
        LabDipStatus::Submitted, LabDipStatus::InLab, LabDipStatus::InternalApproved,
        LabDipStatus::SentToCustomer, LabDipStatus::CustomerApproved,
    ] as $status) {
        $labDip->update(['status' => $status]);
    }

    expect(InventoryTransaction::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Journal::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and($labDip->fresh()->status)->toBe(LabDipStatus::CustomerApproved);
});
