<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Troca o status binário (open/closed) por três estados que o vendedor alterna
     * livremente: pending (default), processing, finished. Pedidos existentes migram
     * pro equivalente mais próximo: open -> pending, closed -> finished.
     */
    public function up(): void
    {
        DB::table('orders')->where('status', 'open')->update(['status' => 'pending']);
        DB::table('orders')->where('status', 'closed')->update(['status' => 'finished']);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'finished')->update(['status' => 'closed']);
        DB::table('orders')->where('status', 'processing')->update(['status' => 'open']);
        DB::table('orders')->where('status', 'pending')->update(['status' => 'open']);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }
};
