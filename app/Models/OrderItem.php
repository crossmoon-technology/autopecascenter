<?php

namespace App\Models;

use database\factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['order_id', 'description', 'quantity'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * A ordem de retorno reflete a ordem de preferência escolhida pelo cliente (ver
     * `position` no pivot, preenchida em CreateOrder::save()) — não é só uma lista.
     */
    public function preferredManufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class, 'order_item_manufacturer')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
