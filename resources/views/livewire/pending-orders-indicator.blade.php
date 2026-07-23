<div>
    <style>
        .poi-trigger {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: inherit;
            text-decoration: none;
        }
        .poi-trigger:hover {
            background: rgba(127, 127, 127, 0.1);
        }
        .poi-trigger svg {
            width: 1.375rem;
            height: 1.375rem;
        }
        .poi-badge {
            position: absolute;
            top: 0.0625rem;
            right: 0.0625rem;
            min-width: 1.125rem;
            height: 1.125rem;
            padding: 0 0.25rem;
            border-radius: 9999px;
            background: #F94603;
            color: #fff;
            font-size: 0.625rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
    </style>

    <a href="{{ $ordersUrl }}" class="poi-trigger" title="Pedidos não finalizados">
        <x-filament::icon icon="heroicon-o-inbox-stack" />
        @if ($pendingOrdersCount > 0)
            <span class="poi-badge">{{ $pendingOrdersCount }}</span>
        @endif
    </a>
</div>
