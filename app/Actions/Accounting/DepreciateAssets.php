<?php

namespace App\Actions\Accounting;

use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\FixedAssetStatus;
use App\Models\Company;
use App\Models\FixedAsset;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a month's straight-line depreciation for every active asset (spec: Fixed
 * Assets). Each charge is Dr Depreciation Expense / Cr Accumulated Depreciation,
 * capped so accumulated never exceeds the depreciable base. Idempotent per asset
 * per period (unique constraint) and atomic.
 */
class DepreciateAssets
{
    public function __construct(private LedgerAccounts $accounts, private PostJournal $postJournal) {}

    /** @return array{count: int, total: string} */
    public function handle(Company $company, int $year, int $month): array
    {
        $companyId = (int) $company->getKey();
        $period = sprintf('%04d-%02d', $year, $month);
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $date = $periodEnd->format('Y-m-d');

        return DB::transaction(function () use ($company, $companyId, $period, $periodEnd, $date): array {
            $count = 0;
            $total = BigDecimal::zero();

            $assets = FixedAsset::query()->where('company_id', $companyId)
                ->where('status', FixedAssetStatus::Active->value)->get();

            foreach ($assets as $asset) {
                // Not yet in service, or already depreciated this period.
                if (Carbon::parse($asset->acquisition_date)->greaterThan($periodEnd)) {
                    continue;
                }
                if ($asset->depreciationEntries()->where('period', $period)->exists()) {
                    continue;
                }

                $remaining = $asset->remainingDepreciation();
                if ($remaining->isLessThanOrEqualTo(0)) {
                    $asset->update(['status' => FixedAssetStatus::FullyDepreciated]);

                    continue;
                }

                $amount = $asset->monthlyDepreciation();
                if ($amount->isGreaterThan($remaining)) {
                    $amount = $remaining; // final, partial month
                }
                if ($amount->isLessThanOrEqualTo(0)) {
                    continue;
                }

                $draft = JournalDraft::make($date, memo: "Depreciation {$asset->asset_code} {$period}", source: $asset)
                    ->debit($this->accounts->get('depreciation_expense', $companyId), $amount)
                    ->credit($this->accounts->get('accumulated_depreciation', $companyId), $amount);
                $journal = $this->postJournal->handle($draft, $company);

                $asset->depreciationEntries()->create([
                    'company_id' => $companyId, 'period' => $period,
                    'amount' => (string) $amount, 'journal_id' => $journal->getKey(),
                ]);

                $accumulated = BigDecimal::of($asset->accumulated_depreciation)->plus($amount);
                $asset->setAttribute('accumulated_depreciation', (string) $accumulated->toScale(2));
                if ($accumulated->isGreaterThanOrEqualTo($asset->depreciableBase())) {
                    $asset->status = FixedAssetStatus::FullyDepreciated;
                }
                $asset->save();

                $count++;
                $total = $total->plus($amount);
            }

            return ['count' => $count, 'total' => (string) $total->toScale(2)];
        });
    }
}
