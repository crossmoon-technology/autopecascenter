<?php

namespace database\factories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItem\Enums\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'source' => Source::Iframe,
            'codigo' => fake()->unique()->bothify('??-####'),
        ];
    }
}
