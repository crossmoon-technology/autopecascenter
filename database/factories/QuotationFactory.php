<?php

namespace database\factories;

use App\Models\Quotation;
use App\Models\Quotation\Enums\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => null,
            'status' => Status::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => Status::Closed]);
    }
}
