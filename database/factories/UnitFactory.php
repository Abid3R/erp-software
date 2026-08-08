<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'base_unit_id' => null,
            'name' => 'Piece',
            'code' => strtoupper(fake()->unique()->bothify('U-???##')),
            'factor' => 1,
            'is_active' => true,
        ];
    }
}
