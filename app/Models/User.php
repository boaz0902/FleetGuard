<?php

namespace App\Models;

use App\Notifications\ResetCustomerPassword;
use App\Services\CustomerEmailVerificationService;
use App\Services\SafeMailDeliveryService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'email_verification_code_attempts' => 'integer',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function customerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class, 'author_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function sendEmailVerificationNotification(): void
    {
        app(CustomerEmailVerificationService::class)->sendCode($this);
    }

    public function sendPasswordResetNotification($token): void
    {
        app(SafeMailDeliveryService::class)->notify($this, new ResetCustomerPassword($token));
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer') || $this->role === null;
    }
}
