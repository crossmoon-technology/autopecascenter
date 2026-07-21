<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Configuracoes\GeneralSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_configuracoes_navigation_group(): void
    {
        $this->assertSame('Configurações', GeneralSettings::getNavigationGroup());
        $this->assertSame('Geral', GeneralSettings::getNavigationLabel());
    }

    public function test_page_renders_successfully(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(GeneralSettings::class)->assertSuccessful();
    }

    public function test_form_prefills_with_the_users_existing_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('users/logos/existing.png', 'fake-contents');
        $user = User::factory()->create(['role' => Role::SuperAdmin, 'logo' => 'users/logos/existing.png']);
        $this->actingAs($user);

        Livewire::test(GeneralSettings::class)
            ->assertSet('data.logo', fn ($value): bool => in_array('users/logos/existing.png', (array) $value, true));
    }

    public function test_save_persists_the_uploaded_logo_to_the_user(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(GeneralSettings::class)
            ->fillForm(['logo' => UploadedFile::fake()->image('logo.png', 100, 100)->size(500)])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $path = $user->fresh()->logo;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_save_rejects_an_unsupported_logo_type(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(GeneralSettings::class)
            ->fillForm(['logo' => UploadedFile::fake()->image('logo.jpg', 100, 100)->size(500)])
            ->call('save')
            ->assertHasFormErrors(['logo']);
    }

    public function test_save_rejects_an_oversized_logo(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(GeneralSettings::class)
            ->fillForm(['logo' => UploadedFile::fake()->create('logo.png', 3000, 'image/png')])
            ->call('save')
            ->assertHasFormErrors(['logo']);
    }
}
