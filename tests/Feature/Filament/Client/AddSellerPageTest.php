<?php

namespace Tests\Feature\Filament\Client;

use App\Enums\Role;
use App\Filament\Client\Pages\AddSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddSellerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_label_is_meus_vendedores(): void
    {
        $this->assertSame('Meus vendedores', AddSeller::getNavigationLabel());
    }

    public function test_shows_the_linked_sellers(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller, 'name' => 'Loja A']);
        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->assertSee('Loja A');
    }

    public function test_links_the_client_to_the_seller_matching_the_code(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->set('data.referral_code', $seller->referral_code)
            ->call('addSeller')
            ->assertNotified();

        $this->assertTrue($client->fresh()->isLinkedToSeller($seller));
    }

    public function test_rejects_an_invalid_code(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->set('data.referral_code', 'NAO-EXISTE')
            ->call('addSeller')
            ->assertNotified();

        $this->assertSame(0, $client->sellers()->count());
    }

    public function test_warns_instead_of_duplicating_an_existing_link(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->set('data.referral_code', $seller->referral_code)
            ->call('addSeller')
            ->assertNotified();

        $this->assertSame(1, $client->sellers()->count());
    }

    public function test_adds_a_second_seller_to_a_client_that_already_has_one(): void
    {
        $firstSeller = User::factory()->create(['role' => Role::Seller]);
        $secondSeller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($firstSeller)->create();
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->set('data.referral_code', $secondSeller->referral_code)
            ->call('addSeller')
            ->assertNotified();

        $this->assertTrue($client->fresh()->isLinkedToSeller($firstSeller));
        $this->assertTrue($client->fresh()->isLinkedToSeller($secondSeller));
    }

    public function test_removes_a_seller_when_the_client_has_more_than_one(): void
    {
        $firstSeller = User::factory()->create(['role' => Role::Seller]);
        $secondSeller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($firstSeller)->create();
        $client->linkToSeller($secondSeller);
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->call('removeSeller', $firstSeller->id)
            ->assertNotified();

        $this->assertFalse($client->fresh()->isLinkedToSeller($firstSeller));
        $this->assertTrue($client->fresh()->isLinkedToSeller($secondSeller));
    }

    /**
     * Sem isso, um cliente que zera os vendedores perde a capacidade de criar pedidos e
     * não tem mais como voltar a vincular um (o campo de vendedor some com 0 opções).
     */
    public function test_refuses_to_remove_the_only_remaining_seller(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->call('removeSeller', $seller->id)
            ->assertNotified();

        $this->assertTrue($client->fresh()->isLinkedToSeller($seller));
    }

    public function test_removing_an_unrelated_seller_id_does_nothing(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($seller)->create();
        $unrelatedSeller = User::factory()->create(['role' => Role::Seller]);
        $this->actingAs($client);

        Livewire::test(AddSeller::class)
            ->call('removeSeller', $unrelatedSeller->id);

        $this->assertTrue($client->fresh()->isLinkedToSeller($seller));
    }
}
