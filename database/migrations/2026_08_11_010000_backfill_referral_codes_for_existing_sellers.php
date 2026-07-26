<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Vendedores criados antes da coluna referral_code existir (o booted() do User só
     * gera um código pra registros novos daqui pra frente) ficam sem código — sem ele,
     * nenhum cliente consegue se vincular a eles pelo cadastro direto.
     */
    public function up(): void
    {
        User::query()
            ->where('role', Role::Seller)
            ->whereNull('referral_code')
            ->each(fn (User $seller) => $seller->forceFill(['referral_code' => User::generateUniqueReferralCode()])->save());
    }

    public function down(): void
    {
        User::query()->where('role', Role::Seller)->update(['referral_code' => null]);
    }
};
