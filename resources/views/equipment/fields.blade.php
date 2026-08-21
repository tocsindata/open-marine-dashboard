<div class="formgrid">
    <label for="equipment-category">Category
        <select id="equipment-category" name="category" required>
            @foreach($categories as $value => $label)
                <option value="{{ $value }}" @selected(old('category', $equipmentItem->category) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label for="equipment-name">Name
        <input id="equipment-name" name="name" maxlength="120" required value="{{ old('name', $equipmentItem->name) }}">
    </label>
    <label for="equipment-manufacturer">Manufacturer
        <input id="equipment-manufacturer" name="manufacturer" maxlength="100" value="{{ old('manufacturer', $equipmentItem->manufacturer) }}">
    </label>
    <label for="equipment-model">Model
        <input id="equipment-model" name="model" maxlength="100" value="{{ old('model', $equipmentItem->model) }}">
    </label>
    <label for="equipment-serial-number">Serial number
        <input id="equipment-serial-number" name="serial_number" maxlength="120" value="{{ old('serial_number', $equipmentItem->serial_number) }}">
    </label>
    <label for="equipment-installed-on">Installed on
        <input id="equipment-installed-on" type="date" name="installed_on" aria-describedby="equipment-service-guidance" value="{{ old('installed_on', $equipmentItem->installed_on?->format('Y-m-d')) }}">
    </label>
    <label for="equipment-last-serviced-on">Last serviced on
        <input id="equipment-last-serviced-on" type="date" name="last_serviced_on" aria-describedby="equipment-service-guidance" value="{{ old('last_serviced_on', $equipmentItem->last_serviced_on?->format('Y-m-d')) }}">
    </label>
    <label for="equipment-warranty-expires-on">Warranty expires on
        <input id="equipment-warranty-expires-on" type="date" name="warranty_expires_on" aria-describedby="equipment-service-guidance" value="{{ old('warranty_expires_on', $equipmentItem->warranty_expires_on?->format('Y-m-d')) }}">
    </label>
    <label for="equipment-meter-hours">Current meter hours (h)
        <input id="equipment-meter-hours" type="number" name="meter_hours" min="0" max="999999999.9" step="0.1" inputmode="decimal" aria-describedby="equipment-service-guidance" value="{{ old('meter_hours', $equipmentItem->meter_hours) }}">
    </label>
</div>
<p id="equipment-service-guidance">Dates are owner-recorded calendar dates. Meter hours are an optional one-decimal reference, not a service interval; verify the physical meter and manufacturer documentation.</p>
<label for="equipment-notes">Private notes
    <textarea id="equipment-notes" name="notes" rows="4" maxlength="5000">{{ old('notes', $equipmentItem->notes) }}</textarea>
</label>
