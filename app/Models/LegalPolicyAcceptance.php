<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LegalPolicyAcceptance extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'booking_id',
        'legal_policy_id',
        'legal_policy_version_id',
        'status',
        'accepted_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Policy acceptance records are immutable.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Policy acceptance records are immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LegalPolicy::class, 'legal_policy_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalPolicyVersion::class, 'legal_policy_version_id');
    }
}
