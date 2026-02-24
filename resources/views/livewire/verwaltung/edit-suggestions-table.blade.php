<div>
    {{-- Status Tabs --}}
    <div class="dash-tab-bar mb-4">
        @php
            $tabs = [
                '' => ['label' => 'Alle', 'count' => $statusCounts['all']],
                'pending' => ['label' => 'Ausstehend', 'count' => $statusCounts['pending']],
                'approved' => ['label' => 'Genehmigt', 'count' => $statusCounts['approved']],
                'rejected' => ['label' => 'Abgelehnt', 'count' => $statusCounts['rejected']],
            ];
        @endphp

        @foreach($tabs as $value => $tab)
            <button wire:click="$set('filterStatus', '{{ $value }}')"
                    class="dash-tab {{ $filterStatus === $value ? 'dash-tab-active' : '' }}">
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="dash-tab-count {{ $value === 'pending' && $tab['count'] > 0 ? 'dash-tab-count-warning' : '' }}">
                        {{ $tab['count'] }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            {{-- Search --}}
            <div class="dash-filter-search">
                <svg class="dash-filter-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Firma, Vorschlag oder Name suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;"
                       aria-label="Änderungsvorschläge durchsuchen">
            </div>

            {{-- Filters --}}
            <div class="dash-filter-actions">
                <select wire:model.live="filterCompany"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Firma filtern">
                    <option value="">Alle Firmen</option>
                    @foreach($companies as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterField"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Feld filtern">
                    <option value="">Alle Felder</option>
                    @foreach(\App\Models\Portal\CompanyEditSuggestion::FIELDS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if($search || $filterCompany || $filterField)
                    <button wire:click="resetFilters"
                            class="dash-btn-icon"
                            title="Filter zurücksetzen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Bulk Actions --}}
        @if(count($selected) > 0)
            <div class="dash-table-bulk">
                <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                <button wire:click="bulkApprove"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-success-light); color: var(--dash-success); border-color: var(--dash-success-border);">
                    Genehmigen
                </button>
                <button wire:click="openBulkRejectModal"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-danger-light); color: var(--dash-danger); border-color: var(--dash-danger-border);">
                    Ablehnen
                </button>
            </div>
        @endif
    </div>

    {{-- Suggestion Cards --}}
    <div class="space-y-3">
        @forelse($suggestions as $suggestion)
            <div class="dash-card dash-card-padded" wire:key="suggestion-{{ $suggestion->id }}">
                <div class="flex items-start gap-3">
                    {{-- Checkbox --}}
                    <input type="checkbox"
                           wire:model.live="selected"
                           value="{{ $suggestion->id }}"
                           class="mt-1 shrink-0"
                           style="accent-color: var(--portal-primary, #3b82f6);"
                           aria-label="Vorschlag auswählen">

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        {{-- Header: Field Badge + Status + Date --}}
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            {{-- Field Badge --}}
                            <span class="dash-badge dash-badge-neutral">
                                {{ $suggestion->field_label }}
                            </span>

                            {{-- Status Badge --}}
                            @if($suggestion->is_pending)
                                <span class="dash-badge dash-badge-warning">Ausstehend</span>
                            @elseif($suggestion->status === 'approved')
                                <span class="dash-badge dash-badge-success">Genehmigt</span>
                            @elseif($suggestion->status === 'rejected')
                                <span class="dash-badge dash-badge-danger">Abgelehnt</span>
                            @endif

                            {{-- Datum --}}
                            <span class="text-xs" style="color: var(--dash-text-muted);">
                                {{ $suggestion->created_at->format('d.m.Y H:i') }}
                            </span>
                        </div>

                        {{-- Company Name --}}
                        @if($suggestion->company)
                            <h3 class="text-sm font-semibold mb-1" style="color: var(--dash-text-primary);">
                                {{ $suggestion->company->name }}
                            </h3>
                        @endif

                        {{-- Suggested Value --}}
                        <div class="text-sm mb-2 p-2 rounded-lg" style="background: var(--dash-bg, #f8fafc); border: 1px solid var(--dash-border, rgba(0,0,0,0.08));">
                            <p class="line-clamp-3" style="color: var(--dash-text-secondary);">{{ $suggestion->suggested_value }}</p>
                        </div>

                        {{-- Reason --}}
                        @if($suggestion->reason)
                            <p class="text-xs mb-2" style="color: var(--dash-text-muted);">
                                <span class="font-medium">Begründung:</span> {{ Str::limit($suggestion->reason, 100) }}
                            </p>
                        @endif

                        {{-- Meta: Reporter + Company --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--dash-text-muted);">
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                                {{ $suggestion->reporter_name ?: 'Anonym' }}
                            </span>
                            @if($suggestion->reporter_email)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                    </svg>
                                    {{ $suggestion->reporter_email }}
                                </span>
                            @endif
                            @if($suggestion->reviewer)
                                <span>Geprüft von {{ $suggestion->reviewer->name }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-1 shrink-0">
                        {{-- Detail --}}
                        <button wire:click="showDetail({{ $suggestion->id }})"
                                class="dash-btn-icon"
                                title="Details anzeigen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </button>

                        @if($suggestion->is_pending)
                            <button wire:click="approveSuggestion({{ $suggestion->id }})"
                                    wire:confirm="Änderungsvorschlag genehmigen?"
                                    class="dash-btn-icon"
                                    style="color: var(--dash-success);"
                                    title="Genehmigen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>

                            <button wire:click="openRejectModal({{ $suggestion->id }})"
                                    class="dash-btn-icon dash-btn-danger"
                                    title="Ablehnen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </button>
                        @endif

                        @if($isAdmin)
                            <button wire:click="deleteSuggestion({{ $suggestion->id }})"
                                    wire:confirm="Vorschlag unwiderruflich löschen?"
                                    class="dash-btn-icon dash-btn-danger"
                                    title="Löschen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="dash-card">
                <div class="dash-empty">
                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                    </svg>
                    @if($search || $filterStatus || $filterCompany || $filterField)
                        <p class="dash-empty-title">Keine Vorschläge gefunden</p>
                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen oder Filtern.</p>
                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                            Filter zurücksetzen
                        </button>
                    @else
                        <p class="dash-empty-title">Noch keine Änderungsvorschläge</p>
                        <p class="dash-empty-description">Vorschläge erscheinen hier, sobald Besucher Änderungen für Firmeneinträge vorschlagen.</p>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($suggestions->hasPages())
        <div class="dash-pagination mt-4">
            {{ $suggestions->links() }}
        </div>
    @endif

    {{-- Result count --}}
    <div class="dash-result-count mt-2" style="border-top: none;">
        {{ $suggestions->total() }} {{ $suggestions->total() === 1 ? 'Vorschlag' : 'Vorschläge' }} gefunden
    </div>

    {{-- ================================================================ --}}
    {{-- REJECT MODAL                                                    --}}
    {{-- ================================================================ --}}
    @if($showRejectModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.set('showRejectModal', false)">
            <div class="dash-modal-backdrop" wire:click="$set('showRejectModal', false)"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <h3 id="reject-modal-title" class="dash-modal-title">
                        {{ $isBulkReject ? count($selected) . ' Vorschläge ablehnen' : 'Vorschlag ablehnen' }}
                    </h3>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm mb-4" style="color: var(--dash-text-secondary);">
                        {{ $isBulkReject
                            ? 'Geben Sie optional einen Grund für die Ablehnung an.'
                            : 'Geben Sie optional einen internen Grund an.' }}
                    </p>

                    <textarea wire:model="rejectReason"
                              rows="3"
                              placeholder="Grund für die Ablehnung (optional)..."
                              class="dash-textarea"></textarea>
                </div>
                <div class="dash-modal-footer">
                    <button type="button"
                            wire:click="$set('showRejectModal', false)"
                            class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <button type="button"
                            wire:click="confirmReject"
                            class="dash-btn dash-btn-danger">
                        Ablehnen
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================ --}}
    {{-- DETAIL MODAL                                                    --}}
    {{-- ================================================================ --}}
    @if($showDetailModal && $detailSuggestion)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.call('closeDetail')">
            <div class="dash-modal-backdrop" wire:click="closeDetail"></div>

            <div class="dash-modal" style="max-width: 36rem;">
                <div class="dash-modal-header">
                    <h3 id="detail-modal-title" class="dash-modal-title">
                        Änderungsvorschlag #{{ $detailSuggestion->id }}
                    </h3>
                    <button wire:click="closeDetail" class="dash-btn-icon" title="Schließen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="dash-modal-body">
                    <dl class="space-y-3">
                        {{-- Firma --}}
                        <div>
                            <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Firma</dt>
                            <dd class="text-sm font-semibold" style="color: var(--dash-text-primary);">
                                {{ $detailSuggestion->company->name ?? 'Unbekannt' }}
                            </dd>
                        </div>

                        {{-- Feld --}}
                        <div>
                            <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Geändertes Feld</dt>
                            <dd>
                                <span class="dash-badge dash-badge-neutral">{{ $detailSuggestion->field_label }}</span>
                            </dd>
                        </div>

                        {{-- Status --}}
                        <div>
                            <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Status</dt>
                            <dd>
                                @if($detailSuggestion->is_pending)
                                    <span class="dash-badge dash-badge-warning">Ausstehend</span>
                                @elseif($detailSuggestion->status === 'approved')
                                    <span class="dash-badge dash-badge-success">Genehmigt</span>
                                @elseif($detailSuggestion->status === 'rejected')
                                    <span class="dash-badge dash-badge-danger">Abgelehnt</span>
                                @endif
                            </dd>
                        </div>

                        {{-- Vorgeschlagener Wert --}}
                        <div>
                            <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Vorgeschlagener Wert</dt>
                            <dd class="text-sm p-3 rounded-lg mt-1" style="background: var(--dash-bg, #f8fafc); border: 1px solid var(--dash-border, rgba(0,0,0,0.08)); color: var(--dash-text-secondary);">
                                {{ $detailSuggestion->suggested_value }}
                            </dd>
                        </div>

                        {{-- Begründung --}}
                        @if($detailSuggestion->reason)
                            <div>
                                <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Begründung</dt>
                                <dd class="text-sm" style="color: var(--dash-text-secondary);">
                                    {{ $detailSuggestion->reason }}
                                </dd>
                            </div>
                        @endif

                        {{-- Reporter --}}
                        <div>
                            <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Vorgeschlagen von</dt>
                            <dd class="text-sm" style="color: var(--dash-text-secondary);">
                                {{ $detailSuggestion->reporter_name ?: 'Anonym' }}
                                @if($detailSuggestion->reporter_email)
                                    ({{ $detailSuggestion->reporter_email }})
                                @endif
                            </dd>
                        </div>

                        {{-- Eingereicht am --}}
                        <div>
                            <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Eingereicht am</dt>
                            <dd class="text-sm" style="color: var(--dash-text-secondary);">
                                {{ $detailSuggestion->created_at->format('d.m.Y \u\m H:i \U\h\r') }}
                            </dd>
                        </div>

                        {{-- Reviewer --}}
                        @if($detailSuggestion->reviewer)
                            <div>
                                <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">Geprüft von</dt>
                                <dd class="text-sm" style="color: var(--dash-text-secondary);">
                                    {{ $detailSuggestion->reviewer->name }}
                                    @if($detailSuggestion->reviewed_at)
                                        am {{ $detailSuggestion->reviewed_at->format('d.m.Y H:i') }}
                                    @endif
                                </dd>
                            </div>
                        @endif

                        {{-- IP (nur Admin) --}}
                        @if($isAdmin && $detailSuggestion->ip_address)
                            <div>
                                <dt class="text-xs font-medium" style="color: var(--dash-text-muted);">IP-Adresse</dt>
                                <dd class="text-sm font-mono" style="color: var(--dash-text-secondary);">
                                    {{ $detailSuggestion->ip_address }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <div class="dash-modal-footer">
                    @if($detailSuggestion->is_pending)
                        <button wire:click="approveSuggestion({{ $detailSuggestion->id }})"
                                wire:confirm="Änderungsvorschlag genehmigen?"
                                class="dash-btn dash-btn-sm"
                                style="background-color: var(--dash-success); color: white;">
                            Genehmigen
                        </button>
                        <button wire:click="openRejectModal({{ $detailSuggestion->id }})"
                                class="dash-btn dash-btn-sm dash-btn-danger">
                            Ablehnen
                        </button>
                    @endif
                    <button wire:click="closeDetail" class="dash-btn dash-btn-secondary">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
