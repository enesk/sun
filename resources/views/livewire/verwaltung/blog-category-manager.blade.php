<div>
    {{-- Header + Actions --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            <div class="flex items-center gap-3">
                <span style="color: var(--dash-text-secondary); font-size: 0.875rem;">
                    {{ $categories->count() }} {{ $categories->count() === 1 ? 'Kategorie' : 'Kategorien' }}
                </span>
            </div>

            <div class="dash-filter-actions">
                @if(count($selected) > 0)
                    <div class="dash-table-bulk">
                        <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                        <button wire:click="bulkDelete"
                                wire:confirm="Kategorien ohne Artikel werden gelöscht. Fortfahren?"
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
                        Neue Kategorie
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
                    {{ $editingId ? 'Kategorie bearbeiten' : 'Neue Kategorie erstellen' }}
                </h3>

                <div class="dash-form-grid" style="gap: 1rem;">
                    {{-- Name --}}
                    <div>
                        <label class="dash-label" for="cat-name">Name *</label>
                        <input type="text"
                               wire:model.live="name"
                               id="cat-name"
                               class="dash-input @error('name') dash-input-error @enderror"
                               placeholder="z.B. Handwerker-Tipps">
                        @error('name')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Slug --}}
                    <div>
                        <label class="dash-label" for="cat-slug">Slug *</label>
                        <input type="text"
                               wire:model.live="slug"
                               id="cat-slug"
                               class="dash-input @error('slug') dash-input-error @enderror"
                               placeholder="handwerker-tipps">
                        @error('slug')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="dash-label" for="cat-description">Beschreibung</label>
                        <textarea wire:model="description"
                                  id="cat-description"
                                  class="dash-textarea @error('description') dash-input-error @enderror"
                                  rows="2"
                                  placeholder="Kurze Beschreibung der Kategorie..."></textarea>
                        @error('description')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Parent + Sort Order --}}
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 160px;">
                            <label class="dash-label" for="cat-parent">Übergeordnete Kategorie</label>
                            <select wire:model="parent_id"
                                    id="cat-parent"
                                    class="dash-select">
                                <option value="">Keine (Hauptkategorie)</option>
                                @foreach($categories as $cat)
                                    @if($cat->id !== $editingId)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div style="width: 120px;">
                            <label class="dash-label" for="cat-sort">Reihenfolge</label>
                            <input type="number"
                                   wire:model="sort_order"
                                   id="cat-sort"
                                   class="dash-input"
                                   min="0">
                        </div>
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

    {{-- Category List (Drag-and-Drop) --}}
    @if($categories->count() > 0)
        <div x-data="{
                dragging: null,
                dragOver: null,
                items: @js($categories->pluck('id')->toArray()),
                startDrag(e, id) {
                    this.dragging = id;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', id);
                },
                onDragOver(e, id) {
                    e.preventDefault();
                    this.dragOver = id;
                },
                onDrop(e, targetId) {
                    e.preventDefault();
                    if (this.dragging === targetId) return;
                    const fromIndex = this.items.indexOf(this.dragging);
                    const toIndex = this.items.indexOf(targetId);
                    this.items.splice(fromIndex, 1);
                    this.items.splice(toIndex, 0, this.dragging);
                    $wire.updateOrder(this.items);
                    this.dragging = null;
                    this.dragOver = null;
                },
                endDrag() {
                    this.dragging = null;
                    this.dragOver = null;
                }
            }"
             style="display: flex; flex-direction: column; gap: 0.5rem;">

            @foreach($categories as $category)
                <div wire:key="cat-{{ $category->id }}"
                     draggable="true"
                     x-on:dragstart="startDrag($event, {{ $category->id }})"
                     x-on:dragover="onDragOver($event, {{ $category->id }})"
                     x-on:drop="onDrop($event, {{ $category->id }})"
                     x-on:dragend="endDrag()"
                     class="dash-card"
                     style="padding: 1rem; cursor: grab; transition: opacity 150ms, border-color 150ms;"
                     :style="dragging === {{ $category->id }} ? 'opacity: 0.4' : (dragOver === {{ $category->id }} ? 'border-color: var(--portal-primary)' : '')">

                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        {{-- Drag Handle + Checkbox --}}
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding-top: 0.125rem;">
                            <svg class="w-4 h-4" style="color: var(--dash-text-muted); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $category->id }}"
                                   style="accent-color: var(--portal-primary);"
                                   aria-label="{{ $category->name }} auswählen">
                        </div>

                        {{-- Content --}}
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                <h4 style="font-size: 0.9375rem; font-weight: 600; color: var(--dash-text-primary); margin: 0;">
                                    @if($category->parent)
                                        <span style="color: var(--dash-text-muted);">{{ $category->parent->name }} →</span>
                                    @endif
                                    {{ $category->name }}
                                </h4>

                                <span class="dash-badge dash-badge-neutral">
                                    {{ $category->posts_count }} {{ $category->posts_count === 1 ? 'Artikel' : 'Artikel' }}
                                </span>
                            </div>

                            @if($category->description)
                                <p style="font-size: 0.8125rem; color: var(--dash-text-secondary); margin: 0; line-height: 1.5;">
                                    {{ $category->description }}
                                </p>
                            @endif

                            <p style="font-size: 0.75rem; color: var(--dash-text-muted); margin: 0.25rem 0 0;">
                                /ratgeber/kategorie/{{ $category->slug }}
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div style="display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0;">
                            <button wire:click="openEdit({{ $category->id }})"
                                    class="dash-btn-icon"
                                    title="Bearbeiten">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                </svg>
                            </button>

                            <button wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="Kategorie löschen? Untergeordnete Kategorien werden der übergeordneten Kategorie zugewiesen."
                                    class="dash-btn-icon dash-btn-danger"
                                    title="Löschen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Empty State --}}
        <div class="dash-card">
            <div class="dash-empty">
                <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                </svg>
                <p class="dash-empty-title">Noch keine Blog-Kategorien</p>
                <p class="dash-empty-description">Erstellen Sie Kategorien, um Ihre Ratgeber-Artikel thematisch zu ordnen.</p>
                <button wire:click="openCreate" class="dash-btn dash-btn-sm dash-btn-primary">
                    Erste Kategorie erstellen
                </button>
            </div>
        </div>
    @endif
</div>
