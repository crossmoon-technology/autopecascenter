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

#[Fillable(['manufacturer_id', 'name', 'descricao', 'file', 'extracted_at', 'is_active'])]
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

    public function informativos(): HasMany
    {
        return $this->hasMany(Informativo::class);
    }

    protected static function booted(): void
    {
        // O force delete já é coberto pelo cascadeOnDelete() da FK no banco; aqui só
        // cuidamos do soft delete, que é uma UPDATE e não dispara aquele cascade.
        static::deleting(function (Catalog $catalog): void {
            if (! $catalog->isForceDeleting()) {
                $catalog->parts()->delete();
            }
        });
    }
}
