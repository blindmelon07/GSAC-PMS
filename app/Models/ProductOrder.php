<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOrder extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_DELIVERED = 'delivered';

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'reference_number', 'branch_id', 'requested_by',
        'status', 'priority', 'notes', 'rejection_reason',
        'approved_by', 'delivered_by',
        'approved_at', 'delivered_at', 'needed_by',
        'subtotal', 'total_amount',
    ];

    protected $casts = [
        'approved_at'  => 'datetime',
        'delivered_at' => 'datetime',
        'needed_by'    => 'datetime',
        'subtotal'     => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductOrder $order) {
            if (! $order->reference_number) {
                $order->reference_number = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        $prefix = 'PO-' . now()->format('Y-m');
        $last   = static::withTrashed()
            ->where('reference_number', 'like', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
    public function deliverer(): BelongsTo { return $this->belongsTo(User::class, 'delivered_by'); }
    public function items(): HasMany       { return $this->hasMany(ProductOrderItem::class); }

    public function scopePending($query)            { return $query->where('status', self::STATUS_PENDING); }
    public function scopeApproved($query)           { return $query->where('status', self::STATUS_APPROVED); }
    public function scopeDelivered($query)          { return $query->where('status', self::STATUS_DELIVERED); }
    public function scopeForBranch($query, int $id) { return $query->where('branch_id', $id); }
    public function scopeUrgent($query)             { return $query->where('priority', self::PRIORITY_URGENT); }

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool  { return $this->status === self::STATUS_REJECTED; }
    public function isDelivered(): bool { return $this->status === self::STATUS_DELIVERED; }
    public function isUrgent(): bool    { return $this->priority === self::PRIORITY_URGENT; }

    public function canBeApproved(): bool  { return $this->isPending(); }
    public function canBeRejected(): bool  { return $this->isPending(); }
    public function canBeDelivered(): bool { return $this->isApproved(); }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items->sum('line_total');

        $this->update([
            'subtotal'     => $subtotal,
            'total_amount' => $subtotal,
        ]);
    }
}
