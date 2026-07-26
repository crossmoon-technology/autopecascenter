<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar conta — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="register-page">

    <div class="register-wrap">

        <div class="register-card">

            <div class="register-card__header">
                <a href="{{ url('/') }}" class="register-card__logo">
                    <img src="{{ asset('images/white-logo.png') }}" alt="{{ config('app.name') }}">
                </a>
                <h1 class="register-card__title">Como você vai usar a plataforma?</h1>
                <p class="register-card__subtitle">Escolha uma opção pra continuar o cadastro.</p>
            </div>

            <div class="register-choice">
                <a href="{{ route('register.client') }}" class="register-choice__option">
                    <span class="register-choice__title">Sou Cliente</span>
                    <span class="register-choice__description">Quero enviar listas de peças pra um vendedor que já me passou o código dele.</span>
                </a>

                <a href="{{ route('register.seller') }}" class="register-choice__option">
                    <span class="register-choice__title">Sou Vendedor</span>
                    <span class="register-choice__description">Quero fazer cotações pela plataforma e gerenciar os pedidos dos meus clientes.</span>
                </a>
            </div>

            <div class="register-card__footer">
                <p>Já tem uma conta? <a href="{{ route('login') }}">Entrar</a></p>
            </div>

            <div class="register-card__back">
                <a href="{{ url('/') }}">← Voltar ao site</a>
            </div>

        </div>

    </div>

</body>

</html>
