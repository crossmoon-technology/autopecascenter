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
                @if (! $this->isSeller() && $user->invitedBy)
                    <div class="profile-field">
                        <dt>Convidado por</dt>
                        <dd>{{ $user->invitedBy->name }}</dd>
                    </div>
                @endif
            </dl>
        </div>

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
