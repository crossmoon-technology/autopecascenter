<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table): void {
            $table->string('update_file')->nullable()->after('file');
        });
    }

    public function down(): void
    {
        Schema::table('catalogs', function (Blueprint $table): void {
            $table->dropColumn('update_file');
        });
    }
};
