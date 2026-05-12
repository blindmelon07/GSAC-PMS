<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    const TYPE_RESTOCK           = 'restock';
    const TYPE_ADJUSTMENT        = 'adjustment';
    const TYPE_ORDER_FULFILLMENT = 'order_fulfillment';

    protected $fillable = [
        'inventory_id',
        'type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'reference',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after'  => 'integer',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
