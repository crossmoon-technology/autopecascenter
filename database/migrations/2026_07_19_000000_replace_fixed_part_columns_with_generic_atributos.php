<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only codigo and conversoes are consistent across every manufacturer's
     * .jsonl catalog — descricao/tipo/posicao/categoria were modeled after
     * Cofap's export specifically, and other suppliers bring different fields.
     * Everything besides codigo/conversoes now lives in a generic atributos
     * JSON bag instead of fixed columns.
     */
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->json('atributos')->nullable()->after('conversoes');
        });

        DB::table('parts')->select('id', 'descricao', 'tipo', 'posicao', 'categoria')->orderBy('id')->chunkById(500, function ($parts) {
            foreach ($parts as $part) {
                $atributos = array_filter([
                    'descricao' => $part->descricao,
                    'tipo' => $part->tipo,
                    'posicao' => $part->posicao,
                    'categoria' => $part->categoria,
                ], fn ($value) => ! is_null($value));

                if (! empty($atributos)) {
                    DB::table('parts')->where('id', $part->id)->update([
                        'atributos' => json_encode($atributos),
                    ]);
                }
            }
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn(['descricao', 'tipo', 'posicao', 'categoria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->string('descricao')->nullable();
            $table->string('tipo')->nullable();
            $table->string('posicao')->nullable();
            $table->string('categoria')->nullable();
        });

        DB::table('parts')->select('id', 'atributos')->whereNotNull('atributos')->orderBy('id')->chunkById(500, function ($parts) {
            foreach ($parts as $part) {
                $atributos = json_decode($part->atributos, true) ?? [];

                DB::table('parts')->where('id', $part->id)->update([
                    'descricao' => $atributos['descricao'] ?? null,
                    'tipo' => $atributos['tipo'] ?? null,
                    'posicao' => $atributos['posicao'] ?? null,
                    'categoria' => $atributos['categoria'] ?? null,
                ]);
            }
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('atributos');
        });
    }
};
