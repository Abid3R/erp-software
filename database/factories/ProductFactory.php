<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Product and its stock unit share the same company.
        $company = Company::factory()->create();
        $unit = Unit::factory()->create(['company_id' => $company->getKey()]);

        return [
            'company_id' => $company->getKey(),
            'unit_id' => $unit->getKey(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'name' => fake()->words(3, true),
            'cost_price' => fake()->randomFloat(2, 10, 500),
            'selling_price' => fake()->randomFloat(2, 20, 900),
            'tracks_batch' => false,
            'tracks_serial' => false,
            'is_active' => true,
        ];
    }
}
