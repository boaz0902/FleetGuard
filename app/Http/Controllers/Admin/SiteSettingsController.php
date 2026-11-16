<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\MapLink;
use App\Services\SiteSettings as SiteSettingsService;
use App\Services\SocialProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SiteSettingsController extends Controller
{
    public function __construct(
        private readonly MapLink $mapLink,
        private readonly SocialProfile $socialProfile,
        private readonly SiteSettingsService $siteSettings,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', SiteSetting::class);

        $settings = SiteSetting::query()->orderBy('key')->get()->keyBy('key');
        $values = $this->currentValues($settings);
        $address = old('address', $values['address']);
        $mapValue = old('maps_url', $values['maps_url']);
        $socialValues = [];

        foreach ($this->socialPlatforms() as $platform) {
            $socialValues[$platform] = old('social.'.$platform, $values['social_'.$platform]);
        }

        return view('admin.settings.index', [
            'settings' => $settings,
            'values' => $values,
            'mapPreview' => $this->mapLink->parse(is_string($mapValue) ? $mapValue : null, is_string($address) ? $address : null),
            'socialPreview' => $this->parseSocialValues($socialValues),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage', SiteSetting::class);

        $rules = [
            'name' => ['present', 'nullable', 'string', 'max:255'],
            'tagline' => ['present', 'nullable', 'string', 'max:255'],
            'phone' => ['present', 'nullable', 'string', 'max:50'],
            'email' => ['present', 'nullable', 'email', 'max:255'],
            'address' => ['present', 'nullable', 'string', 'max:255'],
            'business_hours' => ['present', 'nullable', 'string', 'max:255'],
            'maps_url' => ['present', 'nullable', 'string', 'max:5000'],
            'social' => ['required', 'array:'.implode(',', $this->socialPlatforms())],
        ];
        foreach ($this->socialPlatforms() as $platform) {
            $rules['social.'.$platform] = ['present', 'nullable', 'string', 'max:500'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request): void {
            foreach (['name', 'tagline', 'phone', 'email', 'address', 'business_hours', 'maps_url'] as $key) {
                $this->rejectControlCharacters($validator, $key, $request->input($key));
            }
            foreach (['name', 'tagline', 'phone', 'email', 'address', 'business_hours'] as $key) {
                $this->rejectMarkup($validator, $key, $request->input($key));
            }

            foreach ($this->socialPlatforms() as $platform) {
                $key = 'social.'.$platform;
                $this->rejectControlCharacters($validator, $key, $request->input($key));
            }

            $mapValue = $request->input('maps_url');
            $address = $request->input('address');
            if (is_string($mapValue) && ! $this->mapLink->parse($mapValue, is_string($address) ? $address : null)['valid']) {
                $validator->errors()->add('maps_url', "This doesn't look like a Google Maps link.");
            }

            $social = $request->input('social');
            if (is_array($social)) {
                foreach ($this->socialPlatforms() as $platform) {
                    if (isset($social[$platform]) && is_string($social[$platform]) && ! $this->socialProfile->parse($platform, $social[$platform])['valid']) {
                        $validator->errors()->add('social.'.$platform, 'Enter a valid '.ucfirst($platform).' profile URL or @handle.');
                    }
                }
            }
        });

        $validated = $validator->validate();
        $address = is_string($validated['address']) ? trim($validated['address']) : null;
        $mapResult = $this->mapLink->parse($validated['maps_url'], $address);

        $updates = [];
        foreach (['name', 'tagline', 'phone', 'email', 'address', 'business_hours'] as $key) {
            $updates[$key] = $validated[$key];
        }
        $updates['maps_url'] = $mapResult['stored_url'];

        foreach ($this->socialPlatforms() as $platform) {
            $profile = $this->socialProfile->parse($platform, $validated['social'][$platform]);
            $updates['social_'.$platform] = $profile['url'];
        }

        $this->siteSettings->update($updates, $request->user());

        return redirect()->route('admin.settings.index')->with('success', 'Business details updated.');
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('manage', SiteSetting::class);

        $rules = [
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'maps_url' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'social' => ['sometimes', 'array:'.implode(',', $this->socialPlatforms())],
        ];
        foreach ($this->socialPlatforms() as $platform) {
            $rules['social.'.$platform] = ['sometimes', 'nullable', 'string', 'max:500'];
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request): void {
            $this->rejectControlCharacters($validator, 'address', $request->input('address'));
            $this->rejectControlCharacters($validator, 'maps_url', $request->input('maps_url'));
            foreach ($this->socialPlatforms() as $platform) {
                $this->rejectControlCharacters($validator, 'social.'.$platform, $request->input('social.'.$platform));
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $input = $validator->validated();
        $mapValue = $input['maps_url'] ?? null;
        $address = $input['address'] ?? null;
        $map = $this->mapLink->parse(is_string($mapValue) ? $mapValue : null, is_string($address) ? $address : null);
        $social = $this->parseSocialValues($input['social'] ?? []);
        $errors = [];

        if (! $map['valid']) {
            $errors['maps_url'] = [$map['error']];
        }

        foreach ($social as $platform => $profile) {
            if (! $profile['valid']) {
                $errors['social.'.$platform] = [$profile['error']];
            }
        }

        return response()->json([
            'map' => $map,
            'social' => $social,
            'errors' => (object) $errors,
        ], $errors === [] ? 200 : 422);
    }

    private function currentValues($settings): array
    {
        $keys = ['name', 'tagline', 'phone', 'email', 'address', 'business_hours', 'maps_url'];
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $settings->has($key)
                ? $settings->get($key)?->value
                : config('fleetguard.'.$key);
        }

        foreach ($this->socialPlatforms() as $platform) {
            $key = 'social_'.$platform;
            $values[$key] = $settings->has($key)
                ? $settings->get($key)?->value
                : config('fleetguard.social.'.$platform);
        }

        return $values;
    }

    private function parseSocialValues(array $values): array
    {
        $profiles = [];

        foreach ($this->socialPlatforms() as $platform) {
            $value = $values[$platform] ?? null;
            $profiles[$platform] = $this->socialProfile->parse($platform, is_string($value) ? $value : null);
        }

        return $profiles;
    }

    private function socialPlatforms(): array
    {
        return array_keys(SocialProfile::platforms());
    }

    private function rejectControlCharacters($validator, string $key, mixed $value): void
    {
        if (is_string($value) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            $validator->errors()->add($key, 'Control characters are not allowed.');
        }
    }

    private function rejectMarkup($validator, string $key, mixed $value): void
    {
        if (is_string($value) && preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $value)) {
            $validator->errors()->add($key, 'HTML markup is not allowed.');
        }
    }
}
