<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * O token só passa a existir quando o vendedor clica em "Gerar link" na página do
     * cliente (ver App\Filament\Pages\Buscas\ViewOrderLink) — não é mais gerado
     * automaticamente ao criar o registro (ver App\Filament\Pages\Buscas\OrderLinks),
     * então a coluna precisa aceitar nulo. Schema::table (não SQL cru) pra funcionar
     * igual em pgsql (dev) e sqlite (testes, ver phpunit.xml).
     */
    public function up(): void
    {
        Schema::table('order_links', function (Blueprint $table): void {
            $table->string('token', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('order_links')->whereNull('token')->get()->each(function ($row): void {
            DB::table('order_links')->where('id', $row->id)->update(['token' => Str::random(48)]);
        });

        Schema::table('order_links', function (Blueprint $table): void {
            $table->string('token', 64)->nullable(false)->change();
        });
    }
};
