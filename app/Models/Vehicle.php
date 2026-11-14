<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Vehicle extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'brand',
        'model',
        'category',
        'description',
        'daily_rate',
        'passenger_capacity',
        'transmission',
        'fuel_type',
        'image_path',
        'status',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_MAINTENANCE = 'maintenance';

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(VehicleUnavailability::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }
}
