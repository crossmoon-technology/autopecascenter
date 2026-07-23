<?php

namespace App\Console\Commands;

use App\Models\SearchHistory;
use Illuminate\Console\Command;

class PruneOldSearchHistory extends Command
{
    protected $signature = 'search-history:prune';

    protected $description = 'Remove registros de histórico de busca com mais de um mês';

    public function handle(): void
    {
        $deleted = SearchHistory::query()
            ->where('created_at', '<', now()->subMonth())
            ->delete();

        $this->info("{$deleted} registro(s) de histórico removido(s).");
    }
}
