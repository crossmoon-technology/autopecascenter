<?php

namespace App\Models;

use database\factories\CatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['manufacturer_id', 'name', 'file', 'extracted_at', 'is_active'])]
class Catalog extends Model
{
    /** @use HasFactory<CatalogFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'extracted_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
