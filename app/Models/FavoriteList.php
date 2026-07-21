<?php

namespace App\Models;

use database\factories\FavoriteListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name', 'is_default'])]
class FavoriteList extends Model
{
    /** @use HasFactory<FavoriteListFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'favorite_list_part')->withTimestamps()->withPivot('note');
    }

    /**
     * A lista padrão é o fallback de toda peça favoritada sem lista escolhida — apagar
     * ela deixaria peças "órfãs" e quebraria o toggle rápido de favoritar, então nunca
     * é permitido.
     */
    public function isDeletable(): bool
    {
        return ! $this->is_default;
    }
}
