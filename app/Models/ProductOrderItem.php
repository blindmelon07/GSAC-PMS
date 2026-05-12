<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOrderItem extends Model
{
    protected $fillable = [
        'product_order_id', 'product_id',
        'quantity', 'unit_price', 'line_total',
        'customizations', 'notes',
    ];

    protected $casts = [
        'unit_price'     => 'decimal:2',
        'line_total'     => 'decimal:2',
        'quantity'       => 'integer',
        'customizations' => 'array',
    ];

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
