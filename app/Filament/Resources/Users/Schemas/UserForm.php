<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User\Enums\Plan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role')
                    ->label('Papel')
                    ->options(
                        // Role::Admin é um valor reservado/morto (ver App\Enums\Role) —
                        // nenhuma conta deve ser criada com ele.
                        collect(Role::cases())
                            ->reject(fn (Role $role): bool => $role === Role::Admin)
                            ->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])
                    )
                    ->required()
                    ->live(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-mail')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('document')
                    ->label('CPF')
                    ->required()
                    ->length(11)
                    // Documento é único por papel, não globalmente — mesmo CPF pode existir
                    // como Cliente e como Vendedor (ver App\Http\Requests\Auth\RegisterRequest).
                    ->unique(modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('role', $get('role'))),
                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->confirmed()
                    ->rule(Password::min(8)->numbers()->symbols()->mixedCase()),
                TextInput::make('password_confirmation')
                    ->label('Confirmar senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
                Section::make('Dados do vendedor')
                    ->visible(fn (Get $get): bool => $get('role') === Role::Seller->value)
                    ->components([
                        Select::make('plan')
                            ->label('Plano')
                            ->options(collect(Plan::cases())->mapWithKeys(fn (Plan $plan) => [$plan->value => $plan->label()]))
                            ->required(fn (Get $get): bool => $get('role') === Role::Seller->value),
                        DateTimePicker::make('trial_ends_at')
                            ->label('Avaliação gratuita até')
                            ->helperText('Deixe em branco para um plano sem expiração automática.'),
                        DateTimePicker::make('subscription_ends_at')
                            ->label('Assinatura paga até')
                            ->helperText('Deixe em branco caso o pagamento ainda não tenha sido aprovado.'),
                    ]),
            ]);
    }
}
