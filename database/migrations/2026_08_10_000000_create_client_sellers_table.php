<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Substitui o antigo users.invited_by_id (1 cliente = 1 vendedor pra sempre) por uma
     * relação N:N — o mesmo cliente pode aceitar convites de vários vendedores, cada um
     * vendo só os próprios dados dele através dessa tabela.
     */
    public function up(): void
    {
        Schema::create('client_sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'seller_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_sellers');
    }
};
