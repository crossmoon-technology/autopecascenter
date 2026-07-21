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
                    <li><a href="{{ route('home') }}" class="header__link header__link--active">Home</a></li>
                    <li><a href="#como-funciona" class="header__link">Como funciona</a></li>
                    <li><a href="#vantagens" class="header__link">Vantagens</a></li>
                    <li><a href="#para-sua-empresa" class="header__link">Para sua empresa</a></li>
                    <li><a href="#planos" class="header__link">Planos</a></li>
                    <li><a href="#contato" class="header__link">Contato</a></li>
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