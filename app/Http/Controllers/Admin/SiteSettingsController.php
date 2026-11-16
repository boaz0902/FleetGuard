<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SiteSettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SiteSetting::class);

        $settings = SiteSetting::query()->orderBy('key')->get()->keyBy('key');

        return view('admin.settings.index', [
            'settings' => $settings,
            'businessKeys' => [
                'name',
                'tagline',
                'phone',
                'email',
                'address',
                'business_hours',
                'maps_url',
                'social.facebook',
                'social.instagram',
                'social.tiktok',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage', SiteSetting::class);

        $updates = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'business_hours' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'url', 'max:500'],
            'social.facebook' => ['nullable', 'url', 'max:500'],
            'social.instagram' => ['nullable', 'url', 'max:500'],
            'social.tiktok' => ['nullable', 'url', 'max:500'],
        ]);

        $before = [];
        DB::transaction(function () use ($request, $updates, &$before): void {
            foreach ($updates as $key => $value) {
                $normalized = is_string($value) ? trim($value) : $value;
                $record = SiteSetting::query()->firstOrNew(['key' => $key]);
                $before[$key] = $record->value;

                if ($normalized === '') {
                    $record->value = null;
                } else {
                    $record->value = $normalized;
                }

                $record->updated_by = $request->user()->id;
                $record->save();
            }

            Cache::forget('fleetguard.site-settings');
        });

        $configOverrides = [];
        foreach ($updates as $key => $value) {
            $normalized = is_string($value) ? trim($value) : $value;
            $configOverrides[$key] = $normalized === '' ? null : $normalized;
            config(['fleetguard.'.$key => $configOverrides[$key]]);
        }

        $changes = [];
        foreach ($updates as $key => $value) {
            $beforeValue = $before[$key] ?? null;
            $newValue = (is_string($value) ? trim($value) : $value) === '' ? null : (is_string($value) ? trim($value) : $value);
            if ($beforeValue !== $newValue) {
                $changes[$key] = [
                    'from' => $beforeValue,
                    'to' => $newValue,
                ];
            }
        }

        if ($changes !== []) {
            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'site_settings_updated',
                'subject_type' => SiteSetting::class,
                'subject_id' => null,
                'changes' => $changes,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.settings.index')->with('success', 'Business details updated.');
    }
}
