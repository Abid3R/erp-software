<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, array{label: string, amount: string}>|null $allowances
 * @property array<int, array{label: string, amount: string}>|null $deductions
 */
class Payslip extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id', 'basic', 'worked_days',
        'absent_days', 'allowances', 'deductions', 'gross', 'net',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'allowances' => 'array',
            'deductions' => 'array',
            'basic' => 'decimal:2',
            'gross' => 'decimal:2',
            'net' => 'decimal:2',
            'worked_days' => 'integer',
            'absent_days' => 'integer',
        ];
    }

    public static function booted(): void
    {
        // gross = basic + allowances; net = gross − deductions (exact decimals).
        static::saving(function (Payslip $payslip): void {
            $gross = BigDecimal::of($payslip->basic ?? '0')->plus(self::total($payslip->allowances));
            $net = $gross->minus(self::total($payslip->deductions));
            $payslip->setAttribute('gross', (string) $gross->toScale(2, RoundingMode::HALF_UP));
            $payslip->setAttribute('net', (string) $net->toScale(2, RoundingMode::HALF_UP));
        });
    }

    /** @param array<int, array{label?: string, amount?: mixed}>|null $items */
    public static function total(?array $items): BigDecimal
    {
        return collect($items ?? [])->reduce(function (BigDecimal $carry, array $item): BigDecimal {
            $amount = (string) ($item['amount'] ?? 0);

            return $carry->plus(BigDecimal::of($amount === '' ? '0' : $amount));
        }, BigDecimal::zero());
    }

    public function allowanceTotal(): string
    {
        return (string) self::total($this->allowances)->toScale(2, RoundingMode::HALF_UP);
    }

    public function deductionTotal(): string
    {
        return (string) self::total($this->deductions)->toScale(2, RoundingMode::HALF_UP);
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
