<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pareamento DIRETO (não transitivo) entre peças: um par só existe quando as duas
     * peças especificamente compartilham um código (próprio ou de conversoes) em
     * App\Services\PartEquivalence\RebuildPartEquivalences::TABLE_CODES. As duas
     * direções são gravadas (A->B e B->A) de propósito, pra Part::equivalentParts()
     * não precisar de OR nem de UNION pra consultar dos dois lados.
     */
    public function up(): void
    {
        Schema::create('part_equivalences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equivalent_part_id')->constrained('parts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['part_id', 'equivalent_part_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_equivalences');
    }
};
