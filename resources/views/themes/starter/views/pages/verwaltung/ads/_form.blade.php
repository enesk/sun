{{-- Shared form partial for create/edit Ad-Slot --}}
@php
    $slot = $adSlot ?? null;
    $deviceVisibility = old('device_visibility', $slot?->device_visibility ?? ['desktop', 'tablet', 'mobile']);
@endphp

<div class="space-y-6" x-data="{ position: '{{ old('position', $slot?->position) }}' }">
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
        <select name="position"
                id="position"
                class="dash-select @error('position') dash-select-error @enderror"
                x-model="position"
                aria-describedby="auto-ads-warning"
                required>
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

        {{-- Warnung zu Auto Ads (Vorgabe #100, 4.2). Ohne JavaScript sichtbar: kein x-cloak, kein hidden. --}}
        <div id="auto-ads-warning"
             class="dash-flash dash-flash-warning dash-flash-block"
             style="margin-top: 0.75rem;"
             role="note"
             aria-live="polite"
             x-show="position === 'auto_ads'">
            <svg class="dash-flash-icon" style="margin-top: 0.125rem; color: var(--dash-warning, #d97706);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
                <p class="text-sm font-semibold">Auto Ads: Anker-Banner vorher abschalten</p>
                <p class="text-sm mt-0.5 dash-flash-body">Auto Ads platzieren sich selbst und blenden ohne weitere Einstellung einen Anker-Banner am unteren Bildschirmrand ein. Der verschiebt das Seitenlayout um mehrere hundert Pixel. Schalten Sie in Ihrem AdSense-Konto unter <em>Auto Ads &rarr; Anzeigenformate</em> die Anker-Anzeigen ab. Auf Ratgeber-Seiten wird dieser Platz grundsätzlich nicht ausgeliefert.</p>
            </div>
        </div>
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
        <p class="dash-input-hint">Fügen Sie hier den Code aus Ihrem Google AdSense-Konto ein. HTML und JavaScript werden unterstützt.<span x-show="position === 'auto_ads'"> Fügen Sie nur den Auto-Ads-Code aus dem AdSense-Konto ein. Ohne erkennbare Publisher-Kennung (<code>ca-pub-…</code>) kann das Portal den Overlay nicht abschalten.</span></p>
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
