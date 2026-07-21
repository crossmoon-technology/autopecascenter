<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fica nulo pra buscas cujo método não sabe informar isso no momento do registro
     * (a API responde de forma assíncrona, depois que o histórico já foi gravado) —
     * só a Base de dados preenche isso de forma confiável hoje.
     */
    public function up(): void
    {
        Schema::table('search_histories', function (Blueprint $table) {
            $table->boolean('found_results')->nullable()->after('method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_histories', function (Blueprint $table) {
            $table->dropColumn('found_results');
        });
    }
};
