<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — {{ config('app.name') }}</title>
</head>

<body>

    <a href="{{ url('/') }}">
        <img src="{{ asset('images/autopecascenter-logo.png') }}" alt="{{ config('app.name') }}">
    </a>
    <h1>Bem-vindo de volta</h1>
    <p>Acesse sua conta para continuar</p>

    @if (session('error'))
        <p>{{ session('error') }}</p>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
            <div class="login-card__header">
                <a href="{{ url('/') }}" class="login-card__logo">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
                </a>
                <h1 class="login-card__title">Bem-vindo de volta</h1>
                <p class="login-card__subtitle">Acesse sua conta para continuar</p>
            </div>

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

        <div>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="seu@email.com" required autofocus autocomplete="email">
            @error('email')
                <span>{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            @error('password')
                <span>{{ $message }}</span>
            @enderror
        </div>

        <div>
            <a href="{{ route('password.request') }}">Esqueci minha senha</a>
        </div>

        <div>
            <label for="remember">
                <input type="checkbox" name="remember" id="remember">
                <span>Lembrar-me</span>
            </label>
        </div>

        <button type="submit">
            Entrar
        </button>

    </form>

    <div>
        <a href="{{ url('/') }}">← Voltar ao site</a>
    </div>

</body>

</html>
