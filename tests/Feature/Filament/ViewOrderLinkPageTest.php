<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\ViewOrderLink;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewOrderLinkPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_does_not_appear_in_navigation(): void
    {
        $this->assertFalse(ViewOrderLink::shouldRegisterNavigation());
    }

    public function test_shows_the_shareable_link_when_not_used_yet(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['token' => 'abc123']);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->assertSuccessful()
            ->assertSee($link->publicUrl(), false)
            ->assertSee('Aguardando cadastro');
    }

    public function test_shows_a_generate_link_button_when_there_is_no_token_yet(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['label' => 'Oficina do João']);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->assertSuccessful()
            ->assertSee('Nenhum link gerado ainda')
            ->assertDontSee('pedido/', false);
    }

    public function test_generate_link_creates_a_token_only_when_clicked(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['label' => 'Oficina do João']);

        $this->assertNull($link->token);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->call('generateLink')
            ->assertSuccessful()
            ->assertSee('Link pra enviar ao cliente');

        $this->assertNotNull($link->fresh()->token);
    }

    public function test_shows_the_registered_client_but_no_order_yet(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $registeredClient = User::factory()->create(['role' => Role::Client, 'name' => 'João da Oficina']);
        $link = $user->orderLinks()->create([
            'token' => 'abc123',
            'used_at' => now(),
            'registered_user_id' => $registeredClient->id,
        ]);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->assertSuccessful()
            ->assertSee('João da Oficina')
            ->assertSee('O cliente ainda não enviou nenhum pedido.')
            ->assertDontSee($link->publicUrl(), false);
    }

    public function test_shows_the_submitted_order_items_quantities_and_manufacturer_preferences_when_present(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $registeredClient = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($registeredClient)->create();
        $item = $order->items()->create(['description' => 'Pastilha de freio dianteira', 'quantity' => 4]);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap']);
        $item->preferredManufacturers()->attach($manufacturer);

        $link = $user->orderLinks()->create([
            'token' => 'abc123',
            'used_at' => now(),
            'registered_user_id' => $registeredClient->id,
        ]);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->assertSuccessful()
            ->assertSee('Pastilha de freio dianteira')
            ->assertSee('4x')
            ->assertSee('Cofap');
    }

    public function test_shows_every_order_the_client_has_submitted(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $registeredClient = User::factory()->create(['role' => Role::Client]);

        $firstOrder = Order::factory()->for($registeredClient)->create();
        $firstOrder->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        $secondOrder = Order::factory()->for($registeredClient)->create();
        $secondOrder->items()->create(['description' => 'Correia dentada', 'quantity' => 1]);

        $link = $user->orderLinks()->create([
            'token' => 'abc123',
            'used_at' => now(),
            'registered_user_id' => $registeredClient->id,
        ]);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->assertSuccessful()
            ->assertSee('Pastilha de freio')
            ->assertSee('Correia dentada');
    }

    public function test_shows_the_order_level_comment_when_present(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $registeredClient = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($registeredClient)->create(['notes' => 'Entregar no período da tarde.']);
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        $link = $user->orderLinks()->create([
            'token' => 'abc123',
            'used_at' => now(),
            'registered_user_id' => $registeredClient->id,
        ]);

        Livewire::test(ViewOrderLink::class, ['orderLink' => $link->id])
            ->assertSuccessful()
            ->assertSee('Entregar no período da tarde.');
    }

    public function test_returns_404_for_a_link_that_belongs_to_another_user(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $link = $otherUser->orderLinks()->create(['token' => 'abc123']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->get(ViewOrderLink::getUrl(['orderLink' => $link->id], panel: 'super-admin'))
            ->assertNotFound();
    }
}
