<div>
    {{-- Header + Actions --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            <div class="flex items-center gap-3">
                <span style="color: var(--dash-text-secondary); font-size: 0.875rem;">
                    {{ $faqs->count() }} {{ $faqs->count() === 1 ? 'FAQ' : 'FAQs' }}
                </span>
            </div>

            <div class="dash-filter-actions">
                @if(count($selected) > 0)
                    <div class="dash-table-bulk">
                        <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                        <button wire:click="bulkDelete"
                                wire:confirm="Möchten Sie die ausgewählten FAQs wirklich löschen?"
                                class="dash-btn dash-btn-danger dash-btn-sm">
                            Löschen
                        </button>
                    </div>
                @endif

                @if(!$showForm)
                    <button wire:click="openCreateForm"
                            class="dash-btn dash-btn-primary dash-btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Neue FAQ
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
                    {{ $editingId ? 'FAQ bearbeiten' : 'Neue FAQ erstellen' }}
                </h3>

                <div class="dash-form-grid" style="gap: 1rem;">
                    {{-- Frage --}}
                    <div>
                        <label class="dash-label" for="faq-question">Frage *</label>
                        <input type="text"
                               wire:model="question"
                               id="faq-question"
                               class="dash-input @error('question') dash-input-error @enderror"
                               placeholder="z.B. Wie kann ich mein Firmenprofil bearbeiten?">
                        @error('question')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Antwort --}}
                    <div>
                        <label class="dash-label" for="faq-answer">Antwort *</label>
                        <textarea wire:model="answer"
                                  id="faq-answer"
                                  class="dash-textarea @error('answer') dash-input-error @enderror"
                                  rows="4"
                                  placeholder="Antwort auf die Frage..."></textarea>
                        @error('answer')
                            <p class="dash-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Seite + Aktiv --}}
                    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 160px;">
                            <label class="dash-label" for="faq-page">Anzeigen auf</label>
                            <select wire:model="page"
                                    id="faq-page"
                                    class="dash-select">
                                <option value="faq">Nur FAQ-Seite</option>
                                <option value="home">Startseite + FAQ-Seite</option>
                            </select>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.25rem;">
                            <input type="checkbox"
                                   wire:model="is_active"
                                   id="faq-active"
                                   style="accent-color: var(--portal-primary);">
                            <label for="faq-active" class="dash-label" style="margin-bottom: 0; font-weight: 400;">
                                Aktiv
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                    <button wire:click="cancelForm"
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

    {{-- FAQ List (Drag-and-Drop) --}}
    @if($faqs->count() > 0)
        <div x-data="{
                dragging: null,
                dragOver: null,
                items: @js($faqs->pluck('id')->toArray()),
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

            @foreach($faqs as $faq)
                <div wire:key="faq-{{ $faq->id }}"
                     draggable="true"
                     x-on:dragstart="startDrag($event, {{ $faq->id }})"
                     x-on:dragover="onDragOver($event, {{ $faq->id }})"
                     x-on:drop="onDrop($event, {{ $faq->id }})"
                     x-on:dragend="endDrag()"
                     class="dash-card"
                     style="padding: 1rem; cursor: grab; transition: opacity 150ms, border-color 150ms;"
                     :style="dragging === {{ $faq->id }} ? 'opacity: 0.4' : (dragOver === {{ $faq->id }} ? 'border-color: var(--portal-primary)' : '')">

                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        {{-- Drag Handle + Checkbox --}}
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding-top: 0.125rem;">
                            <svg class="w-4 h-4" style="color: var(--dash-text-muted); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $faq->id }}"
                                   style="accent-color: var(--portal-primary);"
                                   aria-label="{{ $faq->question }} auswählen">
                        </div>

                        {{-- Content --}}
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                <h4 style="font-size: 0.9375rem; font-weight: 600; color: var(--dash-text-primary); margin: 0;">
                                    {{ $faq->question }}
                                </h4>

                                {{-- Page Badge --}}
                                <span class="dash-badge {{ $faq->page === 'home' ? 'dash-badge-primary' : 'dash-badge-neutral' }}">
                                    {{ $faq->page === 'home' ? 'Startseite' : 'FAQ-Seite' }}
                                </span>

                                {{-- Active Badge --}}
                                @if(!$faq->is_active)
                                    <span class="dash-badge dash-badge-warning">Inaktiv</span>
                                @endif
                            </div>

                            <p style="font-size: 0.8125rem; color: var(--dash-text-secondary); margin: 0; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $faq->answer }}
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div style="display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0;">
                            {{-- Toggle Active --}}
                            <button wire:click="toggleActive({{ $faq->id }})"
                                    class="dash-btn-icon"
                                    title="{{ $faq->is_active ? 'Deaktivieren' : 'Aktivieren' }}">
                                @if($faq->is_active)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" style="color: var(--portal-primary);">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" style="color: var(--dash-text-muted);">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                    </svg>
                                @endif
                            </button>

                            {{-- Edit --}}
                            <button wire:click="edit({{ $faq->id }})"
                                    class="dash-btn-icon"
                                    title="Bearbeiten">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                </svg>
                            </button>

                            {{-- Delete --}}
                            <button wire:click="delete({{ $faq->id }})"
                                    wire:confirm="Möchten Sie diese FAQ wirklich löschen?"
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
                </svg>
                <p class="dash-empty-title">Noch keine FAQs vorhanden</p>
                <p class="dash-empty-description">Erstellen Sie häufig gestellte Fragen, um Ihren Nutzern schnelle Antworten zu bieten.</p>
                <button wire:click="openCreateForm" class="dash-btn dash-btn-sm dash-btn-primary">
                    Erste FAQ erstellen
                </button>
            </div>
        </div>
    @endif
</div>
