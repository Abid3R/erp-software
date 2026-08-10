<?php

namespace App\Http\Controllers;

use App\Domain\Hr\RosterGenerator;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Roster;
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

    private function authorizeCompany(int $companyId): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->companies()->whereKey($companyId)->exists(), 403);

        $this->context->set($companyId);
    }
}
