<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="login-page">

    <div class="login-wrap">

        <div class="login-card">

            <div class="login-card__header">
                <a href="{{ url('/') }}" class="login-card__logo">
                    <img src="{{ asset('images/white-logo.png') }}" alt="{{ config('app.name') }}">
                </a>
                <h1 class="login-card__title">Bem-vindo de volta</h1>
                <p class="login-card__subtitle">Acesse sua conta para continuar</p>
            </div>

            @if (session('status'))
                <div class="login-alert login-alert--success">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="login-alert login-alert--error">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="login-form" novalidate>
                @csrf

                <div class="login-form__field @error('email') has-error @enderror">
                    <label class="login-form__label" for="email">E-mail</label>
                    <input class="login-form__input" id="email" type="email" name="email"
                        value="{{ old('email') }}" placeholder="seu@email.com" required autofocus autocomplete="email">
                    @error('email')
                        <span class="login-form__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="login-form__field @error('password') has-error @enderror">
                    <label class="login-form__label" for="password">Senha</label>
                    <input class="login-form__input" id="password" type="password" name="password"
                        placeholder="••••••••" required autocomplete="current-password">
                    @error('password')
                        <span class="login-form__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="login-form__forgot">
                    <a href="{{ route('password.request') }}">Esqueci minha senha</a>
                </div>

                <div class="login-form__remember">
                    <label class="login-form__checkbox-label">
                        <input type="checkbox" name="remember" id="remember" class="login-form__checkbox">
                        <span>Lembrar-me</span>
                    </label>
                </div>

                <button type="submit" class="login-form__submit">
                    Entrar
                </button>

            </form>

            <div class="login-card__back">
                <a href="{{ url('/') }}">← Voltar ao site</a>
            </div>

        </div>

    </div>

</body>

</html>
