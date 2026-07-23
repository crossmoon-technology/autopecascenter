<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enviar lista de peças — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss'])
</head>

<body class="register-page">

    <div class="register-wrap">

        <div class="register-card">

            <div class="register-card__header">
                <a href="{{ url('/') }}" class="register-card__logo">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
                </a>

                @if ($orderLink->isUsed())
                    <h1 class="register-card__title">Link já utilizado</h1>
                    <p class="register-card__subtitle">Esse link já foi usado pra criar uma conta e não está mais disponível.</p>
                @else
                    <h1 class="register-card__title">Complete seu cadastro</h1>
                    <p class="register-card__subtitle">Você foi convidado a enviar sua lista de peças. Preencha os dados abaixo pra continuar.</p>
                @endif
            </div>

            @if (! $orderLink->isUsed())
                @if (session('error'))
                    <div class="register-alert register-alert--error">
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('order-links.store', ['orderLink' => $orderLink->token]) }}" class="register-form" novalidate>
                    @csrf

                    <div class="register-form__row">
                        <div class="register-form__field @error('name') has-error @enderror">
                            <label class="register-form__label" for="name">Nome completo</label>
                            <input
                                class="register-form__input"
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="Seu nome"
                                required
                                autofocus
                                autocomplete="name"
                            >
                            @error('name')
                                <span class="register-form__error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="register-form__field @error('document') has-error @enderror">
                            <label class="register-form__label" for="document">CPF</label>
                            <input
                                class="register-form__input"
                                id="document"
                                type="text"
                                name="document"
                                value="{{ old('document') }}"
                                placeholder="000.000.000-00"
                                required
                                autocomplete="off"
                                maxlength="11"
                            >
                            @error('document')
                                <span class="register-form__error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="register-form__field @error('email') has-error @enderror">
                        <label class="register-form__label" for="email">E-mail</label>
                        <input
                            class="register-form__input"
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="seu@email.com"
                            required
                            autocomplete="email"
                        >
                        @error('email')
                            <span class="register-form__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="register-form__row">
                        <div class="register-form__field @error('password') has-error @enderror">
                            <label class="register-form__label" for="password">Senha</label>
                            <input
                                class="register-form__input"
                                id="password"
                                type="password"
                                name="password"
                                placeholder="••••••••"
                                required
                                autocomplete="new-password"
                            >
                            @error('password')
                                <span class="register-form__error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="register-form__field">
                            <label class="register-form__label" for="password_confirmation">Confirmar senha</label>
                            <input
                                class="register-form__input"
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                placeholder="••••••••"
                                required
                                autocomplete="new-password"
                            >
                        </div>
                    </div>

                    <button type="submit" class="register-form__submit">
                        Continuar
                    </button>

                </form>
            @endif

            <div class="register-card__back">
                <a href="{{ url('/') }}">← Voltar ao site</a>
            </div>

        </div>

    </div>

</body>

</html>
