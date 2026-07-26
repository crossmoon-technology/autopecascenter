<?php

namespace Tests\Feature\Livewire;

use App\Enums\Role;
use App\Filament\Client\Pages\CreateOrder;
use App\Filament\Client\Pages\Manufacturers;
use App\Filament\Client\Pages\OrderHistory;
use App\Filament\Pages\Buscas\Api;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\Iframes;
use App\Filament\Pages\Buscas\Orders;
use App\Livewire\OnboardingTutorial;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_visible_for_a_user_who_has_not_completed_it_yet(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => Role::SuperAdmin,
            'tutorial_completed_at' => null,
            'lgpd_accepted_at' => now(),
        ]));

        Livewire::test(OnboardingTutorial::class)
            ->assertSuccessful()
            ->assertSee('Oi, seja bem-vindo!');
    }

    public function test_is_not_visible_before_lgpd_consent_even_if_the_tutorial_is_pending(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => Role::SuperAdmin,
            'tutorial_completed_at' => null,
            'lgpd_accepted_at' => null,
        ]));

        $this->assertFalse(Livewire::test(OnboardingTutorial::class)->instance()->visible);
    }

    public function test_is_not_visible_for_a_user_who_already_completed_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin, 'tutorial_completed_at' => now()]));

        $component = Livewire::test(OnboardingTutorial::class)->assertSuccessful();

        // O JS ainda recebe os steps mesmo aqui (ver render()) — precisa deles pra poder
        // retomar um tour reiniciado manualmente que exigiu navegar de página (ver
        // App\Livewire\HelpMenu e hasActiveTourState em onboarding-tour.js) — então o
        // sinal real de "não mostrar automaticamente" é needsTutorial: false, não a
        // ausência do texto no HTML.
        $this->assertFalse($component->instance()->visible);
        $component->assertSeeHtml('needsTutorial: false');
    }

    public function test_shows_seller_specific_content_for_admin_and_super_admin(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => Role::Seller,
            'tutorial_completed_at' => null,
            'lgpd_accepted_at' => now(),
        ]));

        Livewire::test(OnboardingTutorial::class)
            ->assertSuccessful()
            ->assertSee('Seus clientes ficam aqui');
    }

    public function test_shows_client_specific_content(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => Role::Client,
            'tutorial_completed_at' => null,
            'lgpd_accepted_at' => now(),
        ]));

        Livewire::test(OnboardingTutorial::class)
            ->assertSuccessful()
            ->assertSee('Esses são os fabricantes disponíveis')
            ->assertDontSee('Seus clientes ficam aqui');
    }

    public function test_seller_steps_are_a_linear_tour_ending_at_the_create_order_button(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin, 'tutorial_completed_at' => null]));

        $data = Livewire::test(OnboardingTutorial::class)->instance()->steps();

        $this->assertSame('linear', $data['type']);
        $this->assertNotEmpty($data['steps']);

        $lastStep = end($data['steps']);
        $this->assertSame('#onboarding-target-create-order', $lastStep['selector']);
        $this->assertSame(Orders::getUrl(panel: 'super-admin'), $lastStep['url']);

        foreach ($data['steps'] as $step) {
            $this->assertNotEmpty($step['selector']);
            $this->assertNotEmpty($step['url']);
            $this->assertNotEmpty($step['title']);
            $this->assertNotEmpty($step['description']);
        }
    }

    public function test_seller_steps_highlight_each_of_the_3_search_menus_separately(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Seller, 'tutorial_completed_at' => null]));

        $data = Livewire::test(OnboardingTutorial::class)->instance()->steps();
        $selectors = array_column($data['steps'], 'selector');

        $this->assertContains('a[href="'.Iframes::getUrl(panel: 'admin').'"]', $selectors);
        $this->assertContains('a[href="'.CatalogDatabaseSearch::getUrl(panel: 'admin').'"]', $selectors);
        $this->assertContains('a[href="'.Api::getUrl(panel: 'admin').'"]', $selectors);
    }

    public function test_client_flow_is_a_guided_order_ending_at_create_order_and_history(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client, 'tutorial_completed_at' => null]));

        $flow = Livewire::test(OnboardingTutorial::class)->instance()->steps();

        $this->assertSame('guided-order', $flow['type']);
        $this->assertSame(Manufacturers::getUrl(panel: 'client'), $flow['urls']['manufacturers']);
        $this->assertSame(CreateOrder::getUrl(panel: 'client'), $flow['urls']['createOrder']);
        $this->assertSame(OrderHistory::getUrl(panel: 'client'), $flow['urls']['history']);

        // O intro aponta pro link de Fabricantes — é pra lá que o Próximo do intro
        // realmente navega (ver clientFlow()), não pro de Criar pedido.
        $this->assertSame('a[href="'.Manufacturers::getUrl(panel: 'client').'"]', $flow['intro']['selector']);

        $this->assertSame('.cm-manufacturers-grid', $flow['manufacturersStep']['selector']);
        $this->assertSame(Manufacturers::getUrl(panel: 'client'), $flow['manufacturersStep']['url']);

        // Passo intermediário na própria página de Fabricantes, que aponta pro link de
        // Criar pedido antes de navegar pra lá (mesma lógica do intro acima).
        $this->assertSame('a[href="'.CreateOrder::getUrl(panel: 'client').'"]', $flow['goToCreateOrderStep']['selector']);

        $this->assertSame('.mo-submit', $flow['createStep']['selector']);
        $this->assertSame(CreateOrder::getUrl(panel: 'client'), $flow['createStep']['url']);

        $this->assertSame(
            ['.onboarding-target-description', '.onboarding-target-quantity', '.onboarding-target-manufacturers'],
            array_column($flow['createFieldSteps'], 'selector'),
        );

        // O select de fabricantes abre um dropdown pra baixo — o passo precisa forçar o
        // card do tutorial pra cima do campo, senão ele fica em cima das opções.
        $manufacturersFieldStep = $flow['createFieldSteps'][2];
        $this->assertSame('top', $manufacturersFieldStep['side']);

        foreach ($flow['createFieldSteps'] as $step) {
            $this->assertNotEmpty($step['title']);
            $this->assertNotEmpty($step['description']);
        }

        foreach (['intro', 'manufacturersStep', 'goToCreateOrderStep', 'createStep', 'verifyStep', 'cancelStep', 'doneStep'] as $key) {
            $this->assertNotEmpty($flow[$key]['title']);
            $this->assertNotEmpty($flow[$key]['description']);
        }
    }

    public function test_finish_marks_the_tutorial_completed_and_hides_it(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin, 'tutorial_completed_at' => null]);
        $this->actingAs($user);

        $component = Livewire::test(OnboardingTutorial::class)->call('finish');

        // Ver o comentário em test_is_not_visible_for_a_user_who_already_completed_it —
        // o texto continua no HTML (dentro do JSON dos steps), então o sinal real é
        // needsTutorial: false / $visible, não a ausência do texto.
        $this->assertFalse($component->instance()->visible);
        $component->assertSeeHtml('needsTutorial: false');

        $this->assertNotNull($user->fresh()->tutorial_completed_at);
    }

    public function test_finish_deletes_the_given_order_when_it_belongs_to_the_user(): void
    {
        $user = User::factory()->create(['role' => Role::Client, 'tutorial_completed_at' => null]);
        $this->actingAs($user);
        $order = Order::factory()->for($user)->create();

        Livewire::test(OnboardingTutorial::class)->call('finish', $order->id);

        $this->assertModelMissing($order);
        $this->assertNotNull($user->fresh()->tutorial_completed_at);
    }

    public function test_finish_does_not_delete_an_order_belonging_to_another_user(): void
    {
        $otherUser = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($otherUser)->create();

        $user = User::factory()->create(['role' => Role::Client, 'tutorial_completed_at' => null]);
        $this->actingAs($user);

        Livewire::test(OnboardingTutorial::class)->call('finish', $order->id);

        $this->assertModelExists($order);
    }

    public function test_finish_without_an_order_id_just_completes_the_tutorial(): void
    {
        $user = User::factory()->create(['role' => Role::Client, 'tutorial_completed_at' => null]);
        $this->actingAs($user);

        Livewire::test(OnboardingTutorial::class)->call('finish');

        $this->assertNotNull($user->fresh()->tutorial_completed_at);
    }

    public function test_restart_redispatches_the_current_steps_even_after_completion(): void
    {
        $user = User::factory()->create(['role' => Role::Client, 'tutorial_completed_at' => now()]);
        $this->actingAs($user);

        Livewire::test(OnboardingTutorial::class)
            ->call('restart')
            ->assertDispatched('onboarding-tour:restart', function (string $name, array $params): bool {
                return $params['steps']['type'] === 'guided-order';
            });
    }

    public function test_restart_listens_for_the_help_menus_event(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin, 'tutorial_completed_at' => now()]);
        $this->actingAs($user);

        Livewire::test(OnboardingTutorial::class)
            ->dispatch('restart-onboarding-tour')
            ->assertDispatched('onboarding-tour:restart');
    }

    public function test_starts_the_tour_after_lgpd_consent_when_the_tutorial_is_still_pending(): void
    {
        $user = User::factory()->create([
            'role' => Role::Client,
            'tutorial_completed_at' => null,
            'lgpd_accepted_at' => null,
        ]);
        $this->actingAs($user);

        Livewire::test(OnboardingTutorial::class)
            ->dispatch('lgpd-accepted')
            ->assertDispatched('onboarding-tour:restart');
    }

    public function test_does_not_start_the_tour_after_lgpd_consent_when_already_completed(): void
    {
        $user = User::factory()->create([
            'role' => Role::Client,
            'tutorial_completed_at' => now(),
            'lgpd_accepted_at' => null,
        ]);
        $this->actingAs($user);

        Livewire::test(OnboardingTutorial::class)
            ->dispatch('lgpd-accepted')
            ->assertNotDispatched('onboarding-tour:restart');
    }
}
