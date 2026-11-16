<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SiteSettings
{
    public function applyToConfig(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $settings = Cache::remember('fleetguard.site-settings', 60, function (): array {
            return SiteSetting::query()->pluck('value', 'key')->all();
        });

        foreach ($settings as $key => $value) {
            if ($key === 'social' || str_starts_with($key, 'social.')) {
                continue;
            }

            if (str_starts_with($key, 'social_')) {
                $platform = substr($key, strlen('social_'));
                if (array_key_exists($platform, SocialProfile::platforms())) {
                    config(['fleetguard.social.'.$platform => $value]);
                }

                continue;
            }

            if ($value === null && $key === 'name') {
                $value = config('fleetguard.name_default', 'FleetGuard');
            } elseif ($value === null && $key === 'tagline') {
                $value = config('fleetguard.tagline_default', 'A considered car rental experience in Panabo City.');
            }

            config(['fleetguard.'.$key => $value]);
        }
    }

    public function update(array $values, User $actor): void
    {
        $before = [];
        $normalizedValues = [];

        foreach ($values as $key => $value) {
            if (is_array($value) || is_object($value) || $key === 'social') {
                throw new InvalidArgumentException('Site setting values must be flat strings or null, not nested data.');
            }

            if (! is_string($value) && $value !== null) {
                throw new InvalidArgumentException('Site setting values must be scalar strings or null.');
            }

            $normalizedValues[$key] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : null;
        }

        DB::transaction(function () use ($normalizedValues, $actor, &$before): void {
            $subjectId = null;

            foreach ($normalizedValues as $key => $value) {
                $setting = SiteSetting::query()->firstOrNew(['key' => $key]);
                $before[$key] = $setting->value;
                $setting->value = $value;
                $setting->updated_by = $actor->id;
                $setting->save();
                $subjectId ??= $setting->id;
            }

            $changes = [];
            foreach ($normalizedValues as $key => $value) {
                if (($before[$key] ?? null) !== $value) {
                    $changes[$key] = [
                        'from' => $before[$key] ?? null,
                        'to' => $value,
                    ];
                }
            }

            if ($changes !== []) {
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => 'site_settings_updated',
                    'subject_type' => SiteSetting::class,
                    'subject_id' => $subjectId,
                    'changes' => $changes,
                    'created_at' => now(),
                ]);
            }
        });

        Cache::forget('fleetguard.site-settings');
        foreach ($normalizedValues as $key => $value) {
            if (str_starts_with($key, 'social_')) {
                config(['fleetguard.social.'.substr($key, strlen('social_')) => $value]);
            } elseif ($value === null && $key === 'name') {
                config(['fleetguard.name' => config('fleetguard.name_default', 'FleetGuard')]);
            } elseif ($value === null && $key === 'tagline') {
                config(['fleetguard.tagline' => config('fleetguard.tagline_default', 'A considered car rental experience in Panabo City.')]);
            } else {
                config(['fleetguard.'.$key => $value]);
            }
        }
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $value = config('fleetguard.'.$key, $default);

        if ($value === null && $default !== null) {
            return $default;
        }

        return $value;
    }
}
