<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice de busca: uma linha por (peça, código que a identifica) — o próprio
     * `codigo` da peça, mais cada código individual dentro de `conversoes` (já
     * normalizado: minúsculo e sem espaço nas pontas). Existe só pra tornar "quem tem
     * esse código" uma igualdade indexada em vez de um LIKE em cima de JSON
     * convertido pra texto — ver App\Services\PartEquivalence\RebuildPartEquivalences.
     */
    public function up(): void
    {
        Schema::create('part_reference_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->timestamps();

            $table->unique(['part_id', 'token']);
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_reference_codes');
    }
};
