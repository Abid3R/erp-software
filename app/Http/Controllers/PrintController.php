<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Payment;
use App\Support\CompanyContext;
use Illuminate\Contracts\View\View;
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

    private function authorizeCompany(int $companyId): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->companies()->whereKey($companyId)->exists(), 403);

        $this->context->set($companyId);
    }
}
