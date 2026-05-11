<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT   = 'draft';
    const STATUS_SENT    = 'sent';
    const STATUS_PAID    = 'paid';
    const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'invoice_number', 'branch_id', 'generated_by',
        'billing_period', 'period_start', 'period_end', 'due_date',
        'status', 'subtotal', 'tax_rate', 'discount_rate', 'discount_amount', 'tax_amount', 'total_amount',
        'pdf_path', 'sent_at', 'paid_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'due_date'     => 'date',
        'subtotal'     => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'discount_rate'  => 'decimal:2',
        'discount_amount'=> 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sent_at'      => 'datetime',
        'paid_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (! $invoice->invoice_number) {
                $invoice->invoice_number = static::generateNumber($invoice->branch_id);
            }
        });
    }

    public static function generateNumber(int $branchId): string
    {
        $branch = Branch::find($branchId);
        $prefix = 'INV-' . now()->format('Y-m') . '-' . ($branch?->code ?? 'BR000');
        $count  = static::withTrashed()
            ->where('invoice_number', 'like', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    public function branch(): BelongsTo      { return $this->belongsTo(Branch::class); }
    public function generatedBy(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
    public function orders(): HasMany        { return $this->hasMany(FormOrder::class); }

    public function isDraft(): bool   { return $this->status === self::STATUS_DRAFT; }
    public function isSent(): bool    { return $this->status === self::STATUS_SENT; }
    public function isPaid(): bool    { return $this->status === self::STATUS_PAID; }
    public function isOverdue(): bool { return $this->status === self::STATUS_OVERDUE; }
}
