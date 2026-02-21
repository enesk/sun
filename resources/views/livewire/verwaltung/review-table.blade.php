<div>
    {{-- Status Tabs --}}
    <div class="dash-tab-bar mb-4">
        @php
            $tabs = [
                '' => ['label' => 'Alle', 'count' => $statusCounts['all']],
                'pending' => ['label' => 'Ausstehend', 'count' => $statusCounts['pending']],
                'approved' => ['label' => 'Freigegeben', 'count' => $statusCounts['approved']],
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
                       placeholder="Autor, Titel, Text oder Firmenname suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
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

                <select wire:model.live="filterRating"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Bewertung filtern">
                    <option value="">Alle Sterne</option>
                    @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'Stern' : 'Sterne' }}</option>
                    @endfor
                </select>

                @if($search || $filterCompany || $filterRating)
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
                    Freigeben
                </button>
                <button wire:click="openBulkRejectModal"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-danger-light); color: var(--dash-danger); border-color: var(--dash-danger-border);">
                    Ablehnen
                </button>
            </div>
        @endif
    </div>

    {{-- Review Cards --}}
    <div class="space-y-3">
        @forelse($reviews as $review)
            <div class="dash-card dash-card-padded" wire:key="review-{{ $review->id }}">
                <div class="flex items-start gap-3">
                    {{-- Checkbox --}}
                    <input type="checkbox"
                           wire:model.live="selected"
                           value="{{ $review->id }}"
                           class="mt-1 shrink-0"
                           style="accent-color: var(--portal-primary, #3b82f6);"
                           aria-label="Bewertung auswählen">

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        {{-- Header: Stars + Author + Status --}}
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            {{-- Sterne --}}
                            <div class="flex items-center gap-0.5" aria-label="{{ number_format($review->rating, 1) }} von 5 Sternen">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"
                                         style="color: {{ $i <= $review->rating ? '#facc15' : 'var(--dash-border, rgba(0,0,0,0.08))' }};">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                                <span class="text-xs ml-1" style="color: var(--dash-text-muted);">{{ number_format($review->rating, 1) }}</span>
                            </div>

                            {{-- Status Badge --}}
                            @if($review->isPending())
                                <span class="dash-badge dash-badge-warning">Ausstehend</span>
                            @elseif($review->isApproved())
                                <span class="dash-badge dash-badge-success">Freigegeben</span>
                            @elseif($review->isRejected())
                                <span class="dash-badge dash-badge-danger">Abgelehnt</span>
                            @endif

                            {{-- Datum --}}
                            <span class="text-xs" style="color: var(--dash-text-muted);">
                                {{ $review->created_at->format('d.m.Y H:i') }}
                            </span>
                        </div>

                        {{-- Title --}}
                        @if($review->title)
                            <h3 class="text-sm font-semibold mb-1" style="color: var(--dash-text-primary);">{{ $review->title }}</h3>
                        @endif

                        {{-- Body --}}
                        @if($review->body)
                            <p class="text-sm mb-2 line-clamp-3" style="color: var(--dash-text-secondary);">{{ $review->body }}</p>
                        @endif

                        {{-- Meta: Author + Company --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--dash-text-muted);">
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                                {{ $review->author_name ?: 'Anonym' }}
                            </span>
                            @if($review->company)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                                    </svg>
                                    {{ $review->company->name }}
                                </span>
                            @endif
                            @if($review->moderation_note)
                                <span class="inline-flex items-center gap-1" style="color: var(--dash-warning);" title="{{ $review->moderation_note }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                                    </svg>
                                    Notiz vorhanden
                                </span>
                            @endif
                            @if($review->moderated_by)
                                <span>Moderiert von {{ $review->moderated_by }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-1 shrink-0">
                        @if(! $review->isApproved())
                            <button wire:click="approveReview({{ $review->id }})"
                                    wire:confirm="Bewertung von &quot;{{ $review->author_name ?: 'Anonym' }}&quot; freigeben?"
                                    class="dash-btn-icon"
                                    style="color: var(--dash-success);"
                                    title="Freigeben">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>
                        @endif

                        @if(! $review->isRejected())
                            <button wire:click="openRejectModal({{ $review->id }})"
                                    class="dash-btn-icon dash-btn-danger"
                                    title="Ablehnen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </button>
                        @endif

                        @if($isAdmin)
                            <button wire:click="deleteReview({{ $review->id }})"
                                    wire:confirm="Bewertung unwiderruflich löschen?"
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                    </svg>
                    @if($search || $filterStatus || $filterCompany || $filterRating)
                        <p class="dash-empty-title">Keine Bewertungen gefunden</p>
                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen oder Filtern.</p>
                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                            Filter zurücksetzen
                        </button>
                    @else
                        <p class="dash-empty-title">Noch keine Bewertungen vorhanden</p>
                        <p class="dash-empty-description">Bewertungen erscheinen hier, sobald Nutzer welche abgeben.</p>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($reviews->hasPages())
        <div class="dash-pagination mt-4">
            {{ $reviews->links() }}
        </div>
    @endif

    {{-- Result count --}}
    <div class="dash-result-count mt-2" style="border-top: none;">
        {{ $reviews->total() }} {{ $reviews->total() === 1 ? 'Bewertung' : 'Bewertungen' }} gefunden
    </div>

    {{-- ================================================================ --}}
    {{-- REJECT MODAL                                                    --}}
    {{-- ================================================================ --}}
    @if($showRejectModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title">
            <div class="dash-modal-backdrop" wire:click="$set('showRejectModal', false)"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <h3 id="reject-modal-title" class="dash-modal-title">
                        {{ $isBulkReject ? count($selected) . ' Bewertungen ablehnen' : 'Bewertung ablehnen' }}
                    </h3>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm mb-4" style="color: var(--dash-text-secondary);">
                        {{ $isBulkReject
                            ? 'Geben Sie optional einen Grund für die Ablehnung an.'
                            : 'Geben Sie optional einen internen Grund an (wird dem Autor nicht angezeigt).' }}
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
</div>
