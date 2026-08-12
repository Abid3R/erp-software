<?php

namespace App\Actions\Crm;

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Converts a qualified lead into a Customer (spec: CRM). Idempotent — a lead
 * already linked to a customer returns it. Atomic.
 */
class ConvertLead
{
    public function handle(Lead $lead): Customer
    {
        $existing = $lead->customer;
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($lead): Customer {
            $customer = Customer::create([
                'company_id' => $lead->company_id,
                'name' => $lead->company_name ?: $lead->name,
                'code' => 'CUST-'.str_pad((string) (Customer::query()->count() + 1), 4, '0', STR_PAD_LEFT),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'is_active' => true,
            ]);

            $lead->update(['status' => LeadStatus::Converted, 'customer_id' => $customer->getKey()]);

            return $customer;
        });
    }
}
