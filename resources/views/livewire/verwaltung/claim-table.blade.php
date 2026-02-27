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
                       placeholder="Firmenname, Antragsteller oder E-Mail suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;"
                       aria-label="Claim-Anträge durchsuchen">
            </div>

            {{-- Reset --}}
            <div class="dash-filter-actions">
                @if($search || $filterStatus !== 'pending')
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
    </div>

    {{-- Claim Cards --}}
    <div class="space-y-3">
        @forelse($claims as $claim)
            <div class="dash-claim-card" wire:key="claim-{{ $claim->id }}" wire:click="openDetail({{ $claim->id }})" role="button" tabindex="0"
                 @keydown.enter="$wire.openDetail({{ $claim->id }})">
                <div class="dash-claim-card__main">
                    {{-- Company Info --}}
                    <div class="dash-claim-card__company">
                        {{-- Logo --}}
                        <div class="dash-claim-card__logo">
                            @if($claim->company && $claim->company->hasMedia('logo'))
                                <img src="{{ $claim->company->getFirstMediaUrl('logo', 'thumb') }}"
                                     alt="{{ $claim->company->name }}"
                                     class="dash-claim-card__logo-img">
                            @else
                                <div class="dash-claim-card__logo-placeholder">
                                    {{ $claim->company ? mb_substr($claim->company->name, 0, 1) : '?' }}
                                </div>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="dash-claim-card__info">
                            <h3 class="dash-claim-card__company-name">
                                {{ $claim->company->name ?? 'Unbekannte Firma' }}
                            </h3>
                            <div class="dash-claim-card__meta">
                                {{-- Antragsteller --}}
                                <span class="dash-claim-card__meta-item">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    {{ $claim->user->name ?? 'Unbekannt' }}
                                </span>

                                {{-- E-Mail --}}
                                <span class="dash-claim-card__meta-item">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                    </svg>
                                    {{ $claim->user->email ?? '' }}
                                </span>

                                {{-- Datum --}}
                                <span class="dash-claim-card__meta-item">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ $claim->created_at->format('d.m.Y H:i') }}
                                </span>

                                {{-- Dokumente --}}
                                <span class="dash-claim-card__meta-item">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                    {{ $claim->document_count }} {{ $claim->document_count === 1 ? 'Dokument' : 'Dokumente' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Status Badge + Arrow --}}
                    <div class="dash-claim-card__actions">
                        @if($claim->isPending())
                            <span class="dash-badge dash-badge-warning">Ausstehend</span>
                        @elseif($claim->isApproved())
                            <span class="dash-badge dash-badge-success">Genehmigt</span>
                        @elseif($claim->isRejected())
                            <span class="dash-badge dash-badge-danger">Abgelehnt</span>
                        @elseif($claim->isCancelled())
                            <span class="dash-badge">Storniert</span>
                        @endif

                        <svg class="dash-claim-card__arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </div>
                </div>

                {{-- Kommentar --}}
                @if($claim->comment)
                    <div class="dash-claim-card__comment">
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                        </svg>
                        <span class="line-clamp-1">{{ $claim->comment }}</span>
                    </div>
                @endif

                {{-- Ablehnungsgrund (nur bei rejected) --}}
                @if($claim->isRejected() && $claim->rejection_reason)
                    <div class="dash-claim-card__rejection">
                        <strong>Abgelehnt:</strong> {{ $claim->rejection_reason }}
                    </div>
                @endif
            </div>
        @empty
            <div class="dash-card">
                <div class="dash-empty">
                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                    @if($search || $filterStatus)
                        <p class="dash-empty-title">Keine Claim-Anträge gefunden</p>
                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen oder Filtern.</p>
                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                            Filter zurücksetzen
                        </button>
                    @else
                        <p class="dash-empty-title">Keine Claim-Anträge vorhanden</p>
                        <p class="dash-empty-description">Claim-Anträge erscheinen hier, sobald Firmeninhaber ihre Firmen übernehmen möchten.</p>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($claims->hasPages())
        <div class="dash-pagination mt-4">
            {{ $claims->links() }}
        </div>
    @endif

    {{-- Result count --}}
    <div class="dash-result-count mt-2" style="border-top: none;">
        {{ $claims->total() }} {{ $claims->total() === 1 ? 'Claim-Antrag' : 'Claim-Anträge' }} gefunden
    </div>

    {{-- ================================================================ --}}
    {{-- SLIDE-OVER DETAIL                                                --}}
    {{-- ================================================================ --}}
    @if($showDetail && $detailClaim)
        <div class="dash-slideover-overlay"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.closeDetail()">
            <div class="dash-slideover-backdrop" wire:click="closeDetail"></div>

            <div class="dash-slideover" role="dialog" aria-modal="true" aria-labelledby="claim-detail-title">
                {{-- Header --}}
                <div class="dash-slideover__header">
                    <h2 id="claim-detail-title" class="dash-slideover__title">Claim-Antrag prüfen</h2>
                    <button wire:click="closeDetail" class="dash-btn-icon" aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Content --}}
                <div class="dash-slideover__body">
                    {{-- Firmen-Header --}}
                    <div class="dash-claim-detail__company-header">
                        <div class="dash-claim-card__logo" style="width: 56px; height: 56px; font-size: 1.25rem;">
                            @if($detailClaim->company && $detailClaim->company->hasMedia('logo'))
                                <img src="{{ $detailClaim->company->getFirstMediaUrl('logo', 'thumb') }}"
                                     alt="{{ $detailClaim->company->name }}"
                                     class="dash-claim-card__logo-img">
                            @else
                                <div class="dash-claim-card__logo-placeholder" style="width: 56px; height: 56px; font-size: 1.25rem;">
                                    {{ $detailClaim->company ? mb_substr($detailClaim->company->name, 0, 1) : '?' }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-base font-semibold" style="color: var(--dash-text-primary);">
                                {{ $detailClaim->company->name ?? 'Unbekannt' }}
                            </h3>
                            @if($detailClaim->company)
                                <p class="text-xs" style="color: var(--dash-text-muted);">
                                    {{ $detailClaim->company->street }} {{ $detailClaim->company->house_no }},
                                    {{ $detailClaim->company->zip }} {{ $detailClaim->company->city?->name ?? '' }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="dash-claim-detail__section">
                        <h4 class="dash-claim-detail__section-title">Status</h4>
                        <div class="flex items-center gap-2">
                            @if($detailClaim->isPending())
                                <span class="dash-badge dash-badge-warning">Ausstehend</span>
                            @elseif($detailClaim->isApproved())
                                <span class="dash-badge dash-badge-success">Genehmigt</span>
                            @elseif($detailClaim->isRejected())
                                <span class="dash-badge dash-badge-danger">Abgelehnt</span>
                            @endif
                            <span class="text-xs" style="color: var(--dash-text-muted);">
                                Eingereicht am {{ $detailClaim->created_at->format('d.m.Y \u\m H:i') }} Uhr
                            </span>
                        </div>
                    </div>

                    {{-- Antragsteller --}}
                    <div class="dash-claim-detail__section">
                        <h4 class="dash-claim-detail__section-title">Antragsteller</h4>
                        <div class="dash-claim-detail__info-grid">
                            <div>
                                <span class="dash-claim-detail__label">Name</span>
                                <span class="dash-claim-detail__value">{{ $detailClaim->user->name ?? 'Unbekannt' }}</span>
                            </div>
                            <div>
                                <span class="dash-claim-detail__label">E-Mail</span>
                                <span class="dash-claim-detail__value">{{ $detailClaim->user->email ?? '' }}</span>
                            </div>
                            <div>
                                <span class="dash-claim-detail__label">Registriert</span>
                                <span class="dash-claim-detail__value">{{ $detailClaim->user?->created_at?->format('d.m.Y') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Kommentar --}}
                    @if($detailClaim->comment)
                        <div class="dash-claim-detail__section">
                            <h4 class="dash-claim-detail__section-title">Kommentar des Antragstellers</h4>
                            <p class="text-sm" style="color: var(--dash-text-secondary);">{{ $detailClaim->comment }}</p>
                        </div>
                    @endif

                    {{-- Dokumente --}}
                    <div class="dash-claim-detail__section">
                        <h4 class="dash-claim-detail__section-title">
                            Verifizierungsdokumente
                            <span class="dash-claim-detail__doc-count">({{ $detailClaim->document_count }})</span>
                        </h4>

                        @if($detailClaim->has_documents)
                            <div class="dash-claim-detail__docs">
                                @foreach($detailClaim->getMedia('claim_documents') as $document)
                                    <a href="{{ $document->getUrl() }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="dash-claim-detail__doc-item">
                                        {{-- Thumbnail oder Icon --}}
                                        @if(str_starts_with($document->mime_type, 'image/'))
                                            <img src="{{ $document->getUrl('thumb') }}"
                                                 alt="{{ $document->file_name }}"
                                                 class="dash-claim-detail__doc-thumb">
                                        @else
                                            <div class="dash-claim-detail__doc-icon">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="dash-claim-detail__doc-info">
                                            <span class="dash-claim-detail__doc-name">{{ $document->file_name }}</span>
                                            <span class="dash-claim-detail__doc-size">{{ number_format($document->size / 1024, 0) }} KB</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm" style="color: var(--dash-text-muted);">Keine Dokumente hochgeladen.</p>
                        @endif
                    </div>

                    {{-- Reviewer-Info (wenn bereits bearbeitet) --}}
                    @if($detailClaim->reviewed_at)
                        <div class="dash-claim-detail__section">
                            <h4 class="dash-claim-detail__section-title">Bearbeitung</h4>
                            <div class="dash-claim-detail__info-grid">
                                <div>
                                    <span class="dash-claim-detail__label">Bearbeitet von</span>
                                    <span class="dash-claim-detail__value">{{ $detailClaim->reviewer?->name ?? 'Unbekannt' }}</span>
                                </div>
                                <div>
                                    <span class="dash-claim-detail__label">Datum</span>
                                    <span class="dash-claim-detail__value">{{ $detailClaim->reviewed_at->format('d.m.Y H:i') }}</span>
                                </div>
                            </div>
                            @if($detailClaim->isRejected() && $detailClaim->rejection_reason)
                                <div class="dash-claim-detail__rejection-reason mt-3">
                                    <strong>Ablehnungsgrund:</strong> {{ $detailClaim->rejection_reason }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Footer Actions (nur bei pending) --}}
                @if($detailClaim->isPending())
                    <div class="dash-slideover__footer">
                        {{-- Reject Section --}}
                        <div class="dash-claim-detail__reject-section">
                            <select wire:model="rejectionReasonKey"
                                    class="dash-select dash-btn-sm"
                                    style="width: 100%; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem; font-size: 0.8125rem;"
                                    aria-label="Ablehnungsgrund wählen">
                                <option value="">Ablehnungsgrund wählen...</option>
                                @foreach(\App\Models\Portal\ClaimRequest::REJECTION_REASONS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @if($rejectionReasonKey === 'other')
                                <textarea wire:model="rejectionReason"
                                          rows="2"
                                          placeholder="Bitte beschreiben Sie den Ablehnungsgrund..."
                                          class="dash-textarea"
                                          style="font-size: 0.8125rem; min-height: auto;"></textarea>
                            @endif
                        </div>

                        <div class="dash-claim-detail__action-buttons">
                            <button wire:click="rejectClaim({{ $detailClaim->id }})"
                                    wire:confirm="Claim-Antrag wirklich ablehnen? Der Antragsteller wird per E-Mail benachrichtigt."
                                    class="dash-btn dash-btn-danger"
                                    @if(empty($rejectionReasonKey)) disabled @endif>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                Ablehnen
                            </button>

                            <button wire:click="approveClaim({{ $detailClaim->id }})"
                                    wire:confirm="Claim genehmigen? Die Firma wird dem Antragsteller sofort zugewiesen und der Trial startet."
                                    class="dash-btn dash-btn-primary"
                                    style="background: var(--dash-success); border-color: var(--dash-success);">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Genehmigen
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
