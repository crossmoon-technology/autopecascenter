<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda os dados do cadastro de vendedor (nome/documento/senha já com hash/ip) até a
     * confirmação de e-mail — sem isso, alguém poderia trocar o role/senha de uma conta
     * Role::Client alheia só sabendo o e-mail dela, sem provar que é dono (ver
     * AuthService::registerSeller()/AuthController::verifyEmail()).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('pending_seller_upgrade')->nullable()->after('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_seller_upgrade');
        });
    }
};
