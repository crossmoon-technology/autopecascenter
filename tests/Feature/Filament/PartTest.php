<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Parts\Pages\CreatePart;
use App\Filament\Resources\Parts\Pages\EditPart;
use App\Models\Catalog;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_part_with_conversoes_json_scoped_to_a_catalog(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create();

        Livewire::test(CreatePart::class)
            ->fillForm([
                'catalog_id' => $catalog->getKey(),
                'codigo' => '16088',
                'descricao' => 'MOLA A GÁS',
                'tipo' => 'MOLA A GÁS',
                'posicao' => 'PORTA MALAS',
                'categoria' => 'MOLASAGÅS',
                'conversoes' => json_encode([
                    'MONROE' => ['GS440'],
                    'NAKATA' => ['MG 16214', 'MG 16215'],
                ]),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $part = Part::firstOrFail();
        $this->assertSame($catalog->getKey(), $part->catalog_id);
        $this->assertSame(['GS440'], $part->conversoes['MONROE']);
        $this->assertSame(['MG 16214', 'MG 16215'], $part->conversoes['NAKATA']);
    }

    public function test_rejects_invalid_json_in_conversoes(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create();

        Livewire::test(CreatePart::class)
            ->fillForm([
                'catalog_id' => $catalog->getKey(),
                'codigo' => '16088',
                'conversoes' => '{not valid json',
            ])
            ->call('create')
            ->assertHasFormErrors(['conversoes']);
    }

    public function test_edit_form_shows_conversoes_as_pretty_printed_json(): void
    {
        $part = Part::factory()->create([
            'conversoes' => ['MONROE' => ['GS440']],
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditPart::class, ['record' => $part->getKey()])
            ->assertFormSet([
                'conversoes' => json_encode(['MONROE' => ['GS440']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
    }
}
