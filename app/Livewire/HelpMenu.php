<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Filament\Client\Pages\Faq as ClientFaq;
use App\Filament\Pages\Ajuda\Faq as SellerFaq;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Botão "?" no topbar (ver AdminPanelProvider/SuperAdminPanelProvider/ClientPanelProvider,
 * render hook GLOBAL_SEARCH_AFTER) — dropdown com atalho pra rever o tutorial guiado
 * (ver App\Livewire\OnboardingTutorial::restart()) e um link pra Perguntas frequentes.
 */
class HelpMenu extends Component
{
    public function startTutorial(): void
    {
        $this->dispatch('restart-onboarding-tour');
    }

    public function render(): View
    {
        $role = Auth::user()->role;
        $panel = $role->panelId();

        $faqUrl = $role === Role::Client
            ? ClientFaq::getUrl(panel: $panel)
            : SellerFaq::getUrl(panel: $panel);

        return view('livewire.help-menu', [
            'faqUrl' => $faqUrl,
        ]);
    }
}
