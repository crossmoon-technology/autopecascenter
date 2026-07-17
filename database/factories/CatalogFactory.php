<?php

namespace database\factories;

use App\Models\Catalog;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Catalog>
 */
class CatalogFactory extends Factory
{
    protected $model = Catalog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manufacturer_id' => Manufacturer::factory(),
            'name' => fake()->unique()->words(3, true),
            'file' => 'catalogs/' . fake()->uuid() . '.json',
            'extracted_at' => fake()->date(),
            'is_active' => true,
        ];
    }
}
