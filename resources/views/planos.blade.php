@extends('layouts.app')

@section('content')
    <main>
        @include('partials.page-hero', [
            'title' => 'Planos para o seu negócio crescer',
            'subtitle' => 'Escolha o plano ideal para o tamanho da sua operação. Sem taxas escondidas, cancele quando quiser.',
        ])

        <section class="pricing">
            <div class="container">
                <div class="pricing__grid">
                    @php
                        $planDefaults = \App\Filament\Pages\Configuracoes\ApplicationSettings::planDefaults();
                        $planSetting = fn (string $key) => \App\Models\Setting::get($key, $planDefaults[$key]);
                        $planFeatures = fn (string $slug) => array_filter(array_map('trim', explode("\n", $planSetting("plan_{$slug}_features"))));
                    @endphp

                    <div class="pricing__card">
                        <h2 class="pricing__name">{{ $planSetting('plan_trial_name') }}</h2>
                        <p class="pricing__description">{{ $planSetting('plan_trial_description') }}</p>
                        <p class="pricing__price">{{ $planSetting('plan_trial_price') }}<span>{{ $planSetting('plan_trial_price_period') }}</span></p>
                        <a href="#agendar-demonstracao" class="btn btn--outline btn--block">Começar avaliação gratuita</a>
                        <ul class="pricing__features">
                            @foreach ($planFeatures('trial') as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="pricing__card">
                        <h2 class="pricing__name">{{ $planSetting('plan_basico_name') }}</h2>
                        <p class="pricing__description">{{ $planSetting('plan_basico_description') }}</p>
                        <p class="pricing__price">{{ $planSetting('plan_basico_price') }}<span>{{ $planSetting('plan_basico_price_period') }}</span></p>
                        <a href="#agendar-demonstracao" class="btn btn--outline btn--block">Começar agora</a>
                        <ul class="pricing__features">
                            @foreach ($planFeatures('basico') as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="pricing__card pricing__card--highlight">
                        <span class="pricing__badge">Mais popular</span>
                        <h2 class="pricing__name">{{ $planSetting('plan_profissional_name') }}</h2>
                        <p class="pricing__description">{{ $planSetting('plan_profissional_description') }}</p>
                        <p class="pricing__price">{{ $planSetting('plan_profissional_price') }}<span>{{ $planSetting('plan_profissional_price_period') }}</span></p>
                        <a href="#agendar-demonstracao" class="btn btn--solid btn--block">Começar agora</a>
                        <ul class="pricing__features">
                            @foreach ($planFeatures('profissional') as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        @include('partials.cta-banner', [
            'title' => 'Ainda com dúvidas sobre qual plano escolher?',
            'subtitle' => 'Fale com nosso time e encontre a melhor opção para sua empresa.',
            'buttonLabel' => 'Falar com um especialista',
            'buttonUrl' => route('contato'),
        ])
    </main>
@endsection
