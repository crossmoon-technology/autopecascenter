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

    public function test_creates_a_part_with_conversoes_and_atributos_json_scoped_to_a_catalog(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create();

        Livewire::test(CreatePart::class)
            ->fillForm([
                'catalog_id' => $catalog->getKey(),
                'codigo' => '16088',
                'atributos' => json_encode([
                    'descricao' => 'MOLA A GÁS',
                    'posicao' => 'PORTA MALAS',
                ]),
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
        $this->assertSame('MOLA A GÁS', $part->atributos['descricao']);
        $this->assertSame('PORTA MALAS', $part->atributos['posicao']);
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

    public function test_rejects_invalid_json_in_atributos(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create();

        Livewire::test(CreatePart::class)
            ->fillForm([
                'catalog_id' => $catalog->getKey(),
                'codigo' => '16088',
                'atributos' => '{not valid json',
            ])
            ->call('create')
            ->assertHasFormErrors(['atributos']);
    }

    public function test_edit_form_shows_conversoes_and_atributos_as_pretty_printed_json(): void
    {
        $part = Part::factory()->create([
            'conversoes' => ['MONROE' => ['GS440']],
            'atributos' => ['descricao' => 'MOLA A GÁS'],
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditPart::class, ['record' => $part->getKey()])
            ->assertFormSet([
                'conversoes' => json_encode(['MONROE' => ['GS440']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'atributos' => json_encode(['descricao' => 'MOLA A GÁS'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
    }
}
