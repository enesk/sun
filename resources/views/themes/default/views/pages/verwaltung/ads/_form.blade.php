{{-- Shared form partial for create/edit Ad-Slot --}}
@php
    $slot = $adSlot ?? null;
    $deviceVisibility = old('device_visibility', $slot?->device_visibility ?? ['desktop', 'tablet', 'mobile']);
@endphp

<div class="space-y-6">
    {{-- Name --}}
    <div>
        <label for="name" class="dash-label dash-label-required">Name</label>
        <input type="text"
               name="name"
               id="name"
               value="{{ old('name', $slot?->name) }}"
               class="dash-input @error('name') dash-input-error @enderror"
               placeholder="z.B. Sidebar Banner 1"
               required>
        @error('name')
            <p class="dash-input-error-msg">{{ $message }}</p>
        @enderror
    </div>

    {{-- Position --}}
    <div>
        <label for="position" class="dash-label dash-label-required">Position</label>
        <select name="position" id="position" class="dash-select @error('position') dash-select-error @enderror" required>
            <option value="">Position wählen…</option>
            @foreach($positions as $key => $label)
                <option value="{{ $key }}" @selected(old('position', $slot?->position) === $key)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('position')
            <p class="dash-input-error-msg">{{ $message }}</p>
        @enderror
    </div>

    {{-- Code --}}
    <div>
        <label for="code" class="dash-label">Ad-Code</label>
        <textarea name="code"
                  id="code"
                  rows="8"
                  class="dash-textarea @error('code') dash-textarea-error @enderror"
                  style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.8125rem;"
                  placeholder="<script>...</script>">{{ old('code', $slot?->code) }}</textarea>
        <p class="dash-input-hint">Fügen Sie hier den Code aus Ihrem Google AdSense-Konto ein. HTML und JavaScript werden unterstützt.</p>
        @error('code')
            <p class="dash-input-error-msg">{{ $message }}</p>
        @enderror
    </div>

    <div class="dash-form-grid dash-form-grid-2">
        {{-- Sortierung --}}
        <div>
            <label for="sort_order" class="dash-label">Sortierung</label>
            <input type="number"
                   name="sort_order"
                   id="sort_order"
                   value="{{ old('sort_order', $slot?->sort_order ?? 0) }}"
                   class="dash-input @error('sort_order') dash-input-error @enderror"
                   min="0"
                   style="max-width: 8rem;">
            <p class="dash-input-hint">Niedrigere Werte werden zuerst angezeigt.</p>
            @error('sort_order')
                <p class="dash-input-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Aktiv-Toggle --}}
        <div>
            <label class="dash-label">Status</label>
            <label class="dash-checkbox" style="margin-top: 0.375rem;">
                <div class="dash-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           @checked(old('is_active', $slot?->is_active ?? false))>
                    <span class="dash-toggle-track"></span>
                </div>
                <span class="text-sm">Aktiv — Slot wird im Frontend angezeigt</span>
            </label>
        </div>
    </div>

    {{-- Geräte-Sichtbarkeit --}}
    <div>
        <label class="dash-label">Geräte-Sichtbarkeit</label>
        <div class="flex flex-wrap gap-4 mt-1">
            @foreach(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $device => $deviceLabel)
                <label class="dash-checkbox">
                    <input type="checkbox"
                           name="device_visibility[]"
                           value="{{ $device }}"
                           @checked(in_array($device, $deviceVisibility))>
                    <span class="text-sm">{{ $deviceLabel }}</span>
                </label>
            @endforeach
        </div>
        @error('device_visibility')
            <p class="dash-input-error-msg">{{ $message }}</p>
        @enderror
    </div>

    {{-- Submit --}}
    <div class="flex items-center gap-3 pt-4" style="border-top: 1px solid var(--dash-border, rgba(0,0,0,0.08));">
        <button type="submit" class="dash-btn dash-btn-primary">
            {{ $slot ? 'Speichern' : 'Ad-Slot erstellen' }}
        </button>
        <a href="{{ route('verwaltung.ads.index') }}" class="dash-btn">
            Abbrechen
        </a>
    </div>
</div>
