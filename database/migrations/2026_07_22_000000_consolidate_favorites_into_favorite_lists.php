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
     * O favoritar direto (user_favorite_parts) e as listas (favorite_lists) eram dois
     * sistemas paralelos — favoritar uma peça e "adicionar a uma lista" eram coisas
     * diferentes, e a lista nunca aparecia em lugar nenhum depois de criada. Agora só
     * existe um conceito: toda peça favoritada mora em pelo menos uma lista, e toda
     * conta tem uma "Lista padrão" que nunca pode ser apagada — é ela que recebe a
     * peça quando o usuário só clica na estrela sem escolher lista nenhuma.
     */
    public function up(): void
    {
        Schema::table('favorite_lists', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('name');
        });

        Schema::table('favorite_list_part', function (Blueprint $table) {
            $table->text('note')->nullable()->after('part_id');
        });

        // Migra quem já tinha favoritado peça pra dentro da lista padrão de cada um.
        $favorites = DB::table('user_favorite_parts')->get();

        foreach ($favorites->groupBy('user_id') as $user_id => $userFavorites) {
            $listId = DB::table('favorite_lists')->insertGetId([
                'user_id' => $user_id,
                'name' => 'Lista padrão',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($userFavorites as $favorite) {
                DB::table('favorite_list_part')->insert([
                    'favorite_list_id' => $listId,
                    'part_id' => $favorite->part_id,
                    'note' => $favorite->note,
                    'created_at' => $favorite->created_at,
                    'updated_at' => $favorite->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('user_favorite_parts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('user_favorite_parts', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'part_id']);
        });

        Schema::table('favorite_list_part', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('favorite_lists', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
