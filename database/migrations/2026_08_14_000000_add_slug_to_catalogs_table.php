<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        foreach (DB::table('catalogs')->orderBy('id')->get(['id', 'name']) as $catalog) {
            $slug = Str::slug($catalog->name);

            if (DB::table('catalogs')->where('slug', $slug)->exists()) {
                $slug .= '-'.$catalog->id;
            }

            DB::table('catalogs')->where('id', $catalog->id)->update(['slug' => $slug]);
        }

        Schema::table('catalogs', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
