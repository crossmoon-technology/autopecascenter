{{-- Sem botão de fechar/fora-do-modal de propósito: só o clique em "Aceitar" (ver
    resources/js/app.js) esconde isso — o visitante não navega no site sem aceitar antes. --}}
<div class="cookie-consent" data-cookie-consent role="dialog" aria-modal="true" aria-labelledby="cookie-consent-title">
    <div class="cookie-consent__card">
        <h2 id="cookie-consent-title" class="cookie-consent__title">Antes de continuar</h2>

        <p class="cookie-consent__text">
            Usamos cookies essenciais para manter o site funcionando e lembrar suas preferências de navegação.
            Não usamos cookies de rastreamento ou publicidade de terceiros. Saiba mais na nossa
            <a href="{{ route('cookie-policy') }}" target="_blank" rel="noopener">Política de Cookies</a>.
        </p>

        <button type="button" class="btn btn--solid btn--block cookie-consent__accept" data-cookie-consent-accept>
            Aceitar e continuar
        </button>
    </div>
</div>
