<?php

namespace database\factories;

use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => Status::Pending,
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => Status::Processing]);
    }

    public function finished(): static
    {
        return $this->state(['status' => Status::Finished]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Status::Cancelled]);
    }
}
