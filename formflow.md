# FormFlow — Branch Form Request Management System

> A Laravel 11 application that allows 18 branches to request physical forms from the main branch, with a full approval workflow and PDF billing/invoicing.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [Migrations](#migrations)
- [Models](#models)
- [Services](#services)
- [Policies](#policies)
- [Form Request Validators](#form-request-validators)
- [Controllers](#controllers)
- [API Routes](#api-routes)
- [Seeders](#seeders)
- [PDF Invoice Template](#pdf-invoice-template)
- [Feature Tests](#feature-tests)
- [composer.json](#composerjson)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.x |
| MySQL / PostgreSQL | 8.0+ / 15+ |
| barryvdh/laravel-dompdf | ^3.0 |
| Laravel Sanctum | ^4.0 |

---

## Installation

```bash
# 1. Clone and install
git clone https://github.com/your-org/formflow.git
cd formflow
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure .env
DB_DATABASE=formflow
DB_USERNAME=root
DB_PASSWORD=secret

# 4. Migrate and seed
php artisan migrate --seed

# 5. Install PDF package
composer require barryvdh/laravel-dompdf

# 6. Serve
php artisan serve
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BranchController.php
│   │       ├── DashboardController.php
│   │       ├── FormOrderController.php
│   │       ├── FormTypeController.php
│   │       └── InvoiceController.php
│   └── Requests/
│       ├── GenerateInvoiceRequest.php
│       └── StoreFormOrderRequest.php
├── Models/
│   ├── Branch.php
│   ├── FormOrder.php
│   ├── FormOrderItem.php
│   ├── FormType.php
│   ├── Invoice.php
│   └── User.php
├── Policies/
│   └── FormOrderPolicy.php
└── Services/
    ├── FormOrderService.php
    ├── InvoiceService.php
    └── PdfService.php
database/
├── migrations/
│   ├── 2026_01_01_000001_create_branches_table.php
│   ├── 2026_01_01_000002_create_form_types_table.php
│   ├── 2026_01_01_000003_add_branch_id_to_users_table.php
│   ├── 2026_01_01_000004_create_invoices_table.php
│   ├── 2026_01_01_000005_create_form_orders_table.php
│   └── 2026_01_01_000006_create_form_order_items_table.php
└── seeders/
    ├── BranchSeeder.php
    ├── DatabaseSeeder.php
    ├── FormTypeSeeder.php
    └── UserSeeder.php
resources/
└── views/
    └── billing/
        └── invoice.blade.php
routes/
├── api.php
└── web.php
tests/
└── Feature/
    └── FormOrderWorkflowTest.php
```

---

## Database Schema

```
branches          — 18 branches + 1 main branch
form_types        — catalogue of requestable form types with unit prices
users             — auth users linked to a branch (or admin)
form_orders       — the core request record
form_order_items  — line items per order (supports multi-type requests)
invoices          — generated billing documents per branch per period
```

### Order Lifecycle

```
pending → approved → in_transit → delivered → billed
               └──→ rejected
```

### Roles

| Role | Abilities |
|---|---|
| `branch_staff` | Create and view their own orders |
| `branch_manager` | Create, view, and cancel their branch's orders |
| `admin` | Full access: approve, deliver, bill, generate invoices |

---

## Migrations

### 1. Create Branches Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();           // BR-001
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->boolean('is_main_branch')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
```

### 2. Create Form Types Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // WS-001
            $table->string('name');                  // Withdrawal Slip
            $table->text('description')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->string('unit_label')->default('piece');
            $table->integer('minimum_order')->default(1);
            $table->integer('maximum_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_types');
    }
};
```

### 3. Add Branch ID to Users Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('email')
                ->constrained('branches')
                ->nullOnDelete();

            $table->string('role')->default('branch_staff');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
        });
    }
};
```

### 4. Create Invoices Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();  // INV-2026-05-BR003
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('generated_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('billing_period');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue'])->default('draft');
            $table->decimal('subtotal', 14, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(12.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
```

### 5. Create Form Orders Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique(); // ORD-2026-05-00001
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('status', [
                'pending', 'approved', 'rejected',
                'in_transit', 'delivered', 'billed',
            ])->default('pending')->index();
            $table->enum('priority', ['low', 'normal', 'urgent'])->default('normal');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('billed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('needed_by')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_orders');
    }
};
```

### 6. Create Form Order Items Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_order_id')
                ->constrained('form_orders')
                ->cascadeOnDelete();
            $table->foreignId('form_type_id')
                ->constrained('form_types')
                ->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);   // snapshot at time of order
            $table->decimal('line_total', 12, 2);    // quantity × unit_price
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_order_items');
    }
};
```

---

## Models

### Branch

```php
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
        return (float) $this->formOrders()
            ->where('status', 'billed')
            ->sum('total_amount');
    }
}
```

### FormType

```php
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
```

### FormOrder

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormOrder extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING    = 'pending';
    const STATUS_APPROVED   = 'approved';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_BILLED     = 'billed';

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_URGENT = 'urgent';

    const TAX_RATE = 0.12;

    protected $fillable = [
        'reference_number', 'branch_id', 'requested_by',
        'status', 'priority', 'notes', 'rejection_reason',
        'approved_by', 'delivered_by', 'billed_by',
        'approved_at', 'delivered_at', 'billed_at', 'needed_by',
        'subtotal', 'tax_amount', 'total_amount', 'invoice_id',
    ];

    protected $casts = [
        'approved_at'  => 'datetime',
        'delivered_at' => 'datetime',
        'billed_at'    => 'datetime',
        'needed_by'    => 'datetime',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Auto-generate reference number on create
    protected static function booted(): void
    {
        static::creating(function (FormOrder $order) {
            if (! $order->reference_number) {
                $order->reference_number = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        $prefix = 'ORD-' . now()->format('Y-m');
        $last   = static::withTrashed()
            ->where('reference_number', 'like', $prefix . '%')
            ->count();

        return $prefix . '-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    // Relationships
    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
    public function deliverer(): BelongsTo { return $this->belongsTo(User::class, 'delivered_by'); }
    public function biller(): BelongsTo    { return $this->belongsTo(User::class, 'billed_by'); }
    public function invoice(): BelongsTo   { return $this->belongsTo(Invoice::class); }
    public function items(): HasMany       { return $this->hasMany(FormOrderItem::class); }

    // Scopes
    public function scopePending($query)             { return $query->where('status', self::STATUS_PENDING); }
    public function scopeApproved($query)            { return $query->where('status', self::STATUS_APPROVED); }
    public function scopeDelivered($query)           { return $query->where('status', self::STATUS_DELIVERED); }
    public function scopeBilled($query)              { return $query->where('status', self::STATUS_BILLED); }
    public function scopeForBranch($query, int $id)  { return $query->where('branch_id', $id); }
    public function scopeUrgent($query)              { return $query->where('priority', self::PRIORITY_URGENT); }

    // Status helpers
    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool   { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool   { return $this->status === self::STATUS_REJECTED; }
    public function isDelivered(): bool  { return $this->status === self::STATUS_DELIVERED; }
    public function isBilled(): bool     { return $this->status === self::STATUS_BILLED; }
    public function isUrgent(): bool     { return $this->priority === self::PRIORITY_URGENT; }

    public function canBeApproved(): bool  { return $this->isPending(); }
    public function canBeRejected(): bool  { return $this->isPending(); }
    public function canBeDelivered(): bool { return $this->isApproved() || $this->status === self::STATUS_IN_TRANSIT; }
    public function canBeBilled(): bool    { return $this->isDelivered(); }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items->sum('line_total');

        $this->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => round($subtotal * self::TAX_RATE, 2),
            'total_amount' => round($subtotal * (1 + self::TAX_RATE), 2),
        ]);
    }
}
```

### FormOrderItem

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormOrderItem extends Model
{
    protected $fillable = [
        'form_order_id', 'form_type_id',
        'quantity', 'unit_price', 'line_total', 'notes',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class, 'form_order_id');
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }

    // Auto-calculate line_total on save
    protected static function booted(): void
    {
        static::saving(function (FormOrderItem $item) {
            $item->line_total = round($item->quantity * $item->unit_price, 2);
        });
    }
}
```

### Invoice

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    const STATUS_DRAFT   = 'draft';
    const STATUS_SENT    = 'sent';
    const STATUS_PAID    = 'paid';
    const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'invoice_number', 'branch_id', 'generated_by',
        'billing_period', 'period_start', 'period_end', 'due_date',
        'status', 'subtotal', 'tax_rate', 'tax_amount', 'total_amount',
        'pdf_path', 'sent_at', 'paid_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'due_date'     => 'date',
        'subtotal'     => 'decimal:2',
        'tax_rate'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
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
```

### User

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    const ROLE_BRANCH_STAFF   = 'branch_staff';
    const ROLE_BRANCH_MANAGER = 'branch_manager';
    const ROLE_ADMIN          = 'admin';

    protected $fillable = [
        'name', 'email', 'password',
        'branch_id', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function branch(): BelongsTo        { return $this->belongsTo(Branch::class); }
    public function formOrders(): HasMany       { return $this->hasMany(FormOrder::class, 'requested_by'); }
    public function generatedInvoices(): HasMany { return $this->hasMany(Invoice::class, 'generated_by'); }

    public function isAdmin(): bool         { return $this->role === self::ROLE_ADMIN; }
    public function isBranchManager(): bool { return $this->role === self::ROLE_BRANCH_MANAGER; }
    public function isBranchStaff(): bool   { return $this->role === self::ROLE_BRANCH_STAFF; }
    public function isBranchUser(): bool    { return in_array($this->role, [self::ROLE_BRANCH_STAFF, self::ROLE_BRANCH_MANAGER]); }
    public function belongsToBranch(int $branchId): bool { return $this->branch_id === $branchId; }
}
```

---

## Services

### FormOrderService

```php
<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\FormOrderItem;
use App\Models\FormType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FormOrderService
{
    /**
     * Create a new form order with its line items.
     *
     * @param  array{
     *   branch_id: int,
     *   priority: string,
     *   notes: string|null,
     *   needed_by: string|null,
     *   items: array<array{form_type_id: int, quantity: int, notes: string|null}>
     * } $data
     */
    public function create(array $data, User $requestedBy): FormOrder
    {
        return DB::transaction(function () use ($data, $requestedBy) {
            $order = FormOrder::create([
                'branch_id'    => $data['branch_id'],
                'requested_by' => $requestedBy->id,
                'priority'     => $data['priority'] ?? FormOrder::PRIORITY_NORMAL,
                'notes'        => $data['notes'] ?? null,
                'needed_by'    => $data['needed_by'] ?? null,
                'status'       => FormOrder::STATUS_PENDING,
            ]);

            $this->syncItems($order, $data['items']);
            $order->recalculateTotals();

            return $order->fresh(['items.formType', 'branch', 'requester']);
        });
    }

    public function approve(FormOrder $order, User $approvedBy): FormOrder
    {
        abort_if(! $order->canBeApproved(), 422, 'This order cannot be approved in its current state.');

        $order->update([
            'status'      => FormOrder::STATUS_APPROVED,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        return $order->fresh();
    }

    public function reject(FormOrder $order, User $rejectedBy, string $reason = ''): FormOrder
    {
        abort_if(! $order->canBeRejected(), 422, 'This order cannot be rejected in its current state.');

        $order->update([
            'status'           => FormOrder::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by'      => $rejectedBy->id,
            'approved_at'      => now(),
        ]);

        return $order->fresh();
    }

    public function deliver(FormOrder $order, User $deliveredBy): FormOrder
    {
        abort_if(! $order->canBeDelivered(), 422, 'This order cannot be marked delivered in its current state.');

        $order->update([
            'status'       => FormOrder::STATUS_DELIVERED,
            'delivered_by' => $deliveredBy->id,
            'delivered_at' => now(),
        ]);

        return $order->fresh();
    }

    public function bill(FormOrder $order, User $billedBy, int $invoiceId): FormOrder
    {
        abort_if(! $order->canBeBilled(), 422, 'Only delivered orders can be billed.');

        $order->update([
            'status'     => FormOrder::STATUS_BILLED,
            'billed_by'  => $billedBy->id,
            'billed_at'  => now(),
            'invoice_id' => $invoiceId,
        ]);

        return $order->fresh();
    }

    private function syncItems(FormOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $formType = FormType::findOrFail($item['form_type_id']);

            FormOrderItem::create([
                'form_order_id' => $order->id,
                'form_type_id'  => $formType->id,
                'quantity'      => $item['quantity'],
                'unit_price'    => $formType->unit_price,
                'line_total'    => $formType->unit_price * $item['quantity'],
                'notes'         => $item['notes'] ?? null,
            ]);
        }
    }
}
```

### InvoiceService

```php
<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly FormOrderService $orderService,
    ) {}

    /**
     * Generate a consolidated invoice for a branch covering all
     * delivered-but-unbilled orders within the given period.
     */
    public function generate(array $data, User $generatedBy): Invoice
    {
        $branch      = Branch::findOrFail($data['branch_id']);
        $periodStart = Carbon::parse($data['period_start'])->startOfDay();
        $periodEnd   = Carbon::parse($data['period_end'])->endOfDay();

        $orders = FormOrder::delivered()
            ->forBranch($branch->id)
            ->whereBetween('delivered_at', [$periodStart, $periodEnd])
            ->whereNull('invoice_id')
            ->with(['items.formType'])
            ->get();

        abort_if($orders->isEmpty(), 422, 'No unbilled delivered orders found for this branch in the selected period.');

        return DB::transaction(function () use ($branch, $orders, $data, $generatedBy, $periodStart, $periodEnd) {
            $subtotal    = $orders->sum('subtotal');
            $taxRate     = 12.00;
            $taxAmount   = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = $subtotal + $taxAmount;
            $dueDate     = now()->addDays($data['due_days'] ?? 30);

            $invoice = Invoice::create([
                'branch_id'      => $branch->id,
                'generated_by'   => $generatedBy->id,
                'billing_period' => $periodStart->format('F Y'),
                'period_start'   => $periodStart->toDateString(),
                'period_end'     => $periodEnd->toDateString(),
                'due_date'       => $dueDate->toDateString(),
                'status'         => Invoice::STATUS_DRAFT,
                'subtotal'       => $subtotal,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'total_amount'   => $totalAmount,
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($orders as $order) {
                $this->orderService->bill($order, $generatedBy, $invoice->id);
            }

            $pdfPath = $this->pdfService->generateInvoice(
                $invoice->fresh(['branch', 'orders.items.formType', 'generatedBy'])
            );
            $invoice->update(['pdf_path' => $pdfPath]);

            return $invoice->fresh(['branch', 'orders']);
        });
    }

    /**
     * Summary of all billable (delivered, unbilled) orders grouped by branch.
     */
    public function getBillableSummary(): Collection
    {
        return Branch::active()
            ->notMain()
            ->withCount(['formOrders as delivered_orders_count' => fn ($q) =>
                $q->delivered()->whereNull('invoice_id')
            ])
            ->withSum(['formOrders as billable_subtotal' => fn ($q) =>
                $q->delivered()->whereNull('invoice_id')
            ], 'subtotal')
            ->having('delivered_orders_count', '>', 0)
            ->get();
    }
}
```

### PdfService

```php
<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    /**
     * Render the invoice Blade template to PDF and persist it to storage.
     * Returns the storage path, e.g. "invoices/INV-2026-05-BR001-001.pdf"
     */
    public function generateInvoice(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('billing.invoice', [
            'invoice' => $invoice,
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders->load('items.formType'),
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'dpi'                  => 150,
        ]);

        $filename = $invoice->invoice_number . '.pdf';
        $path     = 'invoices/' . $filename;

        Storage::put($path, $pdf->output());

        return $path;
    }

    /** Stream an invoice PDF directly to the browser. */
    public function streamInvoice(Invoice $invoice): \Illuminate\Http\Response
    {
        return Pdf::loadView('billing.invoice', [
            'invoice' => $invoice,
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders->load('items.formType'),
        ])
        ->setPaper('a4', 'portrait')
        ->stream($invoice->invoice_number . '.pdf');
    }

    /** Force-download an invoice PDF. */
    public function downloadInvoice(Invoice $invoice): \Illuminate\Http\Response
    {
        return Pdf::loadView('billing.invoice', [
            'invoice' => $invoice,
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders->load('items.formType'),
        ])
        ->setPaper('a4', 'portrait')
        ->download($invoice->invoice_number . '.pdf');
    }
}
```

---

## Policies

### FormOrderPolicy

```php
<?php

namespace App\Policies;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FormOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FormOrder $order): bool
    {
        if ($user->isAdmin()) return true;
        return $user->branch_id === $order->branch_id;
    }

    /** Only branch staff/managers can create orders for their own branch. */
    public function create(User $user): bool
    {
        return $user->isBranchUser() && $user->branch_id !== null;
    }

    /** Branch managers can edit pending orders on their branch. Admins can edit any pending order. */
    public function update(User $user, FormOrder $order): bool
    {
        if (! $order->isPending()) return false;
        if ($user->isAdmin()) return true;
        return $user->isBranchManager() && $user->branch_id === $order->branch_id;
    }

    public function delete(User $user, FormOrder $order): bool
    {
        if (! $order->isPending()) return false;
        if ($user->isAdmin()) return true;
        return $user->isBranchManager() && $user->branch_id === $order->branch_id;
    }

    // Workflow — admin only
    public function approve(User $user, FormOrder $order): bool  { return $user->isAdmin() && $order->canBeApproved(); }
    public function reject(User $user, FormOrder $order): bool   { return $user->isAdmin() && $order->canBeRejected(); }
    public function deliver(User $user, FormOrder $order): bool  { return $user->isAdmin() && $order->canBeDelivered(); }
    public function bill(User $user, FormOrder $order): bool     { return $user->isAdmin() && $order->canBeBilled(); }
}
```

---

## Form Request Validators

### StoreFormOrderRequest

```php
<?php

namespace App\Http\Requests;

use App\Models\FormOrder;
use App\Models\FormType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FormOrder::class);
    }

    public function rules(): array
    {
        return [
            'priority'                 => ['required', Rule::in(['low', 'normal', 'urgent'])],
            'notes'                    => ['nullable', 'string', 'max:1000'],
            'needed_by'                => ['nullable', 'date', 'after:today'],
            'items'                    => ['required', 'array', 'min:1', 'max:10'],
            'items.*.form_type_id'     => [
                'required', 'integer',
                Rule::exists('form_types', 'id')->where('is_active', true),
            ],
            'items.*.quantity'         => ['required', 'integer', 'min:1', 'max:50000'],
            'items.*.notes'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Enforce per-type minimum/maximum order quantities
            foreach ($this->items ?? [] as $index => $item) {
                if (empty($item['form_type_id']) || empty($item['quantity'])) continue;

                $formType = FormType::find($item['form_type_id']);

                if ($formType && $item['quantity'] < $formType->minimum_order) {
                    $v->errors()->add("items.{$index}.quantity",
                        "Minimum order for {$formType->name} is {$formType->minimum_order}.");
                }

                if ($formType && $formType->maximum_order && $item['quantity'] > $formType->maximum_order) {
                    $v->errors()->add("items.{$index}.quantity",
                        "Maximum order for {$formType->name} is {$formType->maximum_order}.");
                }
            }

            // No duplicate form types in a single order
            $ids = array_column($this->items ?? [], 'form_type_id');
            if (count($ids) !== count(array_unique($ids))) {
                $v->errors()->add('items', 'Duplicate form types are not allowed. Combine quantities instead.');
            }
        });
    }
}
```

### GenerateInvoiceRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'branch_id'    => ['required', 'integer', 'exists:branches,id'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'due_days'     => ['nullable', 'integer', 'min:1', 'max:90'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

---

## Controllers

### FormOrderController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormOrderRequest;
use App\Models\FormOrder;
use App\Services\FormOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormOrderController extends Controller
{
    public function __construct(private readonly FormOrderService $service)
    {
        $this->authorizeResource(FormOrder::class, 'form_order');
    }

    /**
     * GET /api/v1/form-orders
     * Admins see all orders. Branch users see only their branch's orders.
     * Filters: status, priority, branch_id (admin only), from, to, search.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = FormOrder::with(['branch', 'requester', 'items.formType'])->latest();

        if ($user->isBranchUser()) {
            $query->forBranch($user->branch_id);
        }

        $request->whenFilled('status',    fn ($v) => $query->where('status', $v));
        $request->whenFilled('priority',  fn ($v) => $query->where('priority', $v));
        $request->whenFilled('from',      fn ($v) => $query->whereDate('created_at', '>=', $v));
        $request->whenFilled('to',        fn ($v) => $query->whereDate('created_at', '<=', $v));
        $request->whenFilled('search',    fn ($v) => $query->where('reference_number', 'like', "%{$v}%"));

        if ($request->filled('branch_id') && $user->isAdmin()) {
            $query->forBranch($request->branch_id);
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    /** POST /api/v1/form-orders */
    public function store(StoreFormOrderRequest $request): JsonResponse
    {
        $order = $this->service->create(
            array_merge($request->validated(), ['branch_id' => $request->user()->branch_id]),
            $request->user()
        );

        return response()->json(['message' => 'Form order submitted successfully.', 'data' => $order], 201);
    }

    /** GET /api/v1/form-orders/{form_order} */
    public function show(FormOrder $formOrder): JsonResponse
    {
        return response()->json(
            $formOrder->load(['branch', 'requester', 'approver', 'deliverer', 'biller', 'items.formType', 'invoice'])
        );
    }

    /** DELETE /api/v1/form-orders/{form_order} (cancel) */
    public function destroy(FormOrder $formOrder): JsonResponse
    {
        $formOrder->delete();
        return response()->json(['message' => 'Order cancelled successfully.']);
    }

    /** PATCH /api/v1/form-orders/{form_order}/approve */
    public function approve(Request $request, FormOrder $formOrder): JsonResponse
    {
        $this->authorize('approve', $formOrder);
        $order = $this->service->approve($formOrder, $request->user());
        return response()->json(['message' => 'Order approved.', 'data' => $order]);
    }

    /** PATCH /api/v1/form-orders/{form_order}/reject */
    public function reject(Request $request, FormOrder $formOrder): JsonResponse
    {
        $this->authorize('reject', $formOrder);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $order = $this->service->reject($formOrder, $request->user(), $request->reason ?? '');
        return response()->json(['message' => 'Order rejected.', 'data' => $order]);
    }

    /** PATCH /api/v1/form-orders/{form_order}/deliver */
    public function deliver(Request $request, FormOrder $formOrder): JsonResponse
    {
        $this->authorize('deliver', $formOrder);
        $order = $this->service->deliver($formOrder, $request->user());
        return response()->json(['message' => 'Order marked as delivered.', 'data' => $order]);
    }
}
```

### InvoiceController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PdfService $pdfService,
    ) {}

    /** GET /api/v1/invoices */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $user  = $request->user();
        $query = Invoice::with(['branch', 'generatedBy'])->latest();

        if ($user->isBranchUser()) $query->where('branch_id', $user->branch_id);
        $request->whenFilled('status', fn ($v) => $query->where('status', $v));
        if ($request->filled('branch_id') && $user->isAdmin()) $query->where('branch_id', $request->branch_id);

        return response()->json($query->paginate(20));
    }

    /** GET /api/v1/invoices/{invoice} */
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);
        return response()->json($invoice->load(['branch', 'generatedBy', 'orders.items.formType']));
    }

    /** POST /api/v1/invoices/generate */
    public function generate(GenerateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->generate($request->validated(), $request->user());
        return response()->json([
            'message' => "Invoice {$invoice->invoice_number} generated successfully.",
            'data'    => $invoice,
        ], 201);
    }

    /** GET /api/v1/invoices/{invoice}/download */
    public function download(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        return $this->pdfService->downloadInvoice($invoice->load(['branch', 'orders.items.formType', 'generatedBy']));
    }

    /** GET /api/v1/invoices/{invoice}/preview */
    public function preview(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        return $this->pdfService->streamInvoice($invoice->load(['branch', 'orders.items.formType', 'generatedBy']));
    }

    /** PATCH /api/v1/invoices/{invoice}/mark-paid */
    public function markPaid(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);
        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
        return response()->json(['message' => 'Invoice marked as paid.', 'data' => $invoice->fresh()]);
    }

    /** GET /api/v1/invoices/billable-summary */
    public function billableSummary(): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);
        return response()->json($this->invoiceService->getBillableSummary());
    }
}
```

### DashboardController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** GET /api/v1/dashboard/stats */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        return $user->isAdmin()
            ? $this->adminStats()
            : $this->branchStats($user->branch_id);
    }

    private function adminStats(): JsonResponse
    {
        $statusCounts = FormOrder::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        $branchActivity = Branch::active()->notMain()
            ->withCount(['formOrders as total_orders'])
            ->withCount(['formOrders as pending_orders' => fn ($q) => $q->pending()])
            ->withSum(['formOrders as total_billed' => fn ($q) => $q->billed()], 'total_amount')
            ->orderByDesc('total_orders')->get();

        $monthlyBilling = Invoice::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('SUM(total_amount) as total'),
            DB::raw('COUNT(*) as count')
        )->groupBy('month')->orderBy('month')->limit(12)->get();

        return response()->json([
            'status_counts'   => $statusCounts,
            'branch_activity' => $branchActivity,
            'recent_orders'   => FormOrder::with(['branch', 'requester'])->latest()->limit(10)->get(),
            'monthly_billing' => $monthlyBilling,
            'urgent_pending'  => FormOrder::pending()->urgent()->with('branch')->latest()->get(),
            'totals' => [
                'orders_today'     => FormOrder::whereDate('created_at', today())->count(),
                'pending_count'    => $statusCounts['pending'] ?? 0,
                'total_billed_ytd' => Invoice::whereYear('created_at', now()->year)->sum('total_amount'),
                'active_branches'  => Branch::active()->notMain()->count(),
            ],
        ]);
    }

    private function branchStats(int $branchId): JsonResponse
    {
        $statusCounts = FormOrder::forBranch($branchId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'status_counts'  => $statusCounts,
            'recent_orders'  => FormOrder::forBranch($branchId)->with(['items.formType'])->latest()->limit(5)->get(),
            'totals' => [
                'pending_count'     => $statusCounts['pending'] ?? 0,
                'delivered_count'   => ($statusCounts['delivered'] ?? 0) + ($statusCounts['billed'] ?? 0),
                'total_billed'      => FormOrder::forBranch($branchId)->billed()->sum('total_amount'),
                'orders_this_month' => FormOrder::forBranch($branchId)->whereMonth('created_at', now()->month)->count(),
            ],
        ]);
    }
}
```

---

## API Routes

```php
<?php

// routes/api.php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FormOrderController;
use App\Http\Controllers\Api\FormTypeController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    Route::get('/me', fn (Request $r) => $r->user()->load('branch'));

    // Reference data
    Route::get('/branches',           [BranchController::class, 'index']);
    Route::get('/branches/{branch}',  [BranchController::class, 'show']);
    Route::get('/form-types',         [FormTypeController::class, 'index']);
    Route::get('/form-types/{formType}', [FormTypeController::class, 'show']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Form orders CRUD + workflow
    Route::apiResource('form-orders', FormOrderController::class);
    Route::patch('/form-orders/{form_order}/approve', [FormOrderController::class, 'approve']);
    Route::patch('/form-orders/{form_order}/reject',  [FormOrderController::class, 'reject']);
    Route::patch('/form-orders/{form_order}/deliver', [FormOrderController::class, 'deliver']);

    // Invoices / billing
    Route::get('/invoices',                        [InvoiceController::class, 'index']);
    Route::post('/invoices/generate',              [InvoiceController::class, 'generate']);
    Route::get('/invoices/billable-summary',       [InvoiceController::class, 'billableSummary']);
    Route::get('/invoices/{invoice}',              [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/download',     [InvoiceController::class, 'download']);
    Route::get('/invoices/{invoice}/preview',      [InvoiceController::class, 'preview']);
    Route::patch('/invoices/{invoice}/mark-paid',  [InvoiceController::class, 'markPaid']);
});
```

---

## Seeders

### BranchSeeder

```php
<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'BR-000', 'name' => 'Main Branch — FormFlow HQ',  'city' => 'Manila',      'is_main_branch' => true],
            ['code' => 'BR-001', 'name' => 'North Makati Branch',         'city' => 'Makati'],
            ['code' => 'BR-002', 'name' => 'South Makati Branch',         'city' => 'Makati'],
            ['code' => 'BR-003', 'name' => 'Quezon City Central Branch',  'city' => 'Quezon City'],
            ['code' => 'BR-004', 'name' => 'Mandaluyong Branch',          'city' => 'Mandaluyong'],
            ['code' => 'BR-005', 'name' => 'Pasig Branch',                'city' => 'Pasig'],
            ['code' => 'BR-006', 'name' => 'Taguig Branch',               'city' => 'Taguig'],
            ['code' => 'BR-007', 'name' => 'Marikina Branch',             'city' => 'Marikina'],
            ['code' => 'BR-008', 'name' => 'Parañaque Branch',            'city' => 'Parañaque'],
            ['code' => 'BR-009', 'name' => 'Las Piñas Branch',            'city' => 'Las Piñas'],
            ['code' => 'BR-010', 'name' => 'Muntinlupa Branch',           'city' => 'Muntinlupa'],
            ['code' => 'BR-011', 'name' => 'Caloocan Branch',             'city' => 'Caloocan'],
            ['code' => 'BR-012', 'name' => 'Malabon Branch',              'city' => 'Malabon'],
            ['code' => 'BR-013', 'name' => 'Valenzuela Branch',           'city' => 'Valenzuela'],
            ['code' => 'BR-014', 'name' => 'Navotas Branch',              'city' => 'Navotas'],
            ['code' => 'BR-015', 'name' => 'Pasay Branch',                'city' => 'Pasay'],
            ['code' => 'BR-016', 'name' => 'Pateros Branch',              'city' => 'Pateros'],
            ['code' => 'BR-017', 'name' => 'San Juan Branch',             'city' => 'San Juan'],
            ['code' => 'BR-018', 'name' => 'Manila Central Branch',       'city' => 'Manila'],
        ];

        foreach ($branches as $data) {
            Branch::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
```

### FormTypeSeeder

```php
<?php

namespace Database\Seeders;

use App\Models\FormType;
use Illuminate\Database\Seeder;

class FormTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'WS-001',  'name' => 'Withdrawal Slip',       'unit_price' => 2.50,  'minimum_order' => 100],
            ['code' => 'DS-001',  'name' => 'Deposit Slip',           'unit_price' => 2.50,  'minimum_order' => 100],
            ['code' => 'AO-001',  'name' => 'Account Opening Form',   'unit_price' => 12.00, 'minimum_order' => 10],
            ['code' => 'LA-001',  'name' => 'Loan Application Form',  'unit_price' => 25.00, 'minimum_order' => 10],
            ['code' => 'FT-001',  'name' => 'Fund Transfer Form',     'unit_price' => 5.00,  'minimum_order' => 50],
            ['code' => 'CR-001',  'name' => 'Cheque Requisition Form','unit_price' => 8.00,  'minimum_order' => 25],
            ['code' => 'ATM-001', 'name' => 'ATM Application Form',   'unit_price' => 15.00, 'minimum_order' => 10],
            ['code' => 'AC-001',  'name' => 'Account Closure Form',   'unit_price' => 10.00, 'minimum_order' => 10],
            ['code' => 'SC-001',  'name' => 'Signature Card',         'unit_price' => 5.00,  'minimum_order' => 50],
            ['code' => 'KYC-001', 'name' => 'KYC Update Form',        'unit_price' => 8.00,  'minimum_order' => 25],
        ];

        foreach ($types as $data) {
            FormType::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
```

### UserSeeder

```php
<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::where('is_main_branch', true)->first();

        User::firstOrCreate(['email' => 'admin@formflow.ph'], [
            'name'      => 'System Administrator',
            'password'  => Hash::make('password'),
            'branch_id' => $mainBranch?->id,
            'role'      => User::ROLE_ADMIN,
        ]);

        foreach (Branch::active()->notMain()->get() as $branch) {
            $slug = strtolower(str_replace([' ', '-'], '_', $branch->code));

            User::firstOrCreate(['email' => "manager.{$slug}@formflow.ph"], [
                'name'      => "Manager — {$branch->name}",
                'password'  => Hash::make('password'),
                'branch_id' => $branch->id,
                'role'      => User::ROLE_BRANCH_MANAGER,
            ]);

            User::firstOrCreate(['email' => "staff.{$slug}@formflow.ph"], [
                'name'      => "Staff — {$branch->name}",
                'password'  => Hash::make('password'),
                'branch_id' => $branch->id,
                'role'      => User::ROLE_BRANCH_STAFF,
            ]);
        }
    }
}
```

### DatabaseSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            FormTypeSeeder::class,
            UserSeeder::class,
        ]);
    }
}
```

