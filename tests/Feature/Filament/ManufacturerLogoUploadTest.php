<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Manufacturers\Pages\CreateManufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ManufacturerLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_png_logo(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Cofap',
                'cnpj' => '12345678000199',
                'logo' => UploadedFile::fake()->image('logo.png', 100, 100)->size(500),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_rejects_a_non_png_logo(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Bosch',
                'cnpj' => '98765432000188',
                'logo' => UploadedFile::fake()->image('logo.jpg', 100, 100)->size(500),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['logo']);
    }

    public function test_rejects_an_oversized_logo(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Nakata',
                'cnpj' => '11122233000144',
                'logo' => UploadedFile::fake()->create('logo.png', 3000, 'image/png'),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['logo']);
    }
}
