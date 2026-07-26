@guest
    <a href="{{ route('login') }}" class="header__btn header__btn--outline">Entrar</a>
    <a href="{{ route('register') }}" class="header__btn header__btn--solid">Criar conta</a>
@else
    <a href="{{ \App\Http\Middleware\FilamentAuthenticate::panelUrlForRole(auth()->user()->role) }}" class="header__btn header__btn--solid">Ir para o painel</a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="header__btn header__btn--outline">Sair</button>
    </form>
@endguest
