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
