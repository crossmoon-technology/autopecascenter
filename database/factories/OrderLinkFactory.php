<?php

namespace database\factories;

use App\Models\OrderLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderLink>
 */
class OrderLinkFactory extends Factory
{
    protected $model = OrderLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => null,
            'token' => Str::random(48),
            'used_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(['used_at' => now()]);
    }
}
