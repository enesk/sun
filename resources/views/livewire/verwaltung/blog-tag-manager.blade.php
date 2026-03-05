<div>
    {{-- Header + Actions --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            <div class="flex items-center gap-3">
                {{-- Search --}}
                <div style="position: relative; min-width: 200px;">
                    <svg class="w-4 h-4" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--dash-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           class="dash-input"
                           style="padding-left: 2.25rem; font-size: 0.875rem;"
                           placeholder="Tags suchen..."
                           aria-label="Tags suchen">
                </div>

                <span style="color: var(--dash-text-secondary); font-size: 0.875rem;">
                    {{ $tags->count() }} {{ $tags->count() === 1 ? 'Tag' : 'Tags' }}
                </span>
            </div>

            <div class="dash-filter-actions">
                @if(count($selected) > 0)
                    <div class="dash-table-bulk">
                        <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                        <button wire:click="bulkDelete"
                                wire:confirm="Ausgewählte Tags löschen? Die Artikel-Verknüpfungen werden entfernt."
                                class="dash-btn dash-btn-danger dash-btn-sm">
                            Löschen
                        </button>
                    </div>
                @endif

                @if(!$showForm)
                    <button wire:click="openCreate"
                            class="dash-btn dash-btn-primary dash-btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Neuer Tag
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Inline Create/Edit Form --}}
    @if($showForm)
        <div class="dash-card mb-4" style="border-left: 3px solid var(--portal-primary);">
            <div style="padding: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: var(--dash-text-primary); margin-bottom: 1rem;">
                    {{ $editingId ? 'Tag bearbeiten' : 'Neuen Tag erstellen' }}
                </h3>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    {{-- Name --}}
                    <div style="flex: 1; min-width: 200px;">
                        <label class="dash-label" for="tag-name">Name *</label>
                        <input type="text"
                               wire:model.live="name"
                               id="tag-name"
                               class="dash-input @error('name') dash-input-error @enderror"
                               placeholder="z.B. Renovierung">
                        @error('name')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Slug --}}
                    <div style="flex: 1; min-width: 200px;">
                        <label class="dash-label" for="tag-slug">Slug *</label>
                        <input type="text"
                               wire:model.live="slug"
                               id="tag-slug"
                               class="dash-input @error('slug') dash-input-error @enderror"
                               placeholder="renovierung">
                        @error('slug')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Form Actions --}}
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                    <button wire:click="closeForm"
                            class="dash-btn dash-btn-sm">
                        Abbrechen
                    </button>
                    <button wire:click="save"
                            class="dash-btn dash-btn-primary dash-btn-sm">
                        {{ $editingId ? 'Speichern' : 'Erstellen' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Tag List --}}
    @if($tags->count() > 0)
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            @foreach($tags as $tag)
                <div wire:key="tag-{{ $tag->id }}"
                     class="dash-card"
                     style="padding: 0.75rem 1rem; display: inline-flex; align-items: center; gap: 0.75rem;">

                    <input type="checkbox"
                           wire:model.live="selected"
                           value="{{ $tag->id }}"
                           style="accent-color: var(--portal-primary);"
                           aria-label="{{ $tag->name }} auswählen">

                    <div>
                        <span style="font-size: 0.9375rem; font-weight: 600; color: var(--dash-text-primary);">
                            {{ $tag->name }}
                        </span>
                        <span class="dash-badge dash-badge-neutral" style="margin-left: 0.375rem;">
                            {{ $tag->posts_count }}
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        <button wire:click="openEdit({{ $tag->id }})"
                                class="dash-btn-icon"
                                title="Bearbeiten">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/>
                            </svg>
                        </button>

                        <button wire:click="deleteTag({{ $tag->id }})"
                                wire:confirm="Tag löschen? Die Artikel-Verknüpfungen werden entfernt."
                                class="dash-btn-icon dash-btn-danger"
                                title="Löschen">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Empty State --}}
        <div class="dash-card">
            <div class="dash-empty">
                <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                </svg>
                <p class="dash-empty-title">Noch keine Tags</p>
                <p class="dash-empty-description">Tags helfen Lesern, verwandte Artikel zu finden.</p>
                <button wire:click="openCreate" class="dash-btn dash-btn-sm dash-btn-primary">
                    Ersten Tag erstellen
                </button>
            </div>
        </div>
    @endif
</div>
