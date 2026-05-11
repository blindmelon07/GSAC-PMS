<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_order_id', 'form_type_id',
        'quantity', 'unit_price', 'line_total', 'notes',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (FormOrderItem $item) {
            $item->line_total = round($item->quantity * $item->unit_price, 2);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class, 'form_order_id');
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }
}
