<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'address', 'city',
        'contact_person', 'contact_email', 'contact_phone',
        'is_main_branch', 'is_active',
    ];

    protected $casts = [
        'is_main_branch' => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function formOrders(): HasMany
    {
        return $this->hasMany(FormOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotMain($query)
    {
        return $query->where('is_main_branch', false);
    }

    public function pendingOrdersCount(): int
    {
        return $this->formOrders()->where('status', 'pending')->count();
    }

    public function totalBilledAmount(): float
    {
        return (float) $this->formOrders()->where('status', 'billed')->sum('total_amount');
    }
}
