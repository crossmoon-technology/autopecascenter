<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contas com role=2 (Role::Admin) eram, na prática, vendedores — esse valor agora
     * fica reservado pra um futuro ator (ver App\Enums\Role) e o vendedor de verdade
     * passa a ser role=4 (Role::Seller). Contas existentes são migradas já aprovadas
     * (plan_approved_at preenchido) com o plano Básico, pra ninguém perder acesso.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 2)
            ->update([
                'role' => 4,
                'plan' => 1,
                'plan_approved_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('role', 4)
            ->update([
                'role' => 2,
                'plan' => null,
                'trial_ends_at' => null,
                'plan_approved_at' => null,
            ]);
    }
};
