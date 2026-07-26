<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O fluxo de convite por link (App\Models\OrderLink) foi substituído pelo cadastro
     * direto do cliente com o código do vendedor (users.referral_code) — ver
     * App\Http\Controllers\AuthController::registerClient(). O vínculo cliente-vendedor
     * em si já não depende mais dessa tabela desde a migration client_sellers.
     */
    public function up(): void
    {
        Schema::dropIfExists('order_links');
    }

    public function down(): void
    {
        Schema::create('order_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
