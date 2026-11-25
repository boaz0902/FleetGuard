@if ($maintenanceVehicles->isNotEmpty())
    @php
        $maintenanceStartDefault = \Carbon\Carbon::now('Asia/Manila')->startOfMinute()->addMinute();
        $maintenanceStartMinimum = $maintenanceStartDefault->format('Y-m-d\TH:i');
        $maintenanceStartValue = old('start_datetime', $maintenanceStartMinimum);
        $maintenanceEndValue = old('end_datetime', $maintenanceStartDefault->copy()->addHour()->format('Y-m-d\TH:i'));
        $maintenanceVehicleId = old('vehicle_id', $defaultVehicleId ?? $maintenanceVehicles->first()?->id);
    @endphp

    <section class="admin-maintenance-panel" aria-labelledby="admin-maintenance-heading">
        <div class="admin-maintenance-heading">
            <span class="admin-maintenance-icon" aria-hidden="true"><i class="bi bi-tools"></i></span>
            <div>
                <p class="admin-maintenance-eyebrow">Fleet operations</p>
                <h2 id="admin-maintenance-heading">Schedule vehicle maintenance</h2>
                <p>Start defaults to now. Future service windows are supported; times use Asia/Manila.</p>
            </div>
        </div>

        <form class="admin-maintenance-form" method="POST" action="{{ $maintenanceAction }}" data-maintenance-form>
            @csrf
            <div class="admin-maintenance-fields">
                <label>
                    <span><i class="bi bi-car-front" aria-hidden="true"></i> Vehicle</span>
                    <select name="vehicle_id" required aria-invalid="{{ $errors->has('vehicle_id') ? 'true' : 'false' }}">
                        @foreach ($maintenanceVehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) $maintenanceVehicleId === (string) $vehicle->id)>{{ $vehicle->name }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <small class="admin-maintenance-field-error">{{ $message }}</small>
                    @enderror
                </label>
                <label>
                    <span><i class="bi bi-calendar-event" aria-hidden="true"></i> Start</span>
                    <input type="datetime-local" name="start_datetime" value="{{ $maintenanceStartValue }}" min="{{ $maintenanceStartMinimum }}" required aria-invalid="{{ $errors->has('start_datetime') ? 'true' : 'false' }}" data-maintenance-start>
                    @error('start_datetime')
                        <small class="admin-maintenance-field-error">{{ $message }}</small>
                    @enderror
                </label>
                <label>
                    <span><i class="bi bi-calendar-event" aria-hidden="true"></i> End</span>
                    <input type="datetime-local" name="end_datetime" value="{{ $maintenanceEndValue }}" min="{{ $maintenanceStartMinimum }}" required aria-invalid="{{ $errors->has('end_datetime') ? 'true' : 'false' }}" data-maintenance-end>
                    @error('end_datetime')
                        <small class="admin-maintenance-field-error">{{ $message }}</small>
                    @enderror
                </label>
                <label>
                    <span><i class="bi bi-chat-left-text" aria-hidden="true"></i> Reason</span>
                    <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="e.g. Scheduled service" required aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}">
                    @error('reason')
                        <small class="admin-maintenance-field-error">{{ $message }}</small>
                    @enderror
                </label>
                <button class="admin-maintenance-submit" type="submit">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span>Add maintenance period</span>
                </button>
            </div>
        </form>
    </section>
@endif
