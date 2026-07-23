<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda a ordem de preferência escolhida pelo cliente pra cada fabricante de um
     * item — sem essa coluna não haveria como reconstituir a ordem de seleção depois
     * que ela é salva no pivot.
     */
    public function up(): void
    {
        Schema::table('order_item_manufacturer', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('manufacturer_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_manufacturer', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
