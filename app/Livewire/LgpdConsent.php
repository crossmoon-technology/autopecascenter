<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Modal de boas-vindas + consentimento LGPD, exibido antes de qualquer outra coisa no
 * primeiro acesso (ver lgpd_accepted_at em App\Models\User) — inclusive antes do tutorial
 * guiado (ver App\Livewire\OnboardingTutorial::mount(), que só se mostra depois desse
 * aceite). Sem botão de fechar de propósito: só accept() esconde o modal.
 */
class LgpdConsent extends Component
{
    public bool $visible = false;

    public function mount(): void
    {
        $this->visible = Auth::check() && Auth::user()->needsLgpdConsent();
    }

    public function accept(): void
    {
        Auth::user()->acceptLgpd();
        $this->visible = false;

        // Avisa o App\Livewire\OnboardingTutorial (sempre montado no painel, ver
        // */PanelProvider) pra só começar o tour DEPOIS desse aceite, não junto.
        $this->dispatch('lgpd-accepted');
    }

    public function render(): View
    {
        return view('livewire.lgpd-consent');
    }
}
