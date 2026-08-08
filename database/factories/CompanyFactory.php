<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->bothify('CO###??')),
            'currency_code' => 'BDT',
            'currency_symbol' => '৳',
            'timezone' => 'UTC',
            'fiscal_year_start_month' => 1,
            'default_tax_rate' => 15.000,
            'allow_negative_stock' => false,
            'is_active' => true,
        ];
    }
}
