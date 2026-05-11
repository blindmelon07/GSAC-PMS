<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description',
        'unit_price', 'unit_label',
        'minimum_order', 'maximum_order', 'is_active',
    ];

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'minimum_order' => 'integer',
        'maximum_order' => 'integer',
        'is_active'     => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(FormOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function formatPrice(): string
    {
        return '₱' . number_format($this->unit_price, 2);
    }
}
