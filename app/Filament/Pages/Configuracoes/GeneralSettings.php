<?php

namespace App\Filament\Pages\Configuracoes;

use App\Filament\Concerns\HasHelpAction;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GeneralSettings extends Page implements HasForms
{
    use HasHelpAction;
    use InteractsWithForms;

    protected string $view = 'filament.pages.configuracoes.general-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Geral';

    protected static ?string $title = 'Geral';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'logo' => Auth::user()->logo,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página Geral';
    }

    protected function helpDescription(): string
    {
        return '<p>Cadastre sua logo aqui — ela aparece ao lado da logo da Auto Peças Center nas cotações que você exportar em PDF.</p>';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('logo')
                    ->label('Sua logo')
                    ->image()
                    ->disk('public')
                    ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->directory('users/logos')
                    ->helperText('Aparece ao lado da logo da Auto Peças Center nas cotações exportadas em PDF.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Auth::user()->update([
            'logo' => $this->form->getState()['logo'],
        ]);

        Notification::make()
            ->title('Configurações salvas.')
            ->success()
            ->send();
    }
}
