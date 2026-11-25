<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalPolicyVersion extends Model
{
    protected $fillable = [
        'legal_policy_id',
        'version',
        'content',
        'status',
        'effective_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LegalPolicy::class, 'legal_policy_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalPolicyAcceptance::class, 'legal_policy_version_id');
    }
}
