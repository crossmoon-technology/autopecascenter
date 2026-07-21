<?php

namespace database\factories;

use App\Models\SearchHistory;
use App\Models\SearchHistory\Enums\Method;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchHistory>
 */
class SearchHistoryFactory extends Factory
{
    protected $model = SearchHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'query' => fake()->bothify('#####??'),
            'method' => fake()->randomElement(Method::cases()),
        ];
    }
}
