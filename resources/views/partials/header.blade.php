<header class="header">
    <div class="container">
        <div class="header__inner">
            <a href="{{ route('home') }}" class="header__logo">
                <img src="{{ asset('images/logo.png') }}" alt="Auto Peças Center">
            </a>

            <input type="checkbox" id="header-nav-toggle" class="header__nav-toggle-input">
            <label for="header-nav-toggle" class="header__nav-toggle" aria-label="Abrir menu">
                <img src="{{ asset('images/icons/menu.svg') }}" alt="" class="header__nav-toggle-icon">
            </label>

            <nav class="header__nav">
                <ul class="header__menu">
                    <li><a href="{{ route('home') }}" class="header__link @if(request()->routeIs('home')) header__link--active @endif">Home</a></li>
                    <li><a href="{{ route('como-funciona') }}" class="header__link @if(request()->routeIs('como-funciona')) header__link--active @endif">Como funciona</a></li>
                    <li><a href="{{ route('planos') }}" class="header__link @if(request()->routeIs('planos')) header__link--active @endif">Planos</a></li>
                    <li><a href="{{ route('contato') }}" class="header__link @if(request()->routeIs('contato')) header__link--active @endif">Contato</a></li>
                </ul>

                <div class="header__actions header__actions--mobile">
                    @include('partials.header-actions')
                </div>
            </nav>

            <div class="header__actions header__actions--desktop">
                @include('partials.header-actions')
            </div>
        </div>
    </div>
</header>