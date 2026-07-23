@php
    use Filament\Support\Facades\FilamentAsset;

    $type = $this->getType();
    $filters = $this->getFilters();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section>
        <x-slot name="heading">Pedidos</x-slot>

        @if ($filters)
            <x-slot name="afterHeader">
                <x-filament::input.wrapper inline-prefix wire:target="filter" class="fi-wi-chart-filter">
                    <x-filament::input.select inline-prefix wire:model.live="filter">
                        @foreach ($filters as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-slot>
        @endif

        <style>
            .od-chart-frame {
                height: 18rem;
                margin-bottom: 1.75rem;
            }
            .od-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
                gap: 0.75rem;
            }
            .od-stat-card {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
                padding: 0.875rem 1rem;
                border-radius: 0.625rem;
                border: 1px solid rgba(127, 127, 127, 0.2);
            }
            .od-stat-value {
                font-size: 1.5rem;
                font-weight: 700;
                line-height: 1.1;
            }
            .od-stat-label {
                font-size: 0.75rem;
                opacity: 0.65;
            }
            .od-stat-pending .od-stat-value { color: rgb(249 70 3); }
            .od-stat-processing .od-stat-value { color: rgb(154 52 18); }
            .od-stat-finished .od-stat-value { color: rgb(234 88 12); }
            .od-stat-cancelled .od-stat-value { color: rgb(124 45 18); }
            .dark .od-stat-pending .od-stat-value { color: rgb(251 138 92); }
            .dark .od-stat-processing .od-stat-value { color: rgb(251 146 60); }
            .dark .od-stat-finished .od-stat-value { color: rgb(255 237 213); }
            .dark .od-stat-cancelled .od-stat-value { color: rgb(253 186 116); }
            .od-chart-frame .fi-wi-chart-grid-color {
                color: rgba(0, 0, 0, 0.06);
            }
            .dark .od-chart-frame .fi-wi-chart-grid-color {
                color: rgba(255, 255, 255, 0.08);
            }
        </style>

        {{-- Gráfico: mesma marcação/mecanismo do filament-widgets::chart-widget original,
             só embutida aqui pra ficar no mesmo container dos cards e da tabela abaixo.
             O hook rendering()/updateChartData() do ChartWidget continua cuidando de
             despachar os dados novos pro componente Alpine quando o filtro muda. --}}
        <div
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
            wire:ignore
            data-chart-type="{{ $type }}"
            x-data="chart({
                        cachedData: @js($this->getCachedData()),
                        options: @js($this->getOptions()),
                        type: @js($type),
                    })"
            class="fi-wi-chart-frame fi-wi-chart-frame-no-aspect-ratio fi-wi-chart-canvas-ctn od-chart-frame"
        >
            <canvas x-ref="canvas" style="width: 100%; height: 100%; max-height: 100%"></canvas>

            <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
            <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
            <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
            <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
        </div>

        @php
            $counts = $this->statusCounts();
            $statuses = \App\Models\Order\Enums\Status::cases();
        @endphp

        <div class="od-stats-grid">
            <div class="od-stat-card">
                <span class="od-stat-value">{{ $this->totalCount() }}</span>
                <span class="od-stat-label">Total</span>
            </div>
            @foreach ($statuses as $status)
                <div class="od-stat-card od-stat-{{ $status->value }}">
                    <span class="od-stat-value">{{ $counts[$status->value] }}</span>
                    <span class="od-stat-label">{{ $status->getLabel() }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
