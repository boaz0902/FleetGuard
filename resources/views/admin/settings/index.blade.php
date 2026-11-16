@extends('layouts.admin')

@section('title', 'Business settings | FleetGuard')
@section('admin-page-icon', 'sliders')
@section('admin-breadcrumb', 'Settings')
@section('admin-page-title', 'Business details')
@section('admin-page-description', 'Manage the public contact details shown across the site.')

@section('admin-content')
    @php
        $canManageSettings = auth()->user()->can('manage', \App\Models\SiteSetting::class);
        $latestSetting = $settings->sortByDesc('updated_at')->first();
        $updatedByName = $latestSetting?->updatedBy?->name ?? 'an administrator';
        $previewAddress = old('address', $settings->get('address')?->value ?? config('fleetguard.address', ''));
        $previewMapsUrl = old('maps_url', $settings->get('maps_url')?->value ?? config('fleetguard.maps_url', ''));
        $previewSocialProfiles = array_filter([
            'Facebook' => old('social.facebook', $settings->get('social.facebook')?->value ?? config('fleetguard.social.facebook', '')),
            'Instagram' => old('social.instagram', $settings->get('social.instagram')?->value ?? config('fleetguard.social.instagram', '')),
            'TikTok' => old('social.tiktok', $settings->get('social.tiktok')?->value ?? config('fleetguard.social.tiktok', '')),
        ], fn ($url) => is_string($url) && trim($url) !== '');
    @endphp

    <main class="admin-settings" id="main-content">
        @if (session('success'))
            <div class="admin-settings-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-messages-flash" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <header class="admin-settings-header">
            <div>
                <p class="admin-settings-breadcrumb"><span>FleetGuard</span><i class="bi bi-chevron-right" aria-hidden="true"></i> Settings</p>
                <h2 class="admin-settings-title">Business details</h2>
                <p class="admin-settings-subtitle">Keep the public contact information current wherever customers see it.</p>
            </div>
            @unless ($canManageSettings)
                <span class="admin-settings-readonly"><i class="bi bi-lock" aria-hidden="true"></i> View only</span>
            @endunless
        </header>

        <div class="admin-settings-grid">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-settings-form" data-admin-disable-submit data-settings-form>
                @csrf
                @if (! $canManageSettings)
                    <div class="admin-settings-readonly" role="note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>Only administrators can change these details. You can still review the current public-site preview.</span>
                    </div>
                @endif

                <div class="admin-settings-sections">
                    <section class="admin-settings-card" aria-labelledby="settings-business-title">
                        <div class="admin-settings-card-heading">
                            <span class="admin-settings-card-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
                            <span>
                                <h2 id="settings-business-title">Business</h2>
                                <p>Your public-facing name and introduction.</p>
                            </span>
                        </div>
                        <div class="admin-settings-field-grid">
                            <label class="admin-settings-field">
                                <span>Business name</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-building" aria-hidden="true"></i>
                                    <input type="text" name="name" value="{{ old('name', $settings->get('name')?->value ?? config('fleetguard.name', 'FleetGuard')) }}" placeholder="FleetGuard" data-settings-preview-input="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-help{{ $errors->has('name') ? ' name-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="name-help">Shown as “FleetGuard” on the public site when left empty.</small>
                                @error('name')<small class="admin-settings-error" id="name-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Tagline</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-card-text" aria-hidden="true"></i>
                                    <input type="text" name="tagline" value="{{ old('tagline', $settings->get('tagline')?->value ?? '') }}" placeholder="Your next journey starts here" data-settings-preview-input="tagline" aria-invalid="{{ $errors->has('tagline') ? 'true' : 'false' }}" aria-describedby="tagline-help{{ $errors->has('tagline') ? ' tagline-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="tagline-help">The public site uses its standard tagline when this is empty.</small>
                                @error('tagline')<small class="admin-settings-error" id="tagline-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                    </section>

                    <section class="admin-settings-card" aria-labelledby="settings-contact-title">
                        <div class="admin-settings-card-heading">
                            <span class="admin-settings-card-icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
                            <span>
                                <h2 id="settings-contact-title">Contact</h2>
                                <p>Ways for customers to reach and find your team.</p>
                            </span>
                        </div>
                        <div class="admin-settings-field-grid">
                            <label class="admin-settings-field">
                                <span>Phone</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-telephone" aria-hidden="true"></i>
                                    <input type="text" name="phone" value="{{ old('phone', $settings->get('phone')?->value ?? config('fleetguard.phone', '')) }}" placeholder="To be added" data-settings-preview-input="phone" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="phone-help{{ $errors->has('phone') ? ' phone-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="phone-help">The contact section displays “To be added” when empty.</small>
                                @error('phone')<small class="admin-settings-error" id="phone-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Email</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <input type="email" name="email" value="{{ old('email', $settings->get('email')?->value ?? config('fleetguard.email', '')) }}" placeholder="To be added" data-settings-preview-input="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-help{{ $errors->has('email') ? ' email-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="email-help">The contact section displays “To be added” when empty.</small>
                                @error('email')<small class="admin-settings-error" id="email-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Address</span>
                                <span class="admin-settings-input-wrap admin-settings-input-wrap--textarea">
                                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                    <textarea name="address" rows="3" placeholder="To be added" data-settings-preview-input="address" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="address-help{{ $errors->has('address') ? ' address-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>{{ old('address', $settings->get('address')?->value ?? config('fleetguard.address', '')) }}</textarea>
                                </span>
                                <small id="address-help">Shown on the public contact section and footer.</small>
                                @error('address')<small class="admin-settings-error" id="address-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Business hours</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    <input type="text" name="business_hours" value="{{ old('business_hours', $settings->get('business_hours')?->value ?? config('fleetguard.business_hours', '')) }}" placeholder="To be added" data-settings-preview-input="business_hours" aria-invalid="{{ $errors->has('business_hours') ? 'true' : 'false' }}" aria-describedby="business-hours-help{{ $errors->has('business_hours') ? ' business-hours-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="business-hours-help">The contact section displays “To be added” when empty.</small>
                                @error('business_hours')<small class="admin-settings-error" id="business-hours-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field admin-settings-field--full">
                                <span>Maps URL</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-link-45deg" aria-hidden="true"></i>
                                    <input type="url" name="maps_url" value="{{ old('maps_url', $settings->get('maps_url')?->value ?? config('fleetguard.maps_url', '')) }}" placeholder="https://maps.example.com/..." data-settings-preview-input="maps_url" aria-invalid="{{ $errors->has('maps_url') ? 'true' : 'false' }}" aria-describedby="maps-url-help{{ $errors->has('maps_url') ? ' maps-url-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="maps-url-help">A map link is shown when both this URL and an address are provided.</small>
                                @error('maps_url')<small class="admin-settings-error" id="maps-url-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                    </section>

                    <section class="admin-settings-card" aria-labelledby="settings-social-title">
                        <div class="admin-settings-card-heading">
                            <span class="admin-settings-card-icon"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
                            <span>
                                <h2 id="settings-social-title">Social</h2>
                                <p>Optional links to your official social profiles.</p>
                            </span>
                        </div>
                        <div class="admin-settings-field-grid">
                            <label class="admin-settings-field">
                                <span>Facebook</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-facebook" aria-hidden="true"></i>
                                    <input type="url" name="social[facebook]" value="{{ old('social.facebook', $settings->get('social.facebook')?->value ?? config('fleetguard.social.facebook', '')) }}" placeholder="To be added" data-settings-preview-input="social.facebook" aria-invalid="{{ $errors->has('social.facebook') ? 'true' : 'false' }}" aria-describedby="facebook-help{{ $errors->has('social.facebook') ? ' facebook-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="facebook-help">Leave empty to hide this social link on the public site.</small>
                                @error('social.facebook')<small class="admin-settings-error" id="facebook-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Instagram</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-instagram" aria-hidden="true"></i>
                                    <input type="url" name="social[instagram]" value="{{ old('social.instagram', $settings->get('social.instagram')?->value ?? config('fleetguard.social.instagram', '')) }}" placeholder="To be added" data-settings-preview-input="social.instagram" aria-invalid="{{ $errors->has('social.instagram') ? 'true' : 'false' }}" aria-describedby="instagram-help{{ $errors->has('social.instagram') ? ' instagram-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="instagram-help">Leave empty to hide this social link on the public site.</small>
                                @error('social.instagram')<small class="admin-settings-error" id="instagram-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>TikTok</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-tiktok" aria-hidden="true"></i>
                                    <input type="url" name="social[tiktok]" value="{{ old('social.tiktok', $settings->get('social.tiktok')?->value ?? config('fleetguard.social.tiktok', '')) }}" placeholder="To be added" data-settings-preview-input="social.tiktok" aria-invalid="{{ $errors->has('social.tiktok') ? 'true' : 'false' }}" aria-describedby="tiktok-help{{ $errors->has('social.tiktok') ? ' tiktok-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="tiktok-help">Leave empty to hide this social link on the public site.</small>
                                @error('social.tiktok')<small class="admin-settings-error" id="tiktok-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                    </section>

                    <p class="admin-settings-updated">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        @if ($latestSetting?->updated_at)
                            Updated by {{ $updatedByName }} {{ $latestSetting->updated_at->diffForHumans() }}
                        @else
                            Business details have not been updated yet.
                        @endif
                    </p>
                    <div class="admin-settings-savebar">
                        <small>{{ $canManageSettings ? 'Changes appear across the public site and customer-facing emails.' : 'Administrator access is required to save changes.' }}</small>
                        @if ($canManageSettings)
                            <button class="admin-settings-save" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Save settings</button>
                        @else
                            <span class="admin-settings-readonly"><i class="bi bi-lock" aria-hidden="true"></i> View only</span>
                        @endif
                    </div>
                </div>
            </form>

            <aside class="admin-settings-preview-column">
                <section class="admin-settings-preview" aria-labelledby="settings-preview-title">
                    <p class="admin-settings-eyebrow">Live public-site preview</p>
                    <h2 id="settings-preview-title">Contact details</h2>
                    <p>Preview updates as you edit. Empty fields use the same placeholders shown to customers.</p>
                    <div class="admin-settings-preview-business">
                        <strong data-settings-preview="name">{{ old('name', $settings->get('name')?->value ?? config('fleetguard.name', 'FleetGuard')) ?: 'FleetGuard' }}</strong>
                        <small data-settings-preview="tagline">{{ old('tagline', $settings->get('tagline')?->value ?? '') ?: 'Your next journey starts here' }}</small>
                    </div>
                    <dl class="admin-settings-preview-list">
                        <div><dt><i class="bi bi-telephone" aria-hidden="true"></i></dt><dd data-settings-preview="phone">{{ old('phone', $settings->get('phone')?->value ?? config('fleetguard.phone', '')) ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-envelope" aria-hidden="true"></i></dt><dd data-settings-preview="email">{{ old('email', $settings->get('email')?->value ?? config('fleetguard.email', '')) ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-geo-alt" aria-hidden="true"></i></dt><dd data-settings-preview="address">{{ $previewAddress ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-clock" aria-hidden="true"></i></dt><dd data-settings-preview="business_hours">{{ old('business_hours', $settings->get('business_hours')?->value ?? config('fleetguard.business_hours', '')) ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-map" aria-hidden="true"></i></dt><dd data-settings-preview="maps_url" title="{{ $previewMapsUrl }}" class="{{ $previewMapsUrl && $previewAddress ? '' : 'is-placeholder' }}">{{ $previewMapsUrl && $previewAddress ? 'Open in Maps' : 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-link-45deg" aria-hidden="true"></i></dt><dd data-settings-preview="social" class="{{ $previewSocialProfiles ? '' : 'is-placeholder' }}">{{ $previewSocialProfiles ? implode(' · ', array_keys($previewSocialProfiles)) : 'To be added' }}</dd></div>
                    </dl>
                    <a class="admin-settings-preview-link" href="{{ route('home') }}#contact" target="_blank" rel="noopener noreferrer">Open public contact section <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                </section>

                <section class="admin-settings-preview" aria-labelledby="settings-where-title">
                    <p class="admin-settings-eyebrow">Placement guide</p>
                    <h2 id="settings-where-title">Where this appears</h2>
                    <ul class="admin-settings-where">
                        <li><i class="bi bi-window" aria-hidden="true"></i><span>Navbar and public footer use the business name and contact details.</span></li>
                        <li><i class="bi bi-chat-dots" aria-hidden="true"></i><span>The contact section displays phone, email, address, hours, maps, and social links.</span></li>
                        <li><i class="bi bi-envelope-paper" aria-hidden="true"></i><span>Customer-facing messages use the configured business contact information.</span></li>
                    </ul>
                </section>
            </aside>
        </div>
    </main>
@endsection
