<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Conta criada aqui é criada pelo super admin, não pelo próprio usuário — não faz
     * sentido exigir a confirmação de e-mail de novo depois.
     */
    protected function afterCreate(): void
    {
        $this->record->markEmailAsVerified();
    }
}
