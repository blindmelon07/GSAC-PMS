<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $fillable = [
        'product_id',
        'quantity_on_hand',
        'reorder_level',
        'reorder_quantity',
    ];

    protected $casts = [
        'quantity_on_hand'  => 'integer',
        'reorder_level'     => 'integer',
        'reorder_quantity'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->reorder_level > 0 && $this->quantity_on_hand <= $this->reorder_level;
    }

    public function hasStock(int $qty): bool
    {
        return $this->quantity_on_hand >= $qty;
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                     ->where('reorder_level', '>', 0);
    }
}
