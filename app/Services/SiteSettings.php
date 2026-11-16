<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SiteSettings
{
    public function applyToConfig(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $settings = Cache::remember('fleetguard.site-settings', 60, function (): array {
            return SiteSetting::query()->get(['key', 'value'])->mapWithKeys(fn (SiteSetting $setting): array => [
                $setting->key => $setting->value,
            ])->all();
        });

        foreach ($settings as $key => $value) {
            config(['fleetguard.'.$key => $value]);
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
