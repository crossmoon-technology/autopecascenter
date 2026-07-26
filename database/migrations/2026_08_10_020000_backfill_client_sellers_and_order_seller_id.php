<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copia o relacionamento único users.invited_by_id (ainda presente nesse ponto,
     * removido só na migration seguinte) pra tabela N:N nova, e propaga o mesmo vendedor
     * pros pedidos já existentes desse cliente — todo pedido antigo tinha exatamente um
     * vendedor possível (o único invited_by_id do cliente que o criou).
     */
    public function up(): void
    {
        $now = now();

        DB::statement('
            insert into client_sellers (client_id, seller_id, created_at, updated_at)
            select id, invited_by_id, ?, ?
            from users
            where role = 3 and invited_by_id is not null
        ', [$now, $now]);

        DB::statement('
            update orders
            set seller_id = users.invited_by_id
            from users
            where users.id = orders.user_id
            and users.invited_by_id is not null
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->update(['seller_id' => null]);
        DB::table('client_sellers')->truncate();
    }
};
