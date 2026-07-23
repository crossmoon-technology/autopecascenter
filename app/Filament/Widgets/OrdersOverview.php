<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesOrdersByPeriod;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Widget do Painel de Controle — gráfico de barra de pedidos por status, com os cards
 * de contagem na mesma seção, todos reagindo ao mesmo filtro de período. Escopado do
 * mesmo jeito que App\Filament\Pages\Buscas\Orders: só pedidos de clientes convidados
 * pelo vendedor logado.
 *
 * Extends ChartWidget (em vez de montar o gráfico à mão) pra herdar de graça o
 * mecanismo de atualização reativa dele — o hook rendering()/updateChartData() já
 * recalcula o checksum e despacha os novos dados pro componente Alpine sempre que o
 * filtro muda, sem precisar reimplementar nada disso aqui.
 */
class OrdersOverview extends ChartWidget
{
    use ScopesOrdersByPeriod;

    protected string $view = 'filament.widgets.orders-overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * Sem lazy-load: é o primeiro conteúdo real do Painel de Controle, não faz sentido
     * esperar o usuário rolar a página (ou disparar um segundo request) pra aparecer.
     */
    protected static bool $isLazy = false;

    public ?string $filter = 'last_7_days';

    private ?Collection $periodOrders = null;

    public function getHeading(): string
    {
        return 'Pedidos';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return $this->periodOptions();
    }

    /**
     * barPercentage/categoryPercentage alone can't shrink both the bar width and the gap
     * between bars at once — for a single dataset, bar width is just
     * categoryWidth * categoryPercentage * barPercentage, so a smaller bar always eats
     * into the same fixed categoryWidth and leaves a *bigger* gap, never a smaller one.
     * Padding the plot area on the sides shrinks categoryWidth itself instead, so the
     * whole group of bars compresses together — narrower bars AND smaller gaps, with the
     * freed-up space pushed out to the chart's margins rather than between the bars.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'layout' => [
                'padding' => [
                    'left' => 60,
                    'right' => 60,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $orders = $this->currentPeriodOrders();
        $statuses = Status::cases();

        return [
            'datasets' => [
                [
                    'label' => 'Pedidos',
                    'data' => collect($statuses)->map(fn (Status $status): int => $orders->where('status', $status)->count())->all(),
                    'backgroundColor' => ['rgba(249, 70, 3, 0.75)', 'rgba(234, 88, 12, 0.75)', 'rgba(253, 186, 116, 0.75)', 'rgba(154, 52, 18, 0.75)'],
                    'barPercentage' => 0.6,
                    'categoryPercentage' => 0.98,
                ],
            ],
            'labels' => collect($statuses)->map(fn (Status $status): string => $status->getLabel())->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $orders = $this->currentPeriodOrders();

        return collect(Status::cases())
            ->mapWithKeys(fn (Status $status): array => [$status->value => $orders->where('status', $status)->count()])
            ->all();
    }

    public function totalCount(): int
    {
        return array_sum($this->statusCounts());
    }

    /**
     * Uma única query por render, reaproveitada pelo gráfico e pelos cards.
     *
     * @return Collection<int, Order>
     */
    private function currentPeriodOrders(): Collection
    {
        if ($this->periodOrders !== null) {
            return $this->periodOrders;
        }

        $period = array_key_exists($this->filter, $this->periodOptions()) ? $this->filter : 'last_7_days';

        return $this->periodOrders = $this->scopedOrders($period)->get();
    }
}
