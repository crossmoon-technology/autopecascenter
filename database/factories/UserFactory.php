<?php

namespace database\factories;

use App\Enums\Role;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => Role::Client,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'document' => fake()->unique()->numerify('###########'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Um vendedor com e-mail confirmado que ainda não escolheu um plano — o estado logo
     * após o cadastro + confirmação de e-mail, antes de passar pela tela de escolha.
     */
    public function seller(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Seller,
            'plan' => null,
            'trial_ends_at' => null,
            'plan_approved_at' => null,
        ]);
    }

    /**
     * Um vendedor que já escolheu um plano e está com a avaliação gratuita de 7 dias
     * rodando — acesso liberado automaticamente, pagamento ainda não aprovado pelo
     * SuperAdmin.
     */
    public function inTrial(Plan $plan = Plan::Profissional): static
    {
        return $this->seller()->state(fn (array $attributes) => [
            'plan' => $plan,
            'trial_ends_at' => now()->addDays(7),
        ]);
    }

    /**
     * Um vendedor com o pagamento do plano aprovado pelo SuperAdmin — assinatura de 30
     * dias ainda dentro do prazo, o estado que a maioria dos testes que só precisam de "um
     * vendedor funcionando" realmente quer.
     */
    public function approvedSeller(Plan $plan = Plan::Basico): static
    {
        return $this->seller()->state(fn (array $attributes) => [
            'plan' => $plan,
            'trial_ends_at' => now()->subDay(),
            'plan_approved_at' => now(),
            'subscription_ends_at' => now()->addDays(30),
        ]);
    }

    /**
     * Um cliente vinculado ao vendedor dado (ver User::linkToSeller()) — substitui o
     * antigo `['invited_by_id' => $seller->id]`, já que um cliente pode ter vários
     * vendedores agora.
     */
    public function clientOf(User $seller): static
    {
        return $this->state(['role' => Role::Client])
            ->afterCreating(fn (User $client) => $client->linkToSeller($seller));
    }
}
