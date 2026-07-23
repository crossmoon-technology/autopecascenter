<?php

namespace App\Models;

use App\Models\Order\Enums\Status;
use database\factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'quotation_id', 'notes', 'status'])]
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
