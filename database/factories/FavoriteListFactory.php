<?php

namespace database\factories;

use App\Models\FavoriteList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FavoriteList>
 */
class FavoriteListFactory extends Factory
{
    protected $model = FavoriteList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['name' => 'Lista padrão', 'is_default' => true]);
    }
}
