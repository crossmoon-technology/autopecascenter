<?php

namespace App\Models;

use App\Models\Quotation\Enums\Status;
use App\Models\QuotationItem\Enums\Source;
use database\factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'status'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function displayName(): string
    {
        return $this->name ?: "Cotação #{$this->id}";
    }

    /**
     * Item de um resultado real da Base de dados — tem um Part estável pra linkar de
     * volta pra página dedicada da peça. firstOrCreate evita duplicar a mesma peça se o
     * usuário clicar "adicionar" mais de uma vez sem querer.
     */
    public function addPart(Part $part): QuotationItem
    {
        $item = $this->items()->firstOrCreate(
            ['source' => Source::Database, 'part_id' => $part->id],
            ['manufacturer_id' => $part->catalog->manufacturer_id, 'codigo' => $part->codigo],
        );

        $this->touch();

        return $item;
    }

    /**
     * Item vindo da API (DTO de scraping) ou digitado manualmente no Iframe — nenhum dos
     * dois tem um registro estável em `parts`, só o código e (quando disponível) uma
     * descrição soltos.
     */
    public function addExternalItem(Source $source, int $manufacturer_id, string $codigo, ?string $descricao = null): QuotationItem
    {
        $item = $this->items()->firstOrCreate(
            ['source' => $source, 'manufacturer_id' => $manufacturer_id, 'codigo' => $codigo, 'part_id' => null],
            ['descricao' => $descricao],
        );

        $this->touch();

        return $item;
    }

    public function close(?string $name = null): void
    {
        $this->update([
            'status' => Status::Closed,
            'name' => filled($name) ? $name : $this->displayName(),
        ]);
    }

    public function reopen(): void
    {
        $this->update(['status' => Status::Open]);
    }
}
