<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Sellers\Pages\EditSeller;
use App\Filament\Resources\Sellers\Pages\ListSellers;
use App\Filament\Resources\Sellers\SellerResource;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SellerResource só existe no painel super-admin (ver SuperAdminPanelProvider) —
        // sem isso, getUrl() dos row actions da tabela resolve pro painel "admin" (o
        // primeiro registrado em bootstrap/providers.php, usado como default em testes
        // sem uma request HTTP real passando pelo middleware do painel).
        Filament::setCurrentPanel('super-admin');
    }

    public function test_lists_only_seller_role_accounts(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $client = User::factory()->create(['role' => Role::Client]);
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListSellers::class)
            ->assertCanSeeTableRecords([$seller])
            ->assertCanNotSeeTableRecords([$client, $superAdmin]);
    }

    public function test_is_hidden_from_a_seller_and_a_client(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());
        $this->assertFalse(SellerResource::canViewAny());

        $this->actingAs(User::factory()->create(['role' => Role::Client]));
        $this->assertFalse(SellerResource::canViewAny());
    }

    public function test_approve_payment_action_is_only_visible_for_sellers_with_a_pending_payment(): void
    {
        $noPlanYet = User::factory()->seller()->create();
        $pendingPayment = User::factory()->inTrial()->create();
        $approved = User::factory()->approvedSeller()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListSellers::class)
            ->assertTableActionHidden('approvePayment', $noPlanYet)
            ->assertTableActionVisible('approvePayment', $pendingPayment)
            ->assertTableActionHidden('approvePayment', $approved);
    }

    public function test_approve_payment_action_clears_the_pending_state(): void
    {
        $pendingPayment = User::factory()->inTrial()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListSellers::class)->callTableAction('approvePayment', $pendingPayment);

        $this->assertFalse($pendingPayment->fresh()->isPaymentPending());
    }

    public function test_approve_payment_action_is_visible_again_once_the_subscription_expires(): void
    {
        $expiredSubscription = User::factory()->approvedSeller()->create([
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->subDay(),
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListSellers::class)
            ->assertTableActionVisible('approvePayment', $expiredSubscription)
            ->callTableAction('approvePayment', $expiredSubscription);

        $this->assertFalse($expiredSubscription->fresh()->isSubscriptionExpired());
        $this->assertTrue($expiredSubscription->fresh()->hasActiveSellerAccess());
    }

    public function test_can_change_the_plan_and_clear_the_trial_end_date(): void
    {
        $seller = User::factory()->approvedSeller(Plan::Profissional)->create(['trial_ends_at' => now()->addDays(3)]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditSeller::class, ['record' => $seller->getKey()])
            ->fillForm([
                'plan' => Plan::Basico->value,
                'trial_ends_at' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $seller->refresh();
        $this->assertSame(Plan::Basico, $seller->plan);
        $this->assertNull($seller->trial_ends_at);
    }

    public function test_can_manually_extend_the_subscription_end_date(): void
    {
        $seller = User::factory()->approvedSeller(Plan::Profissional)->create(['subscription_ends_at' => now()->subDay()]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditSeller::class, ['record' => $seller->getKey()])
            ->fillForm(['subscription_ends_at' => '2027-01-15 10:00:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $seller->refresh();
        $this->assertFalse($seller->isSubscriptionExpired());
        $this->assertSame('2027-01-15 10:00:00', $seller->subscription_ends_at->format('Y-m-d H:i:s'));
    }
}
