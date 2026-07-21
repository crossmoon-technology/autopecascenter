@extends('layouts.app')

@section('content')
    <style>
        body {
            background: #08090b;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            color: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .pp-wrap {
            width: 100%;
            max-width: 640px;
        }
        .pp-brand {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .pp-brand-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
            background: #f94603;
            color: #fff;
            font-weight: 800;
            font-size: 0.8125rem;
        }
        .pp-brand-name {
            color: #fff;
            font-weight: 700;
            font-size: 0.9375rem;
        }
        .pp-brand-name span {
            color: #f94603;
        }
        .pp-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }
        .pp-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .pp-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .pp-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 4rem;
            height: 4rem;
            flex-shrink: 0;
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #fff;
            padding: 0.5rem;
            overflow: hidden;
        }
        .pp-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .pp-logo-placeholder {
            background: rgba(0, 0, 0, 0.06);
        }
        .pp-codigo {
            font-size: 1.5rem;
            font-weight: 800;
        }
        .pp-meta {
            font-size: 0.875rem;
            opacity: 0.6;
            margin-top: 0.125rem;
        }
        .pp-qr {
            flex-shrink: 0;
            padding: 0.5rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .pp-qr svg {
            display: block;
            width: 84px;
            height: 84px;
        }
        .pp-section {
            margin-top: 1.75rem;
        }
        .pp-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            opacity: 0.55;
            margin-bottom: 0.625rem;
        }
        .pp-attributes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
        }
        .pp-attribute {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .pp-attribute-label {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            opacity: 0.5;
            font-weight: 600;
        }
        .pp-attribute-value {
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 0.125rem;
        }
        .pp-equivalences {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .pp-equivalence {
            font-size: 0.875rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .pp-equivalence strong {
            font-weight: 700;
        }
        .pp-footer {
            margin-top: 2rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .pp-copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 0.875rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.15);
            background: transparent;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            color: inherit;
        }
        .pp-copy-btn:hover {
            border-color: #f94603;
        }
        .pp-copied {
            font-size: 0.75rem;
            font-weight: 600;
            color: #15803d;
        }
        .pp-cta {
            font-size: 0.8125rem;
            color: #f94603;
            font-weight: 600;
            text-decoration: none;
        }
        .pp-cta:hover {
            text-decoration: underline;
        }
    </style>

    <div class="pp-wrap">
        <div class="pp-brand">
            <span class="pp-brand-mark">AC</span>
            <span class="pp-brand-name">Auto Peças <span>Center</span></span>
        </div>

        <div class="pp-card">
            <div class="pp-header">
                <div class="pp-header-info">
                    @php $logo = $part->catalog->manufacturer->icon ?? $part->catalog->manufacturer->logo; @endphp

                    @if ($logo)
                        <span class="pp-logo">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="">
                        </span>
                    @else
                        <span class="pp-logo pp-logo-placeholder"></span>
                    @endif

                    <div>
                        <div class="pp-codigo">{{ $part->codigo }}</div>
                        <div class="pp-meta">{{ $part->catalog->manufacturer->name }} &middot; {{ $part->catalog->name }}</div>
                    </div>
                </div>

                <span class="pp-qr">{!! $qrCode !!}</span>
            </div>

            @if (! empty($part->atributos))
                <div class="pp-section">
                    <div class="pp-section-title">Atributos</div>
                    <div class="pp-attributes">
                        @foreach ($part->atributos as $chave => $valor)
                            <div class="pp-attribute">
                                <div class="pp-attribute-label">{{ $chave }}</div>
                                <div class="pp-attribute-value">{{ is_array($valor) ? implode(', ', $valor) : $valor }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! empty($part->conversoes))
                <div class="pp-section">
                    <div class="pp-section-title">Equivalências</div>
                    <div class="pp-equivalences">
                        @foreach ($part->conversoes as $marca => $codigos)
                            <div class="pp-equivalence">
                                <strong>{{ $marca }}:</strong> {{ is_array($codigos) ? implode(', ', $codigos) : $codigos }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="pp-footer">
                <button type="button" id="pp-copy-btn" class="pp-copy-btn">Copiar link</button>
                <span id="pp-copied" class="pp-copied" style="display: none;">Link copiado!</span>
                <a href="{{ route('login') }}" class="pp-cta">Entrar no Auto Peças Center &rarr;</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('pp-copy-btn').addEventListener('click', function () {
            navigator.clipboard.writeText(window.location.href).then(function () {
                var copied = document.getElementById('pp-copied');
                copied.style.display = 'inline';
                setTimeout(function () {
                    copied.style.display = 'none';
                }, 1500);
            });
        });
    </script>
@endsection
