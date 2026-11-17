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
        $socialPreviewProfiles = array_merge($socialPreview, ['text_number' => $textPreview]);
        $hasSocialPreview = collect($socialPreviewProfiles)->contains(fn ($profile) => $profile['valid'] && $profile['url']);
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
            <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-settings-form" data-admin-disable-submit data-settings-form data-preview-url="{{ route('admin.settings.preview') }}" data-preview-csrf="{{ csrf_token() }}">
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
                                    <input type="text" name="name" value="{{ old('name', $values['name'] ?? 'FleetGuard') }}" placeholder="FleetGuard" data-settings-preview-input="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-help{{ $errors->has('name') ? ' name-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="name-help">Shown as “FleetGuard” on the public site when left empty.</small>
                                @error('name')<small class="admin-settings-error" id="name-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Tagline</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-card-text" aria-hidden="true"></i>
                                    <input type="text" name="tagline" value="{{ old('tagline', $values['tagline'] ?? '') }}" placeholder="A considered car rental experience in Panabo City." data-settings-preview-input="tagline" aria-invalid="{{ $errors->has('tagline') ? 'true' : 'false' }}" aria-describedby="tagline-help{{ $errors->has('tagline') ? ' tagline-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="tagline-help">Shown in the public footer; the standard tagline returns when left empty.</small>
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
                                    <input type="text" name="phone" value="{{ old('phone', $values['phone'] ?? '') }}" placeholder="To be added" data-settings-preview-input="phone" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="phone-help{{ $errors->has('phone') ? ' phone-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="phone-help">The contact section displays “To be added” when empty.</small>
                                @error('phone')<small class="admin-settings-error" id="phone-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Email</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <input type="email" name="email" value="{{ old('email', $values['email'] ?? '') }}" placeholder="To be added" data-settings-preview-input="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-help{{ $errors->has('email') ? ' email-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="email-help">The contact section displays “To be added” when empty.</small>
                                @error('email')<small class="admin-settings-error" id="email-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Address</span>
                                <span class="admin-settings-input-wrap admin-settings-input-wrap--textarea">
                                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                    <textarea name="address" rows="3" placeholder="To be added" data-settings-preview-input="address" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="address-help{{ $errors->has('address') ? ' address-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>{{ old('address', $values['address'] ?? '') }}</textarea>
                                </span>
                                <small id="address-help">Shown on the public contact section and footer.</small>
                                @error('address')<small class="admin-settings-error" id="address-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field">
                                <span>Business hours</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    <input type="text" name="business_hours" value="{{ old('business_hours', $values['business_hours'] ?? '') }}" placeholder="To be added" data-settings-preview-input="business_hours" aria-invalid="{{ $errors->has('business_hours') ? 'true' : 'false' }}" aria-describedby="business-hours-help{{ $errors->has('business_hours') ? ' business-hours-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="business-hours-help">The contact section displays “To be added” when empty.</small>
                                @error('business_hours')<small class="admin-settings-error" id="business-hours-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-settings-field admin-settings-field--full">
                                <span>Maps URL</span>
                                <span class="admin-settings-input-wrap">
                                    <i class="bi bi-link-45deg" aria-hidden="true"></i>
                                    <input type="text" name="maps_url" value="{{ old('maps_url', $values['maps_url'] ?? '') }}" placeholder="Paste a Google Maps link or iframe" data-settings-preview-input="maps_url" aria-invalid="{{ $errors->has('maps_url') ? 'true' : 'false' }}" aria-describedby="maps-url-help{{ $errors->has('maps_url') ? ' maps-url-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                </span>
                                <small id="maps-url-help">In Google Maps, choose Share → Embed a map → Copy HTML. We extract only the map source; short links work as an Open in Maps link without an embed.</small>
                                <small class="admin-settings-detection" data-settings-detection="maps_url" aria-live="polite">{{ $mapPreview['valid'] ? ($mapPreview['embed_url'] ? 'Map embed ready.' : ($mapPreview['open_url'] ? ($canManageSettings ? 'Add a full Google Maps link to show a live map.' : 'Open in Maps link ready; a live map is unavailable for this link.') : 'Enter an address to create a map preview.')) : $mapPreview['error'] }}</small>
                                @error('maps_url')<small class="admin-settings-error" id="maps-url-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                    </section>

                    <section class="admin-settings-card" aria-labelledby="settings-social-title">
                        <div class="admin-settings-card-heading">
                            <span class="admin-settings-card-icon"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
                            <span>
                                <h2 id="settings-social-title">Social</h2>
                                <p>Optional social links and a phone-only text messaging option.</p>
                            </span>
                        </div>
                        <div class="admin-settings-field-grid">
                            @foreach ($socialPreview as $platform => $profile)
                                <label class="admin-settings-field">
                                    <span>{{ $profile['name'] }}</span>
                                    <span class="admin-settings-input-wrap">
                                        <i class="bi bi-{{ $profile['icon'] }}" aria-hidden="true"></i>
                                        <input type="text" name="social[{{ $platform }}]" value="{{ old('social.'.$platform, $values['social_'.$platform] ?? '') }}" placeholder="{{ $platform === 'messenger' ? 'm.me/yourpage or @yourpage' : '@username or profile URL' }}" data-settings-preview-input="social.{{ $platform }}" aria-invalid="{{ $errors->has('social.'.$platform) ? 'true' : 'false' }}" aria-describedby="{{ $platform }}-help{{ $errors->has('social.'.$platform) ? ' '.$platform.'-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                    </span>
                                    <small id="{{ $platform }}-help" data-settings-detection="social.{{ $platform }}" aria-live="polite">
                                        @if (! $profile['valid'])
                                            {{ $profile['error'] }}
                                        @elseif ($profile['username'])
                                            Detected: {{ '@'.$profile['username'] }}
                                        @elseif ($profile['url'])
                                            {{ $profile['label'] }} link ready.
                                        @else
                                            Leave empty to hide this social link on the public site.
                                        @endif
                                    </small>
                                    @if ($platform === 'messenger')
                                        <small>Examples: m.me/yourpage, messenger.com/t/yourpage, or @yourpage.</small>
                                    @endif
                                    @error('social.'.$platform)<small class="admin-settings-error" id="{{ $platform }}-error">{{ $message }}</small>@enderror
                                </label>
                            @endforeach
                                <label class="admin-settings-field">
                                    <span>Text message number</span>
                                    <span class="admin-settings-input-wrap">
                                        <i class="bi bi-chat-dots" aria-hidden="true"></i>
                                        <input type="tel" name="text_number" value="{{ old('text_number', $values['text_number'] ?? '') }}" placeholder="09XX XXX XXXX" data-settings-preview-input="text_number" aria-invalid="{{ $errors->has('text_number') ? 'true' : 'false' }}" aria-describedby="text-number-help{{ $errors->has('text_number') ? ' text-number-error' : '' }}" {{ $canManageSettings ? '' : 'disabled' }}>
                                    </span>
                                    <small id="text-number-help">Examples: 09XX XXX XXXX or +63 9XX XXX XXXX. Leave empty to use the business phone number. Shown to visitors on phones only.</small>
                                    <small class="admin-settings-detection" data-settings-detection="text_number" aria-live="polite">
                                        @if (! $textPreview['valid'])
                                            {{ $textPreview['error'] }}
                                        @elseif ($textPreview['number'])
                                            Detected: {{ $textPreview['formatted'] }}
                                        @else
                                            Leave empty to hide text messaging.
                                        @endif
                                    </small>
                                    @error('text_number')<small class="admin-settings-error" id="text-number-error">{{ $message }}</small>@enderror
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
                        <strong data-settings-preview="name">{{ old('name', $values['name'] ?? 'FleetGuard') ?: 'FleetGuard' }}</strong>
                        <small data-settings-preview="tagline">{{ old('tagline', $values['tagline'] ?? '') ?: 'A considered car rental experience in Panabo City.' }}</small>
                    </div>
                    <dl class="admin-settings-preview-list">
                        <div><dt><i class="bi bi-telephone" aria-hidden="true"></i></dt><dd data-settings-preview="phone">{{ old('phone', $values['phone'] ?? '') ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-envelope" aria-hidden="true"></i></dt><dd data-settings-preview="email">{{ old('email', $values['email'] ?? '') ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-geo-alt" aria-hidden="true"></i></dt><dd data-settings-preview="address">{{ old('address', $values['address'] ?? '') ?: 'To be added' }}</dd></div>
                        <div><dt><i class="bi bi-clock" aria-hidden="true"></i></dt><dd data-settings-preview="business_hours">{{ old('business_hours', $values['business_hours'] ?? '') ?: 'To be added' }}</dd></div>
                        <div>
                            <dt><i class="bi bi-map" aria-hidden="true"></i></dt>
                            <dd data-settings-preview="maps_url" class="{{ $mapPreview['open_url'] ? '' : 'is-placeholder' }}">
                                @if ($mapPreview['open_url'])
                                    <a href="{{ $mapPreview['open_url'] }}" target="_blank" rel="noopener noreferrer" data-settings-preview-map-link>Open in Maps</a>
                                @else
                                    <span data-settings-preview-map-link>To be added</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-link-45deg" aria-hidden="true"></i></dt>
                            <dd data-settings-preview="social" class="{{ $hasSocialPreview ? '' : 'is-placeholder' }}">
                                @unless ($hasSocialPreview)
                                    <span data-settings-social-empty>To be added</span>
                                @endunless
                                @include('partials.social-links', ['profiles' => $socialPreviewProfiles, 'variant' => 'card', 'includeTemplates' => true])
                            </dd>
                        </div>
                    </dl>
                    <div class="admin-settings-map-preview" data-settings-map-preview>
                        @if ($mapPreview['embed_url'])
                            <iframe src="{{ $mapPreview['embed_url'] }}" title="FleetGuard location map preview" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        @endif
                    </div>
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
