<?php

namespace App\Models;

use App\Models\Catalog\Enums\ImportStatus;
use database\factories\CatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

// update_file e source_version ficam fora do Fillable de propósito: só os jobs/
// comandos de import (ImportCatalogPartsUpdate, ScrapeCatalogs) devem escrevê-los,
// via forceFill(), nunca por preenchimento em massa vindo de um form do painel.
// scraper_slug é a exceção: é escolhido pelo próprio admin no form (ver
// CatalogForm) — identifica a entrada de config/scrapers.php usada pelo
// ScrapeCatalogs para reimportar esse catálogo automaticamente.
#[Fillable(['manufacturer_id', 'name', 'slug', 'descricao', 'file', 'extracted_at', 'is_active', 'scraper_slug'])]
class Catalog extends Model
{
    /** @use HasFactory<CatalogFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'extracted_at' => 'date',
            'is_active' => 'boolean',
            'import_status' => ImportStatus::class,
        ];
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Catalog $catalog): void {
            // Arquivo só é removido do disco na exclusão permanente — o soft delete
            // precisa manter o arquivo, já que o catálogo pode ser restaurado depois.
            if ($catalog->isForceDeleting()) {
                Storage::disk('local')->delete(array_filter([$catalog->file, $catalog->update_file]));

                return;
            }

            // O force delete já é coberto pelo cascadeOnDelete() da FK no banco; aqui só
            // cuidamos do soft delete, que é uma UPDATE e não dispara aquele cascade.
            $catalog->parts()->delete();
        });
    }
}
