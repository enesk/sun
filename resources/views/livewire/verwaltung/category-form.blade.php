<div>
    <form wire:submit="save" class="space-y-6">
        {{-- Grunddaten --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Grunddaten</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-base-content/70 mb-1">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           wire:model.live.debounce.300ms="name"
                           class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('name') ? 'border-red-300' : 'border-base-200' }}"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. Handwerker">
                    @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-base-content/70 mb-1">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="slug"
                           wire:model.blur="slug"
                           class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('slug') ? 'border-red-300' : 'border-base-200' }}"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="handwerker">
                    @error('slug')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-base-content/40">URL-Pfad: /kategorien/{{ $slug ?: '...' }}</p>
                </div>
            </div>

            {{-- Description --}}
            <div class="mt-4">
                <label for="description" class="block text-sm font-medium text-base-content/70 mb-1">Beschreibung</label>
                <textarea id="description"
                          wire:model="description"
                          rows="3"
                          class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent resize-y"
                          style="focus:ring-color: var(--portal-primary, #3b82f6);"
                          placeholder="Optionale Beschreibung der Kategorie..."></textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Einordnung --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Einordnung</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Parent Category --}}
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-base-content/70 mb-1">Oberkategorie</label>
                    <select id="parent_id"
                            wire:model="parent_id"
                            class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-1">
                        <option value="">Keine (Hauptkategorie)</option>
                        @foreach($parentOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Sort Order --}}
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-base-content/70 mb-1">Sortierung</label>
                    <input type="number"
                           id="sort_order"
                           wire:model="sort_order"
                           min="0"
                           max="9999"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);">
                    @error('sort_order')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-base-content/40">Kleinere Zahl = weiter oben</p>
                </div>

                {{-- Icon --}}
                <div>
                    <label for="icon" class="block text-sm font-medium text-base-content/70 mb-1">Icon</label>
                    <input type="text"
                           id="icon"
                           wire:model="icon"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. wrench, heart, building">
                    @error('icon')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('verwaltung.categories.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-base-content/70 hover:text-base-content transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Abbrechen
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="relative inline-flex items-center justify-center px-6 py-2.5 rounded-lg text-white text-sm font-medium shadow-sm hover:opacity-90 disabled:opacity-50 overflow-hidden"
                    style="background-color: var(--portal-primary, #3b82f6);">
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
