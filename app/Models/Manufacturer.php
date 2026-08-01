<?php

namespace App\Models;

use database\factories\ManufacturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'logo', 'icon', 'external_link', 'iframe_url', 'is_active'])]
class Manufacturer extends Model
{
    /** @use HasFactory<ManufacturerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function catalogs(): HasMany
    {
        return $this->hasMany(Catalog::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Manufacturer $manufacturer): void {
            // Arquivo só é removido do disco na exclusão permanente — o soft delete
            // precisa manter o arquivo, já que o fabricante pode ser restaurado depois.
            if ($manufacturer->isForceDeleting()) {
                Storage::disk('public')->delete(array_filter([$manufacturer->logo, $manufacturer->icon]));
            }
        });
    }
}
