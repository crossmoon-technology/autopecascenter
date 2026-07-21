<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Configuracoes\ManufacturerPreferences;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManufacturerPreferencesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_configuracoes_navigation_group(): void
    {
        $this->assertSame('Configurações', ManufacturerPreferences::getNavigationGroup());
        $this->assertSame('Fabricantes habilitados', ManufacturerPreferences::getNavigationLabel());
    }

    public function test_page_renders_with_a_chip_per_active_manufacturer(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ManufacturerPreferences::class)
            ->assertSuccessful()
            ->assertSee($manufacturer->name);
    }

    public function test_inactive_manufacturers_are_not_shown_as_chips(): void
    {
        $inactive = Manufacturer::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ManufacturerPreferences::class)
            ->assertDontSee($inactive->name);
    }

    public function test_chips_start_unselected_when_the_user_has_no_saved_preference(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ManufacturerPreferences::class)
            ->assertSet("manufacturers.{$manufacturer->id}", false);
    }

    public function test_chips_start_selected_from_a_previously_saved_preference(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($manufacturer);

        $this->actingAs($user);

        Livewire::test(ManufacturerPreferences::class)
            ->assertSet("manufacturers.{$manufacturer->id}", true);
    }

    public function test_save_persists_the_selected_manufacturers_to_the_pivot_table(): void
    {
        $selected = Manufacturer::factory()->create(['is_active' => true]);
        $unselected = Manufacturer::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($user);

        Livewire::test(ManufacturerPreferences::class)
            ->set("manufacturers.{$selected->id}", true)
            ->set("manufacturers.{$unselected->id}", false)
            ->call('save')
            ->assertNotified();

        $this->assertSame(
            [$selected->id],
            $user->preferredManufacturers()->pluck('manufacturers.id')->all()
        );
    }

    public function test_save_removes_previously_saved_manufacturers_that_get_unchecked(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($manufacturer);

        $this->actingAs($user);

        Livewire::test(ManufacturerPreferences::class)
            ->set("manufacturers.{$manufacturer->id}", false)
            ->call('save');

        $this->assertSame(0, $user->preferredManufacturers()->count());
    }
}
