<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escolha seu plano — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="choose-plan-page">

    <div class="choose-plan-wrap">

        <div class="choose-plan-header">
            <a href="{{ url('/') }}" class="choose-plan-logo">
                <img src="{{ asset('images/white-logo.png') }}" alt="{{ config('app.name') }}">
            </a>
            <h1 class="choose-plan-title">Escolha como você quer começar</h1>
            <p class="choose-plan-subtitle">
                Todas as opções liberam o acesso na hora, com 7 dias de avaliação gratuita.
            </p>
        </div>

        <form method="POST" action="{{ route('choose-plan.store') }}">
            @csrf

            <div class="choose-plan-options">
                <div class="choose-plan-card choose-plan-card--highlight">
                    <span class="choose-plan-card__badge">Recomendado</span>
                    <h2>Avaliação gratuita</h2>
                    <p>7 dias com acesso completo ao plano Profissional, sem compromisso.</p>
                    <button
                        type="submit"
                        name="plan_choice"
                        value="trial"
                        class="choose-plan-card__button choose-plan-card__button--solid"
                    >
                        Começar avaliação gratuita
                    </button>
                </div>

                <div class="choose-plan-card">
                    <h2>Básico</h2>
                    <p>Para quem está começando a cotar online.</p>
                    <button type="submit" name="plan_choice" value="basico" class="choose-plan-card__button">
                        Escolher Básico
                    </button>
                </div>

                <div class="choose-plan-card">
                    <h2>Profissional</h2>
                    <p>Para equipes que vendem todos os dias.</p>
                    <button type="submit" name="plan_choice" value="profissional" class="choose-plan-card__button">
                        Escolher Profissional
                    </button>
                </div>
            </div>

            @error('plan_choice')
                <p class="choose-plan-error">{{ $message }}</p>
            @enderror
        </form>

    </div>

</body>

</html>
