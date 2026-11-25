<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalPolicy extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public static function bookingAgreementSlugs(): array
    {
        return [
            'terms-and-conditions',
            'rental-policy',
            'cancellation-policy',
            'refund-policy',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegalPolicyVersion::class);
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalPolicyAcceptance::class);
    }

    public function currentPublishedVersion(): HasOne
    {
        $relation = $this->hasOne(LegalPolicyVersion::class);
        $this->constrainPublishedVersionQuery($relation->getQuery(), now('Asia/Manila'));

        return $relation;
    }

    public function currentPublishedVersionAt(DateTimeInterface $asOf): ?LegalPolicyVersion
    {
        $relation = $this->versions();
        $this->constrainPublishedVersionQuery($relation->getQuery(), $asOf);

        return $relation->first();
    }

    private function constrainPublishedVersionQuery(Builder $query, DateTimeInterface $asOf): void
    {
        $manilaAsOf = Carbon::instance($asOf)->setTimezone('Asia/Manila');
        $effectiveDate = $manilaAsOf->toDateString();

        $query->where('status', 'published')
            ->where(function (Builder $query) use ($effectiveDate): void {
                $query->whereNull('effective_at')
                    ->orWhereDate('effective_at', '<=', $effectiveDate);
            })
            ->orderByRaw('COALESCE(effective_at, DATE(published_at)) DESC')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
