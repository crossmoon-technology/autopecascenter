<?php

namespace App\Filament\Pages\Buscas\Concerns;

use App\Models\SearchHistory\Enums\Method;
use Illuminate\Support\Facades\Auth;

trait RecordsSearchHistory
{
    /**
     * $found_results fica null quando o método ainda não sabe informar isso no momento
     * do registro (ex: a API responde de forma assíncrona, depois que o histórico já
     * foi gravado).
     */
    protected function recordSearchHistory(string $query, Method $method, ?bool $found_results = null): void
    {
        $user = Auth::user();

        $user->searchHistory()->create([
            'query' => $query,
            'method' => $method,
            'found_results' => $found_results,
        ]);

        $idsToKeep = $user->searchHistory()->orderByDesc('created_at')->limit(100)->pluck('id');

        $user->searchHistory()->whereNotIn('id', $idsToKeep)->delete();
    }
}
