<?php

namespace App\Filament\Resources\Sellers\Tables;

use App\Models\User;
use App\Models\User\Enums\Plan;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SellersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('document')
                    ->label('CPF')
                    ->searchable(),
                TextColumn::make('plan')
                    ->label('Plano')
                    ->badge()
                    ->formatStateUsing(fn (?Plan $state): string => $state?->label() ?? '—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $record): string => $record->sellerStatusLabel())
                    ->color(fn (User $record): string => $record->sellerStatusColor()),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('approvePayment')
                    ->label('Aprovar pagamento')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isPaymentPending())
                    ->action(function (User $record): void {
                        $record->approvePlanPayment();

                        Notification::make()
                            ->title('Pagamento aprovado.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
