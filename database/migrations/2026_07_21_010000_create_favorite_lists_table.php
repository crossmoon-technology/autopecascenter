<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('favorite_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('favorite_list_part', function (Blueprint $table) {
            $table->foreignId('favorite_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['favorite_list_id', 'part_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_list_part');
        Schema::dropIfExists('favorite_lists');
    }
};
