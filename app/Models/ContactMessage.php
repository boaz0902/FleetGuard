<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'reference',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'user_id',
        'customer_id',
        'ip_hash',
        'user_agent',
        'handled_by',
        'read_at',
        'replied_at',
        'archived_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public static function newReference(): string
    {
        do {
            $reference = 'FG-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
