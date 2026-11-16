@extends('layouts.admin')

@section('admin-page-icon', 'sliders')
@section('admin-breadcrumb', 'Settings')
@section('admin-page-title', 'Business details')
@section('admin-page-description', 'Update the public contact details shown across the site')

@section('admin-content')
    <div class="admin-content-shell admin-dashboard-shell">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-settings-form">
            @csrf
            <div class="admin-card" style="padding:1rem 1.25rem; margin-bottom:1rem;">
                <h2>Business</h2>
                <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px,1fr));">
                    <label>
                        <span>Business name</span>
                        <input type="text" name="name" value="{{ old('name', $settings->get('name')?->value ?? config('fleetguard.name', 'FleetGuard')) }}">
                    </label>
                    <label>
                        <span>Tagline</span>
                        <input type="text" name="tagline" value="{{ old('tagline', $settings->get('tagline')?->value ?? '') }}">
                    </label>
                </div>
            </div>

            <div class="admin-card" style="padding:1rem 1.25rem; margin-bottom:1rem;">
                <h2>Contact</h2>
                <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px,1fr));">
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone" value="{{ old('phone', $settings->get('phone')?->value ?? config('fleetguard.phone', '')) }}">
                    </label>
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email', $settings->get('email')?->value ?? config('fleetguard.email', '')) }}">
                    </label>
                    <label style="grid-column: 1 / -1;">
                        <span>Address</span>
                        <textarea name="address" rows="3">{{ old('address', $settings->get('address')?->value ?? config('fleetguard.address', '')) }}</textarea>
                    </label>
                    <label style="grid-column: 1 / -1;">
                        <span>Opening hours</span>
                        <input type="text" name="business_hours" value="{{ old('business_hours', $settings->get('business_hours')?->value ?? config('fleetguard.business_hours', '')) }}">
                    </label>
                    <label style="grid-column: 1 / -1;">
                        <span>Maps URL</span>
                        <input type="url" name="maps_url" value="{{ old('maps_url', $settings->get('maps_url')?->value ?? config('fleetguard.maps_url', '')) }}">
                    </label>
                </div>
            </div>

            <div class="admin-card" style="padding:1rem 1.25rem; margin-bottom:1rem;">
                <h2>Social</h2>
                <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px,1fr));">
                    <label>
                        <span>Facebook</span>
                        <input type="url" name="social[facebook]" value="{{ old('social.facebook', $settings->get('social.facebook')?->value ?? config('fleetguard.social.facebook', '')) }}">
                    </label>
                    <label>
                        <span>Instagram</span>
                        <input type="url" name="social[instagram]" value="{{ old('social.instagram', $settings->get('social.instagram')?->value ?? config('fleetguard.social.instagram', '')) }}">
                    </label>
                    <label>
                        <span>TikTok</span>
                        <input type="url" name="social[tiktok]" value="{{ old('social.tiktok', $settings->get('social.tiktok')?->value ?? config('fleetguard.social.tiktok', '')) }}">
                    </label>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
                <div><small>Changes are shown across the public site and emailed contact details.</small></div>
                <button class="button button-primary" type="submit">Save settings</button>
            </div>
        </form>
    </div>
@endsection
