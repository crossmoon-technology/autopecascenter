<?php

namespace database\factories;

use App\Models\Catalog;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_id' => Catalog::factory(),
            'codigo' => fake()->unique()->numerify('#####'),
            'descricao' => fake()->words(3, true),
            'tipo' => fake()->words(2, true),
            'posicao' => fake()->words(2, true),
            'categoria' => fake()->word(),
            'conversoes' => [
                'MONROE' => [fake()->bothify('??####')],
                'NAKATA' => [fake()->bothify('MG ?????')],
            ],
        ];
    }
}
