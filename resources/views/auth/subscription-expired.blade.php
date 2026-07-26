<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assinatura vencida — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="choose-plan-page">

    <div class="choose-plan-wrap">

        <div class="choose-plan-confirmation">
            <div class="choose-plan-confirmation__icon choose-plan-confirmation__icon--pending">⏳</div>

            <h1 class="choose-plan-title">Sua assinatura venceu</h1>

            <p class="choose-plan-subtitle">
                Seu plano {{ $user->plan->label() }} estava ativo, mas o período pago chegou ao fim. Um representante
                da {{ config('app.name') }} vai entrar em contato em até <strong>24 horas</strong> para renovar seu
                acesso.
            </p>

            <a href="{{ url('/') }}" class="choose-plan-card__button choose-plan-card__button--solid">
                Voltar para o início
            </a>
        </div>

    </div>

</body>

</html>