---

## PDF Invoice Template

```blade
{{-- resources/views/billing/invoice.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1a1a2e; }

  .header { background: #185FA5; color: #fff; padding: 28px 40px; }
  .header-row { display: table; width: 100%; }
  .header-left, .header-right { display: table-cell; vertical-align: middle; }
  .header-right { text-align: right; }
  .org-name { font-size: 20px; font-weight: bold; }
  .invoice-label { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
  .invoice-number { font-size: 12px; opacity: .75; font-family: monospace; }

  .meta-section { padding: 20px 40px; background: #f8f9fc; border-bottom: 1px solid #e8e8e8; }
  .meta-table td { width: 25%; padding-right: 16px; vertical-align: top; }
  .meta-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #888; }
  .meta-value { font-size: 12px; font-weight: bold; }

  .address-section { padding: 18px 40px; border-bottom: 1px solid #e8e8e8; }
  .address-table td { width: 50%; vertical-align: top; padding-right: 24px; }
  .address-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #888;
                   border-bottom: 2px solid #185FA5; padding-bottom: 4px; margin-bottom: 8px; }
  .address-name { font-size: 13px; font-weight: bold; }

  .items-section { padding: 18px 40px; }
  .items-table { width: 100%; border-collapse: collapse; }
  .items-table thead tr { background: #185FA5; color: #fff; }
  .items-table thead th { padding: 9px 12px; font-size: 10px; text-transform: uppercase; text-align: left; }
  .items-table tbody tr:nth-child(even) { background: #f5f7fb; }
  .items-table tbody td { padding: 9px 12px; font-size: 11px; border-bottom: 1px solid #eee; }
  .text-right { text-align: right; }

  .totals-section { padding: 0 40px 20px; text-align: right; }
  .totals-box { display: inline-block; width: 260px; border: 1px solid #dde3f0; border-radius: 6px; }
  .totals-row { display: table; width: 100%; padding: 7px 14px; border-bottom: 1px solid #eee; }
  .totals-row:last-child { background: #185FA5; color: #fff; border-bottom: none; }
  .totals-label, .totals-value { display: table-cell; font-size: 11px; }
  .totals-value { text-align: right; font-weight: bold; }

  .footer { margin-top: 24px; padding: 14px 40px; background: #f8f9fc;
            border-top: 1px solid #dde3f0; font-size: 10px; color: #888; text-align: center; }
</style>
</head>
<body>

<div class="header">
  <div class="header-row">
    <div class="header-left">
      <div class="org-name">FormFlow</div>
      <div style="font-size:11px;opacity:.8">Main Branch — Supply &amp; Logistics Division</div>
    </div>
    <div class="header-right">
      <div class="invoice-label">INVOICE</div>
      <div class="invoice-number">{{ $invoice->invoice_number }}</div>
    </div>
  </div>
</div>

<div class="meta-section">
  <table class="meta-table"><tr>
    <td><div class="meta-label">Invoice Date</div><div class="meta-value">{{ $invoice->created_at->format('d M Y') }}</div></td>
    <td><div class="meta-label">Billing Period</div><div class="meta-value">{{ $invoice->billing_period }}</div></td>
    <td><div class="meta-label">Due Date</div><div class="meta-value">{{ $invoice->due_date->format('d M Y') }}</div></td>
    <td><div class="meta-label">Status</div><div class="meta-value">{{ strtoupper($invoice->status) }}</div></td>
  </tr></table>
</div>

<div class="address-section">
  <table class="address-table"><tr>
    <td>
      <div class="address-label">Bill From</div>
      <div class="address-name">FormFlow — Main Branch</div>
      <div style="font-size:11px;color:#555">Supply &amp; Logistics Division<br>billing@formflow.ph</div>
    </td>
    <td>
      <div class="address-label">Bill To</div>
      <div class="address-name">{{ $branch->name }}</div>
      <div style="font-size:11px;color:#555">
        {{ $branch->code }}@if($branch->city)<br>{{ $branch->city }}@endif
        @if($branch->contact_email)<br>{{ $branch->contact_email }}@endif
      </div>
    </td>
  </tr></table>
</div>

<div class="items-section">
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:110px">Reference</th>
        <th>Form Type</th>
        <th class="text-right" style="width:70px">Qty</th>
        <th class="text-right" style="width:90px">Unit Price</th>
        <th class="text-right" style="width:100px">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($orders as $order)
        @foreach($order->items as $item)
          <tr>
            <td style="font-family:monospace;font-size:10px;color:#666">{{ $order->reference_number }}</td>
            <td>{{ $item->formType->name }}</td>
            <td class="text-right">{{ number_format($item->quantity) }}</td>
            <td class="text-right">₱{{ number_format($item->unit_price, 2) }}</td>
            <td class="text-right"><strong>₱{{ number_format($item->line_total, 2) }}</strong></td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>
</div>

<div class="totals-section">
  <div class="totals-box">
    <div class="totals-row">
      <span class="totals-label">Subtotal</span>
      <span class="totals-value">₱{{ number_format($invoice->subtotal, 2) }}</span>
    </div>
    <div class="totals-row">
      <span class="totals-label">VAT ({{ number_format($invoice->tax_rate, 0) }}%)</span>
      <span class="totals-value">₱{{ number_format($invoice->tax_amount, 2) }}</span>
    </div>
    <div class="totals-row">
      <span class="totals-label">Total Due</span>
      <span class="totals-value">₱{{ number_format($invoice->total_amount, 2) }}</span>
    </div>
  </div>
</div>

@if($invoice->notes)
  <div style="padding: 0 40px 16px">
    <div style="background:#fffbec;border-left:3px solid #EF9F27;padding:10px 14px;font-size:11px;color:#555">
      <strong>Notes:</strong> {{ $invoice->notes }}
    </div>
  </div>
@endif

<div class="footer">
  Generated by FormFlow on {{ $invoice->created_at->format('d M Y H:i') }}
  by {{ $invoice->generatedBy->name ?? 'System' }}.
  Please settle on or before <strong>{{ $invoice->due_date->format('d M Y') }}</strong>.
  Disputes: <strong>billing@formflow.ph</strong>
</div>

</body>
</html>
```

