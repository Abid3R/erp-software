<?php

use App\Actions\Crm\ConvertLead;
use App\Domain\Crm\PipelineSummary;
use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function crmCompany(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    return $company;
}

it('converts a lead into a customer and links it (idempotent)', function () {
    crmCompany();
    $lead = Lead::create(['name' => 'Kamal', 'company_name' => 'Ahmed Textiles', 'email' => 'k@a.test', 'phone' => '017', 'status' => 'qualified']);

    $customer = app(ConvertLead::class)->handle($lead);

    expect($customer->name)->toBe('Ahmed Textiles')          // uses company name when present
        ->and($customer->email)->toBe('k@a.test')
        ->and($lead->fresh()->status)->toBe(LeadStatus::Converted)
        ->and($lead->fresh()->customer_id)->toBe($customer->getKey());

    // Re-converting returns the same customer (no duplicate).
    $again = app(ConvertLead::class)->handle($lead->fresh());
    expect($again->getKey())->toBe($customer->getKey());
});

it('computes an opportunity\'s probability-weighted value', function () {
    crmCompany();
    $opp = Opportunity::create(['name' => 'Deal', 'stage' => 'proposal', 'expected_value' => 500000, 'probability' => 50]);

    expect((string) $opp->weightedValue())->toBe('250000.00'); // 500000 × 50%
});

it('summarises the open pipeline by stage, excluding won/lost', function () {
    crmCompany();
    Opportunity::create(['name' => 'A', 'stage' => 'proposal', 'expected_value' => 500000, 'probability' => 50]);
    Opportunity::create(['name' => 'B', 'stage' => 'proposal', 'expected_value' => 100000, 'probability' => 40]);
    Opportunity::create(['name' => 'C', 'stage' => 'negotiation', 'expected_value' => 800000, 'probability' => 75]);
    Opportunity::create(['name' => 'D', 'stage' => 'won', 'expected_value' => 999999, 'probability' => 100]);   // excluded
    Opportunity::create(['name' => 'E', 'stage' => 'lost', 'expected_value' => 999999, 'probability' => 0]);     // excluded

    $pipeline = collect(PipelineSummary::for((int) app(CompanyContext::class)->currentId()))->keyBy('stage');

    expect($pipeline['proposal']['count'])->toBe(2)
        ->and($pipeline['proposal']['total'])->toBe('600000.00')            // 500k + 100k
        ->and($pipeline['proposal']['weighted'])->toBe('290000.00')        // 250k + 40k
        ->and($pipeline['negotiation']['total'])->toBe('800000.00')
        ->and($pipeline['negotiation']['weighted'])->toBe('600000.00')     // 800k × 75%
        ->and($pipeline->has('won'))->toBeFalse()
        ->and($pipeline->has('lost'))->toBeFalse();
});
