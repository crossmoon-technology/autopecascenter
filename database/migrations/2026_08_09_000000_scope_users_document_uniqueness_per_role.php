<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O CPF passa a ser único por papel (não mais globalmente único) — a mesma pessoa
     * pode ter uma conta Cliente (convidada por um vendedor) e uma conta Seller (o
     * próprio negócio dela) com o mesmo documento. O e-mail continua único globalmente,
     * então cada conta ainda precisa do seu próprio e-mail de login.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_document_unique');
            $table->unique(['document', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['document', 'role']);
            $table->unique('document');
        });
    }
};
