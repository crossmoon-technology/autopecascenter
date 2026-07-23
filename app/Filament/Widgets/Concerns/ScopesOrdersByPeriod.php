<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Compartilhado pelos widgets de pedidos do Painel de Controle (cards + gráfico) — os
 * dois precisam do mesmo recorte de período (hoje/últimos 7 dias/últimos 31 dias/todos)
 * e do mesmo escopo por vendedor usado em App\Filament\Pages\Buscas\Orders.
 */
trait ScopesOrdersByPeriod
{
    /**
     * @return array<string, string>
     */
    protected function periodOptions(): array
    {
        return [
            'day' => 'Hoje',
            'last_7_days' => 'Últimos 7 dias',
            'last_31_days' => 'Últimos 31 dias',
            'all' => 'Todos',
        ];
    }

    protected function scopedOrders(string $period): Builder
    {
        $query = Order::query()
            ->whereHas('user', fn (Builder $query) => $query->where('invited_by_id', Auth::id()));

        [$start, $end] = $this->periodRange($period);

        if ($start !== null) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'last_7_days' => [$now->copy()->subDays(7), $now->copy()],
            'last_31_days' => [$now->copy()->subDays(31), $now->copy()],
            'all' => [null, null],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
