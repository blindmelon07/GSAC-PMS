<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
