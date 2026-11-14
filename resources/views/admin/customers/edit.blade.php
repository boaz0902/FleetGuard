@extends('layouts.admin')

@section('title', 'Edit '.$customer->full_name.' | Customers | FleetGuard')
@section('admin-page-title', 'Edit customer')
@section('admin-page-icon', 'person-gear')
@section('admin-breadcrumb', 'Customers')
@section('admin-page-description', 'Update supported customer profile fields without changing booking records.')

@section('admin-content')
    <main class="admin-customer-edit-page" id="main-content">
        @if ($errors->any())
            <div class="admin-customers-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="admin-customer-edit-header">
            <a class="admin-customer-back" href="{{ route('admin.customers.show', $customer) }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to customer</span>
            </a>
            <div class="admin-customer-edit-identity">
                <span class="admin-customer-avatar" aria-hidden="true">{{ $customer->initials() }}</span>
                <div><p class="admin-customers-eyebrow">Customer #{{ $customer->id }}</p><h2>{{ $customer->full_name }}</h2></div>
            </div>
        </div>

        <section class="admin-customer-edit-panel admin-section-card" aria-labelledby="customer-edit-heading">
            <div class="admin-customer-form-heading">
                <span class="admin-customer-detail-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
                <div><p class="admin-customers-eyebrow">Profile information</p><h2 id="customer-edit-heading">Customer details</h2></div>
            </div>
            <p class="admin-customer-form-intro">Only fields present in the customer record are editable. Updating this profile does not change existing booking contact or rate snapshots.</p>

            <form id="customer-edit-form" class="admin-customer-form" method="POST" action="{{ route('admin.customers.update', $customer) }}" data-admin-disable-submit>
                @csrf
                @method('PUT')
                <div class="admin-customer-form-fields">
                    <label>
                        <span>Full name <b aria-hidden="true">*</b></span>
                        <input type="text" name="full_name" value="{{ old('full_name', $customer->full_name) }}" maxlength="255" autocomplete="name" required @error('full_name') aria-invalid="true" aria-describedby="customer-name-error" @enderror>
                        @error('full_name')<small class="admin-customer-field-error" id="customer-name-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Email address <b aria-hidden="true">*</b></span>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="255" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="customer-email-error" @enderror>
                        @error('email')<small class="admin-customer-field-error" id="customer-email-error">{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Mobile number <b aria-hidden="true">*</b></span>
                        <input type="tel" name="mobile_number" value="{{ old('mobile_number', $customer->mobile_number) }}" maxlength="13" autocomplete="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX" required @error('mobile_number') aria-invalid="true" aria-describedby="customer-phone-error" @enderror>
                        @error('mobile_number')<small class="admin-customer-field-error" id="customer-phone-error">{{ $message }}</small>@enderror
                    </label>
                </div>

                <div class="admin-customer-form-note">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Bookings retain their own customer and rate snapshots. No booking status, dates, or stored totals will be changed.</span>
                </div>

                <div class="admin-customer-form-actions">
                    <a class="admin-customer-secondary-link" href="{{ route('admin.customers.show', $customer) }}">Cancel</a>
                    <button class="admin-customer-primary-link" type="submit"><i class="bi bi-check2" aria-hidden="true"></i><span>Save customer</span></button>
                </div>
            </form>
        </section>

        <div class="admin-customer-sticky-actions">
            <a class="admin-customer-secondary-link" href="{{ route('admin.customers.show', $customer) }}">Cancel</a>
            <button class="admin-customer-primary-link" type="submit" form="customer-edit-form"><i class="bi bi-check2" aria-hidden="true"></i><span>Save changes</span></button>
        </div>
    </main>
@endsection
