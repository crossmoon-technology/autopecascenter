<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aguardando aprovação — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="choose-plan-page">

    <div class="choose-plan-wrap">

        <div class="choose-plan-confirmation">
            <div class="choose-plan-confirmation__icon choose-plan-confirmation__icon--pending">⏳</div>

            <h1 class="choose-plan-title">Aguardando aprovação do pagamento</h1>

            <p class="choose-plan-subtitle">
                Você já escolheu o plano {{ $user->plan->label() }}, mas sua avaliação gratuita de 7 dias já foi
                usada. Um representante da {{ config('app.name') }} vai entrar em contato em até
                <strong>24 horas</strong>.
            </p>

            <div class="choose-plan-pending-switch">
                <p class="choose-plan-pending-switch__label">Mudou de ideia?</p>

                @php
                    $other_plans = [
                        'basico' => \App\Models\User\Enums\Plan::Basico,
                        'profissional' => \App\Models\User\Enums\Plan::Profissional,
                    ];
                @endphp

                <form method="POST" action="{{ route('choose-plan.store') }}" class="choose-plan-pending-switch__form">
                    @csrf

                    @foreach ($other_plans as $value => $plan)
                        @continue($plan === $user->plan)
                        <button type="submit" name="plan_choice" value="{{ $value }}" class="choose-plan-card__button">
                            Mudar para {{ $plan->label() }}
                        </button>
                    @endforeach
                </form>
            </div>

            <a href="{{ url('/') }}" class="choose-plan-card__button choose-plan-card__button--solid">
                Voltar para o início
            </a>
        </div>

    </div>

</body>

</html>
