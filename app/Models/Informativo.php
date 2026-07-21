<?php

namespace App\Models;

use App\Models\Informativo\Enums\InformativoType;
use database\factories\InformativoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_id', 'file', 'original_name', 'type'])]
class Informativo extends Model
{
    /** @use HasFactory<InformativoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => InformativoType::class,
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }
}
