<?php

namespace database\factories;

use App\Models\Catalog;
use App\Models\Informativo;
use App\Models\Informativo\Enums\InformativoType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Informativo>
 */
class InformativoFactory extends Factory
{
    protected $model = Informativo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_id' => Catalog::factory(),
            'file' => 'catalogs/informativos/'.fake()->uuid().'.pdf',
            'original_name' => fake()->words(3, true).'.pdf',
            'type' => InformativoType::Pdf,
        ];
    }
}
