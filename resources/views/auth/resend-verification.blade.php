<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reenviar confirmação de e-mail — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="forgot-page">

    <div class="forgot-wrap">

        <div class="forgot-card">

            <div class="forgot-card__header">
                <a href="{{ url('/') }}" class="forgot-card__logo">
                    <img src="{{ asset('images/white-logo.png') }}" alt="{{ config('app.name') }}">
                </a>
                <h1 class="forgot-card__title">Não recebeu o e-mail de confirmação?</h1>
                <p class="forgot-card__subtitle">Informe o e-mail cadastrado e reenviamos o link de confirmação.</p>
            </div>

            @if (session('status'))
                <div class="forgot-alert forgot-alert--success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('verification.resend') }}" class="forgot-form" novalidate>
                @csrf

                <div class="forgot-form__field @error('email') has-error @enderror">
                    <label class="forgot-form__label" for="email">E-mail</label>
                    <input
                        class="forgot-form__input"
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="seu@email.com"
                        required
                        autofocus
                        autocomplete="email"
                    >
                    @error('email')
                        <span class="forgot-form__error">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="forgot-form__submit">
                    Reenviar e-mail de confirmação
                </button>

            </form>

            <div class="forgot-card__footer">
                <a href="{{ route('login') }}">← Voltar para o login</a>
            </div>

        </div>

    </div>

</body>

</html>