---

## Feature Tests

```php
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\FormType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $branchManager;
    private User $branchStaff;
    private FormType $withdrawalSlip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $mainBranch   = Branch::where('is_main_branch', true)->first();
        $this->branch = Branch::where('code', 'BR-001')->first();

        $this->admin = User::factory()->create([
            'branch_id' => $mainBranch->id,
            'role'      => User::ROLE_ADMIN,
        ]);
        $this->branchManager = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role'      => User::ROLE_BRANCH_MANAGER,
        ]);
        $this->branchStaff = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role'      => User::ROLE_BRANCH_STAFF,
        ]);

        $this->withdrawalSlip = FormType::where('code', 'WS-001')->first();
    }

    public function test_branch_staff_can_submit_order(): void
    {
        $this->actingAs($this->branchStaff)
            ->postJson('/api/v1/form-orders', [
                'priority' => 'normal',
                'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 500]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_quantity_must_meet_minimum(): void
    {
        $this->actingAs($this->branchStaff)
            ->postJson('/api/v1/form-orders', [
                'priority' => 'normal',
                'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 5]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_duplicate_form_types_are_rejected(): void
    {
        $this->actingAs($this->branchStaff)
            ->postJson('/api/v1/form-orders', [
                'priority' => 'normal',
                'items'    => [
                    ['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 100],
                    ['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 200],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_admin_can_approve_pending_order(): void
    {
        $order = $this->createPendingOrder();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/form-orders/{$order->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertNotNull($order->fresh()->approved_at);
    }

    public function test_branch_staff_cannot_approve_orders(): void
    {
        $order = $this->createPendingOrder();

        $this->actingAs($this->branchStaff)
            ->patchJson("/api/v1/form-orders/{$order->id}/approve")
            ->assertForbidden();
    }

    public function test_admin_can_reject_with_reason(): void
    {
        $order = $this->createPendingOrder();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/form-orders/{$order->id}/reject", [
                'reason' => 'Insufficient stock at main branch.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_branch_user_only_sees_own_branch_orders(): void
    {
        $this->createPendingOrder();

        $otherBranch = Branch::where('code', 'BR-002')->first();
        $otherUser   = User::factory()->create(['branch_id' => $otherBranch->id, 'role' => User::ROLE_BRANCH_STAFF]);
        $this->createPendingOrder($otherUser);

        $response = $this->actingAs($this->branchStaff)->getJson('/api/v1/form-orders')->assertOk();

        $branchIds = collect($response->json('data'))->pluck('branch_id')->unique();
        $this->assertCount(1, $branchIds);
        $this->assertEquals($this->branch->id, $branchIds->first());
    }

    public function test_order_totals_are_calculated_correctly(): void
    {
        $response = $this->actingAs($this->branchStaff)
            ->postJson('/api/v1/form-orders', [
                'priority' => 'normal',
                'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 1000]],
            ])
            ->assertCreated();

        $order = FormOrder::find($response->json('data.id'));

        // 1000 × ₱2.50 = ₱2,500 + 12% VAT = ₱2,800
        $this->assertEquals(2500.00, (float) $order->subtotal);
        $this->assertEquals(300.00,  (float) $order->tax_amount);
        $this->assertEquals(2800.00, (float) $order->total_amount);
    }

    private function createPendingOrder(?User $user = null): FormOrder
    {
        $user ??= $this->branchStaff;

        $response = $this->actingAs($user)->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 500]],
        ]);

        return FormOrder::find($response->json('data.id'));
    }
}
```

---

## composer.json

```json
{
    "name": "your-org/formflow",
    "description": "Branch Form Request Management System",
    "license": "proprietary",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "laravel/sanctum": "^4.0",
        "laravel/tinker": "^2.9",
        "barryvdh/laravel-dompdf": "^3.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.13",
        "laravel/sail": "^1.26",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.0",
        "phpunit/phpunit": "^11.0",
        "laravel/telescope": "^5.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ]
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

> **Default Credentials after seeding**
>
> | Role | Email | Password |
> |---|---|---|
> | Admin | `admin@formflow.ph` | `password` |
> | Branch Manager (BR-001) | `manager.br_001@formflow.ph` | `password` |
> | Branch Staff (BR-001) | `staff.br_001@formflow.ph` | `password` |
