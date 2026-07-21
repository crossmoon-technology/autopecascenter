<?php

namespace App\Models;

use database\factories\ManufacturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
