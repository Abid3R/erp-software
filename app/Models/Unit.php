<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Unit of measurement with exact decimal conversion (spec #19, #52).
 */
class Unit extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['company_id', 'base_unit_id', 'name', 'code', 'factor', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /**
     * How many base units one of this unit represents. A base unit resolves to 1.
     */
    public function factorToBase(): BigDecimal
    {
        return BigDecimal::of($this->factor);
    }

    /**
     * Convert a quantity expressed in this unit into base units. Exact decimal.
     */
    public function toBase(BigDecimal|string|int $quantity): BigDecimal
    {
        return BigDecimal::of($quantity)->multipliedBy($this->factorToBase());
    }

    /**
     * Convert a quantity from this unit into another unit that shares the same
     * base. Result is rounded to the configured quantity precision.
     */
    public function convertTo(self $target, BigDecimal|string|int $quantity): BigDecimal
    {
        if (! $this->sharesBaseWith($target)) {
            throw new InvalidArgumentException(
                "Cannot convert between units with different base units: {$this->code} -> {$target->code}."
            );
        }

        $scale = (int) config('erp.quantity_precision', 4);

        return $this->toBase($quantity)
            ->dividedBy($target->factorToBase(), $scale + 6, RoundingMode::HALF_UP)
            ->toScale($scale, RoundingMode::HALF_UP);
    }

    private function sharesBaseWith(self $other): bool
    {
        return $this->baseKey() === $other->baseKey();
    }

    /** The id of the ultimate base unit (self if this is a base unit). */
    private function baseKey(): int
    {
        return $this->base_unit_id ?? $this->getKey();
    }
}
