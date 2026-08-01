<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $files = DB::table('informativos')->pluck('file')->filter()->all();

        if ($files !== []) {
            Storage::disk('public')->delete($files);
        }

        Schema::dropIfExists('informativos');
    }

    /**
     * Reverse the migrations.
     *
     * Só recria a estrutura da tabela — os registros e os arquivos apagados em up() não
     * têm como ser restaurados por essa migration.
     */
    public function down(): void
    {
        Schema::create('informativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->string('file');
            $table->string('original_name')->nullable();
            $table->string('type');
            $table->timestamps();
        });
    }
};
