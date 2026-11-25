<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference',
        'customer_id',
        'vehicle_id',
        'pickup_location',
        'pickup_datetime',
        'return_location',
        'return_datetime',
        'rental_duration_days',
        'daily_rate_snapshot',
        'estimated_total',
        'location_fee_snapshot',
        'location_fee_confirmed',
        'customer_notes',
        'license_front_path',
        'license_back_path',
        'license_selfie_path',
        'status',
        'admin_remarks',
    ];

    protected $hidden = [
        'license_front_path',
        'license_back_path',
        'license_selfie_path',
    ];

    protected $casts = [
        'pickup_datetime' => 'datetime',
        'return_datetime' => 'datetime',
        'checked_out_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'daily_rate_snapshot' => 'decimal:2',
        'estimated_total' => 'decimal:2',
        'location_fee_snapshot' => 'decimal:2',
        'location_fee_confirmed' => 'decimal:2',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function legalPolicyAcceptances(): HasMany
    {
        return $this->hasMany(LegalPolicyAcceptance::class);
    }

    public function testimonial(): HasOne
    {
        return $this->hasOne(Testimonial::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    public function scopeOverlappingPeriod(Builder $query, CarbonInterface $pickup, CarbonInterface $returned): Builder
    {
        return $query
            ->where('pickup_datetime', '<', $returned)
            ->where('return_datetime', '>', $pickup);
    }

    public function getStatusTransitions(): array
    {
        return [
            self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED => [self::STATUS_ONGOING, self::STATUS_CANCELLED],
            self::STATUS_REJECTED => [],
            self::STATUS_ONGOING => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        $transitions = $this->getStatusTransitions();

        return in_array($status, $transitions[$this->status] ?? [], true);
    }
}
