<?php

namespace App\Models;

use App\Models\SearchHistory\Enums\Method;
use database\factories\SearchHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'query', 'method', 'found_results'])]
class SearchHistory extends Model
{
    /** @use HasFactory<SearchHistoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'method' => Method::class,
            'found_results' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
