<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable por enquanto — o backfill (ver migration seguinte) preenche os pedidos já
     * existentes antes de virar obrigatório. Sem isso, um pedido não tem como dizer pra
     * qual vendedor ele é assim que o cliente passa a poder ter mais de um.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });
    }
};
