<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
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
                    ->label('CPF/CNPJ')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Papel')
                    ->badge()
                    ->formatStateUsing(fn (Role $state): string => $state->label())
                    ->color(fn (Role $state): string => match ($state) {
                        Role::SuperAdmin => 'danger',
                        Role::Admin => 'gray',
                        Role::Seller => 'success',
                        Role::Client => 'info',
                    }),
                IconColumn::make('email_verified_at')
                    ->label('E-mail confirmado')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Papel')
                    ->options(fn (): array => collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all()),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
