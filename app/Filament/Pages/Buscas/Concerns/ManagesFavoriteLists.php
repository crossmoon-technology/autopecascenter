<?php

namespace App\Filament\Pages\Buscas\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

trait ManagesFavoriteLists
{
    /**
     * Botão secundário ao lado da estrela — o toggle rápido sempre manda pra "Lista
     * padrão", então quem quer escolher (ou criar) uma lista específica na hora de
     * favoritar usa essa action em vez de precisar ir até Favoritas depois.
     */
    public function pickFavoriteListAction(): Action
    {
        return Action::make('pickFavoriteList')
            ->label('Adicionar à lista')
            ->icon(Heroicon::OutlinedFolderPlus)
            ->modalHeading('Adicionar à lista')
            ->modalSubmitActionLabel('Adicionar')
            ->schema([
                Select::make('favorite_list_id')
                    ->label('Lista')
                    ->options(fn (): array => Auth::user()->favoriteLists()->pluck('name', 'id')->all())
                    ->required()
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nome da lista')
                            ->required()
                            ->maxLength(255)
                            ->unique('favorite_lists', 'name', modifyRuleUsing: fn ($rule) => $rule->where('user_id', Auth::id())),
                    ])
                    ->createOptionUsing(fn (array $data): int => Auth::user()->favoriteLists()->create(['name' => $data['name']])->id),
            ])
            ->action(function (array $data, array $arguments): void {
                $part_id = (int) $arguments['part_id'];

                $favoriteList = Auth::user()->favoriteLists()->findOrFail($data['favorite_list_id']);
                $favoriteList->parts()->syncWithoutDetaching([$part_id]);

                $this->markPartAsFavorited($part_id);

                Notification::make()
                    ->title("Adicionada à lista \"{$favoriteList->name}\".")
                    ->success()
                    ->send();
            });
    }

    abstract protected function markPartAsFavorited(int $part_id): void;
}
