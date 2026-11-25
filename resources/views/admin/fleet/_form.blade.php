<form class="admin-fleet-form" method="POST" enctype="multipart/form-data" action="{{ $vehicle->exists ? route('admin.fleet.update', $vehicle) : route('admin.fleet.store') }}" data-admin-disable-submit>
    @csrf
    @if ($vehicle->exists)
        @method('PUT')
    @endif

    <div class="admin-fleet-form-sections">
        <fieldset class="admin-fleet-form-section admin-fleet-form-section--wide">
            <legend>Basic information</legend>
            <div class="admin-fleet-form-grid">
                <label>
                    <span>Vehicle name</span>
                    <input type="text" name="name" value="{{ old('name', $vehicle->name) }}" maxlength="120" required>
                    @error('name') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Brand</span>
                    <input type="text" name="brand" value="{{ old('brand', $vehicle->brand) }}" maxlength="100" required>
                    @error('brand') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Model</span>
                    <input type="text" name="model" value="{{ old('model', $vehicle->model) }}" maxlength="100" required>
                    @error('model') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Category</span>
                    <input type="text" name="category" value="{{ old('category', $vehicle->category) }}" list="fleet-category-options" maxlength="50" required>
                    <datalist id="fleet-category-options">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">
                        @endforeach
                    </datalist>
                    @error('category') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Passenger capacity</span>
                    <input type="number" name="passenger_capacity" value="{{ old('passenger_capacity', $vehicle->passenger_capacity) }}" min="1" max="32" step="1" required>
                    @error('passenger_capacity') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Transmission</span>
                    <input type="text" name="transmission" value="{{ old('transmission', $vehicle->transmission) }}" maxlength="50" required>
                    @error('transmission') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Fuel type</span>
                    <input type="text" name="fuel_type" value="{{ old('fuel_type', $vehicle->fuel_type) }}" maxlength="50" required>
                    @error('fuel_type') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
                <label class="admin-fleet-form-full">
                    <span>Description</span>
                    <textarea name="description" rows="4" maxlength="5000">{{ old('description', $vehicle->description) }}</textarea>
                    @error('description') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                </label>
            </div>
        </fieldset>

        <fieldset class="admin-fleet-form-section">
            <legend>Pricing</legend>
            <label>
                <span>Daily rental rate</span>
                <span class="admin-fleet-currency-field">
                    <span aria-hidden="true">₱</span>
                    <input type="number" name="daily_rate" value="{{ old('daily_rate', $vehicle->daily_rate) }}" min="0.01" max="99999999.99" step="0.01" required>
                </span>
                <small>Existing reservations retain their saved rate.</small>
                @error('daily_rate') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
            </label>
        </fieldset>

        <fieldset class="admin-fleet-form-section">
            <legend>Status</legend>
            <label>
                <span>Vehicle status</span>
                <select name="status" required>
                    @foreach ([
                        \App\Models\Vehicle::STATUS_ACTIVE,
                        \App\Models\Vehicle::STATUS_INACTIVE,
                        \App\Models\Vehicle::STATUS_MAINTENANCE,
                    ] as $status)
                        <option value="{{ $status }}" @selected(old('status', $vehicle->status ?? \App\Models\Vehicle::STATUS_ACTIVE) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <small>Deactivation or maintenance is blocked while approved or ongoing reservations remain.</small>
                @error('status') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
            </label>
        </fieldset>

        <fieldset class="admin-fleet-form-section admin-fleet-form-section--wide">
            <legend>Media</legend>
            <div class="admin-fleet-media-field">
                <span class="admin-fleet-media-preview" data-fleet-image-preview>
                    <i class="bi bi-car-front" aria-hidden="true" data-fleet-image-placeholder></i>
                    <img src="{{ old('image_path', $vehicle->image_path) ? asset(old('image_path', $vehicle->image_path)) : '' }}" alt="Selected fleet image preview" @if (!old('image_path', $vehicle->image_path)) hidden @endif data-fleet-image-preview-img>
                </span>
                <div class="admin-fleet-media-controls">
                    <label>
                        <span>Upload vehicle image</span>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-fleet-image-upload>
                        <small>JPG, PNG, or WebP up to 10 MB. Images are resized to fit a 1600 × 1000 media frame.</small>
                        @error('image') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Or choose existing media</span>
                    <select name="image_path" data-fleet-image-select>
                        <option value="" data-preview-url="">Use vehicle icon</option>
                        @foreach ($images as $image)
                            <option value="{{ $image }}" data-preview-url="{{ asset($image) }}" @selected(old('image_path', $vehicle->image_path) === $image)>{{ basename($image) }}</option>
                        @endforeach
                    </select>
                    @error('image_path') <small class="admin-fleet-field-error">{{ $message }}</small> @enderror
                    </label>
                </div>
            </div>
        </fieldset>
    </div>

    <div class="admin-fleet-form-actions">
        <a class="admin-fleet-secondary-link" href="{{ $vehicle->exists ? route('admin.fleet.show', $vehicle) : route('admin.fleet.index') }}">Cancel</a>
        <button class="admin-fleet-primary-link" type="submit">
            <i class="bi bi-check2" aria-hidden="true"></i>
            <span>{{ $vehicle->exists ? 'Save changes' : 'Add vehicle' }}</span>
        </button>
    </div>
</form>
