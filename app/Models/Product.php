<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    const CATEGORY_PAPER   = 'paper';
    const CATEGORY_WRITING = 'writing';
    const CATEGORY_FILING  = 'filing';
    const CATEGORY_GENERAL = 'general';

    const CATEGORIES = [
        self::CATEGORY_PAPER,
        self::CATEGORY_WRITING,
        self::CATEGORY_FILING,
        self::CATEGORY_GENERAL,
    ];

    protected $fillable = [
        'code', 'name', 'description', 'category',
        'unit_price', 'unit_label',
        'minimum_order', 'maximum_order',
        'customizations', 'is_active',
    ];

    protected $casts = [
        'unit_price'     => 'decimal:2',
        'minimum_order'  => 'integer',
        'maximum_order'  => 'integer',
        'customizations' => 'array',
        'is_active'      => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function hasCustomizations(): bool
    {
        return ! empty($this->customizations);
    }

    public function formatPrice(): string
    {
        return '₱' . number_format($this->unit_price, 2);
    }
}
