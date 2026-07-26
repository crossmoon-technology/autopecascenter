<?php

namespace App\Filament\Resources\Sellers\Schemas;

use App\Models\User;
use App\Models\User\Enums\Plan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SellerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->disabled(),
                TextInput::make('email')
                    ->label('E-mail')
                    ->disabled(),
                TextInput::make('document')
                    ->label('CPF')
                    ->disabled(),
                Select::make('plan')
                    ->label('Plano')
                    ->options(collect(Plan::cases())->mapWithKeys(fn (Plan $plan) => [$plan->value => $plan->label()]))
                    ->required(),
                DateTimePicker::make('trial_ends_at')
                    ->label('Avaliação gratuita até')
                    ->helperText('Deixe em branco para um plano sem expiração automática. Preenchido, o acesso é bloqueado sozinho depois dessa data.'),
                DateTimePicker::make('subscription_ends_at')
                    ->label('Assinatura paga até')
                    ->helperText('Preenchido automaticamente com +30 dias ao usar "Aprovar pagamento". Deixe em branco para uma assinatura sem expiração automática, ou ajuste a data à mão (renovação manual, promoção etc).'),
                TextInput::make('status')
                    ->label('Status')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (TextInput $component, ?User $record) => $component->state($record?->sellerStatusLabel())),
            ]);
    }
}
