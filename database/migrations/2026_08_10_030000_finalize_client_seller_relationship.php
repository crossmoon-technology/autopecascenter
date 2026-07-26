<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Depois do backfill (migration anterior), todo pedido já tem um vendedor — vira
     * obrigatório pra qualquer pedido novo daqui pra frente. users.invited_by_id sai de
     * vez, substituído pela tabela client_sellers.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('invited_by_id')->nullable()->after('registration_ip')->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->change();
        });
    }
};
