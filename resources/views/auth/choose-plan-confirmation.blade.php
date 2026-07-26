<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plano escolhido — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="choose-plan-page">

    <div class="choose-plan-wrap">

        <div class="choose-plan-confirmation">
            <div class="choose-plan-confirmation__icon">✓</div>

            <h1 class="choose-plan-title">Sua avaliação gratuita começou!</h1>

            <p class="choose-plan-subtitle">
                Você já tem acesso liberado por 7 dias. Como você escolheu um plano pago, um representante da
                {{ config('app.name') }} vai entrar em contato em até <strong>24 horas</strong> para explicar como o
                plano funciona e os meios de pagamento disponíveis.
            </p>

            <a href="{{ \App\Http\Middleware\FilamentAuthenticate::panelUrlForRole(auth()->user()->role) }}" class="choose-plan-card__button choose-plan-card__button--solid">
                Ir para o painel
            </a>
        </div>

    </div>

</body>

</html>
