<x-filament-panels::page>
    <style>
        .profile-card {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .profile-section {
            border: 1px solid rgba(127, 127, 127, 0.2);
            border-radius: 0.75rem;
            padding: 1.25rem;
        }
        .profile-section h3 {
            font-size: 0.9375rem;
            font-weight: 700;
            margin: 0 0 1rem;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
            gap: 1rem;
        }
        .profile-field dt {
            font-size: 0.75rem;
            opacity: 0.65;
            margin-bottom: 0.1875rem;
        }
        .profile-field dd {
            font-size: 0.9375rem;
            font-weight: 600;
            margin: 0;
        }
        .profile-logo {
            width: 5rem;
            height: 5rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
            object-fit: contain;
            background: #fff;
            padding: 0.5rem;
        }
        .profile-hint {
            font-size: 0.8125rem;
            opacity: 0.65;
            margin-top: 0.75rem;
        }
        .profile-hint a {
            text-decoration: underline;
        }
        .profile-code {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .profile-code__value {
            font-family: ui-monospace, monospace;
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: rgb(249 70 3);
        }
        .profile-code__copy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 2.25rem;
            height: 2.25rem;
            flex-shrink: 0;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
            background: transparent;
            color: inherit;
            opacity: 0.7;
            cursor: pointer;
        }
        .profile-code__copy:hover {
            opacity: 1;
            border-color: rgb(249 70 3);
        }
        .profile-code__copy svg {
            width: 1.125rem;
            height: 1.125rem;
        }
        .profile-code__copied {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(21 128 61);
        }
    </style>

    @php
        $user = $this->getUser();
    @endphp

    <div class="profile-card">
        <div class="profile-section">
            <h3>Seus dados</h3>
            <dl class="profile-grid">
                <div class="profile-field">
                    <dt>Nome</dt>
                    <dd>{{ $user->name }}</dd>
                </div>
                <div class="profile-field">
                    <dt>E-mail</dt>
                    <dd>{{ $user->email }}</dd>
                </div>
                <div class="profile-field">
                    <dt>CPF</dt>
                    <dd>{{ $this->formattedDocument() }}</dd>
                </div>
                <div class="profile-field">
                    <dt>Função</dt>
                    <dd>{{ $user->role->label() }}</dd>
                </div>
                <div class="profile-field">
                    <dt>Membro desde</dt>
                    <dd>{{ $user->created_at->translatedFormat('d/m/Y') }}</dd>
                </div>
                @if (! $this->isSeller() && $user->sellers->isNotEmpty())
                    <div class="profile-field">
                        <dt>{{ $user->sellers->count() > 1 ? 'Vendedores' : 'Vendedor' }}</dt>
                        <dd>{{ $user->sellers->pluck('name')->join(', ') }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($user->role === \App\Enums\Role::Seller)
            <div class="profile-section">
                <h3>Seu código de vendedor</h3>
                <p class="profile-hint">Compartilhe com seus clientes — eles usam esse código pra se vincular a você no cadastro em {{ route('register.client') }}.</p>

                <div class="profile-code" x-data="{ copied: false }">
                    <span class="profile-code__value">{{ $user->referral_code }}</span>

                    <button
                        type="button"
                        class="profile-code__copy"
                        title="Copiar código"
                        x-on:click="navigator.clipboard.writeText('{{ $user->referral_code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    >
                        <x-filament::icon icon="heroicon-o-clipboard-document" />
                    </button>

                    <span x-show="copied" x-cloak class="profile-code__copied">Copiado!</span>
                </div>
            </div>
        @endif

        @if ($this->isSeller())
            <div class="profile-section">
                <h3>Sua logo</h3>
                @if ($user->logo)
                    <img src="{{ Storage::disk('public')->url($user->logo) }}" alt="" class="profile-logo">
                @else
                    <p class="profile-hint">Você ainda não cadastrou uma logo.</p>
                @endif
                <p class="profile-hint">
                    Aparece nas cotações exportadas em PDF. Pra trocar, vá em
                    <a href="{{ \App\Filament\Pages\Configuracoes\GeneralSettings::getUrl(panel: $user->role->panelId()) }}">Configurações &gt; Geral</a>.
                </p>
            </div>
        @endif

        <div class="profile-section">
            <h3>Privacidade</h3>
            @if ($user->lgpd_accepted_at)
                <p class="profile-hint">
                    Você aceitou os termos de privacidade e o uso de cookies em
                    {{ $user->lgpd_accepted_at->translatedFormat('d/m/Y \à\s H:i') }}.
                </p>
            @else
                <p class="profile-hint">Você ainda não confirmou os termos de privacidade.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
