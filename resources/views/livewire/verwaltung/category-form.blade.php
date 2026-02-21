<div>
    <form wire:submit="save" class="space-y-6">
        {{-- Grunddaten --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Grunddaten</h2>

            <div class="dash-form-grid dash-form-grid-2">
                {{-- Name --}}
                <div>
                    <label for="name" class="dash-label dash-label-required">Name</label>
                    <input type="text"
                           id="name"
                           wire:model.live.debounce.300ms="name"
                           class="dash-input {{ $errors->has('name') ? 'dash-input-error' : '' }}"
                           placeholder="z.B. Handwerker">
                    @error('name')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="dash-label dash-label-required">Slug</label>
                    <input type="text"
                           id="slug"
                           wire:model.blur="slug"
                           class="dash-input {{ $errors->has('slug') ? 'dash-input-error' : '' }}"
                           placeholder="handwerker">
                    @error('slug')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                    <p class="dash-input-hint">URL-Pfad: /kategorien/{{ $slug ?: '...' }}</p>
                </div>
            </div>

            {{-- Description --}}
            <div class="mt-4">
                <label for="description" class="dash-label">Beschreibung</label>
                <textarea id="description"
                          wire:model="description"
                          rows="3"
                          class="dash-textarea {{ $errors->has('description') ? 'dash-textarea-error' : '' }}"
                          placeholder="Optionale Beschreibung der Kategorie..."></textarea>
                @error('description')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Einordnung --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Einordnung</h2>

            <div class="dash-form-grid dash-form-grid-3">
                {{-- Parent Category --}}
                <div>
                    <label for="parent_id" class="dash-label">Oberkategorie</label>
                    <select id="parent_id"
                            wire:model="parent_id"
                            class="dash-select">
                        <option value="">Keine (Hauptkategorie)</option>
                        @foreach($parentOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Sort Order --}}
                <div>
                    <label for="sort_order" class="dash-label">Sortierung</label>
                    <input type="number"
                           id="sort_order"
                           wire:model="sort_order"
                           min="0"
                           max="9999"
                           class="dash-input">
                    @error('sort_order')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                    <p class="dash-input-hint">Kleinere Zahl = weiter oben</p>
                </div>

                {{-- Icon --}}
                <div>
                    <label for="icon" class="dash-label">Icon</label>
                    <input type="text"
                           id="icon"
                           wire:model="icon"
                           class="dash-input"
                           placeholder="z.B. wrench, heart, building">
                    @error('icon')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('verwaltung.categories.index') }}"
               class="dash-btn dash-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Abbrechen
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="dash-btn dash-btn-primary relative overflow-hidden"
                    wire:target="save">
                <span wire:loading.class="opacity-0" wire:target="save" class="transition-opacity duration-200">
                    {{ $categoryId ? 'Änderungen speichern' : 'Kategorie erstellen' }}
                </span>
                <span wire:loading wire:target="save" class="absolute inset-0 flex items-center justify-center">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                </span>
            </button>
        </div>
    </form>
</div>
