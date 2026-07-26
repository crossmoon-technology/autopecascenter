<?php

namespace App\Models;

use App\Models\Order\Enums\Status;
use database\factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'seller_id', 'quotation_id', 'notes', 'status'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * O vendedor pra quem esse pedido foi feito — obrigatório desde que um cliente passou
     * a poder ter mais de um vendedor (ver User::sellers()), já que nesse cenário não dá
     * mais pra inferir isso só a partir de quem fez o pedido.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function scopeForSeller(Builder $query, User $seller): Builder
    {
        return $query->where('seller_id', $seller->id);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * A cotação (do vendedor) montada a partir das peças desse pedido — só pode ser
     * marcado Finalizado depois de associada a uma (ver App\Filament\Pages\Buscas\Orders).
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
