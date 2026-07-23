<?php

namespace App\Filament\Pages\Ajuda;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Faq extends Page
{
    protected string $view = 'filament.pages.ajuda.faq';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $title = 'Perguntas frequentes';

    // Só acessível pelo link no menu de ajuda (botão "?" no topbar, ver
    // App\Livewire\HelpMenu) — não precisa de item próprio na sidebar.
    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public function getFaqItems(): array
    {
        return config('onboarding_faq.seller');
    }
}
