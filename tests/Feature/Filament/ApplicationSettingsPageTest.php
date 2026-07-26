<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Configuracoes\ApplicationSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_configuracoes_navigation_group(): void
    {
        $this->assertSame('Configurações', ApplicationSettings::getNavigationGroup());
        $this->assertSame('Application', ApplicationSettings::getNavigationLabel());
    }

    public function test_page_renders_successfully(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)->assertSuccessful();
    }

    public function test_form_prefills_with_defaults_when_nothing_is_saved_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->assertSet('data.contact_phone', '(11) 99999-9999')
            ->assertSet('data.contact_email', 'contato@autopecascenter.com.br')
            ->assertSet('data.plan_basico_price', 'R$ 99')
            ->assertSet('data.plan_profissional_price', 'R$ 249')
            ->assertSet('data.plan_trial_price', 'Grátis');
    }

    public function test_plan_form_prefills_with_existing_saved_values(): void
    {
        Setting::set('plan_basico_price', 'R$ 129');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->assertSet('data.plan_basico_price', 'R$ 129');
    }

    public function test_save_persists_plan_fields_to_the_settings_table(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->fillForm([
                'plan_basico_price' => 'R$ 119',
                'plan_basico_price_period' => '/mês à vista',
                'plan_profissional_features' => "Recurso A\nRecurso B",
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('R$ 119', Setting::get('plan_basico_price'));
        $this->assertSame('/mês à vista', Setting::get('plan_basico_price_period'));
        $this->assertSame("Recurso A\nRecurso B", Setting::get('plan_profissional_features'));
    }

    public function test_form_prefills_with_existing_saved_values(): void
    {
        Setting::set('contact_phone', '(21) 98888-7777');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->assertSet('data.contact_phone', '(21) 98888-7777');
    }

    public function test_save_persists_every_field_to_the_settings_table(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->fillForm([
                'contact_phone' => '(11) 91234-5678',
                'contact_email' => 'vendas@autopecascenter.com.br',
                'contact_address' => 'Rua Nova, 100',
                'business_hours' => 'Todos os dias, 24h',
                'social_facebook' => 'https://facebook.com/autopecascenter',
                'social_instagram' => 'https://instagram.com/autopecascenter',
                'social_linkedin' => 'https://linkedin.com/company/autopecascenter',
                'social_youtube' => 'https://youtube.com/@autopecascenter',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('(11) 91234-5678', Setting::get('contact_phone'));
        $this->assertSame('vendas@autopecascenter.com.br', Setting::get('contact_email'));
        $this->assertSame('Rua Nova, 100', Setting::get('contact_address'));
        $this->assertSame('Todos os dias, 24h', Setting::get('business_hours'));
        $this->assertSame('https://facebook.com/autopecascenter', Setting::get('social_facebook'));
        $this->assertSame('https://instagram.com/autopecascenter', Setting::get('social_instagram'));
        $this->assertSame('https://linkedin.com/company/autopecascenter', Setting::get('social_linkedin'));
        $this->assertSame('https://youtube.com/@autopecascenter', Setting::get('social_youtube'));
    }

    public function test_social_fields_have_no_prefilled_placeholder_that_would_fail_url_validation(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->assertSet('data.social_facebook', null)
            ->assertSet('data.social_instagram', null);
    }

    public function test_save_works_when_social_fields_are_left_empty(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->fillForm(['contact_phone' => '(11) 90000-0000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('(11) 90000-0000', Setting::get('contact_phone'));
        $this->assertNull(Setting::get('social_facebook'));
    }

    public function test_is_not_accessible_from_the_seller_admin_panel(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $response = $this->get('/vendedor/'.ApplicationSettings::getSlug());

        $response->assertNotFound();
    }

    public function test_clearing_a_contact_field_persists_it_as_blank_instead_of_reverting_to_the_default(): void
    {
        Setting::set('contact_phone', '(11) 99999-0000');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ApplicationSettings::class)
            ->fillForm(['contact_phone' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(Setting::get('contact_phone'));

        // Reabrir a página não deve trazer de volta o texto padrão, já que o campo foi
        // limpo de propósito — Setting::get() só cai no default quando a linha nem existe.
        Livewire::test(ApplicationSettings::class)
            ->assertSet('data.contact_phone', null);
    }
}
