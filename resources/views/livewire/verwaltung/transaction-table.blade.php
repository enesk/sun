<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-error">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            <div class="dash-filter-search">
                <svg class="dash-filter-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suche nach Plan..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>
            <div class="dash-filter-actions">
                <select wire:model.live="filterStatus"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Status filtern">
                    <option value="">Alle Status</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if($search || $filterStatus)
                    <button wire:click="$set('search', ''); $set('filterStatus', '');"
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

    {{-- Table --}}
    @if($transactions->isEmpty())
        <div class="dash-card">
            <div class="dash-empty">
                <svg class="dash-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
                <p class="dash-empty-title">Keine Zahlungen</p>
                <p class="dash-empty-description">Es sind noch keine Transaktionen vorhanden.</p>
            </div>
        </div>
    @else
        <div class="dash-card overflow-hidden">
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                <button wire:click="sort('amount')" class="dash-table-sort {{ $sortBy === 'amount' ? 'dash-table-sort-active' : '' }}">
                                    Betrag
                                    @if($sortBy === 'amount')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col">
                                <button wire:click="sort('status')" class="dash-table-sort {{ $sortBy === 'status' ? 'dash-table-sort-active' : '' }}">
                                    Status
                                    @if($sortBy === 'status')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col">Zugehörig</th>
                            <th scope="col">
                                <button wire:click="sort('created_at')" class="dash-table-sort {{ $sortBy === 'created_at' ? 'dash-table-sort-active' : '' }}">
                                    Datum
                                    @if($sortBy === 'created_at')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr>
                                <td>
                                    <span class="font-medium" style="color: var(--dash-text-primary);">{{ $tx->formatted_amount }}</span>
                                </td>
                                <td>
                                    <span class="dash-badge dash-badge-{{ $tx->status_color === 'success' ? 'success' : 'warning' }}">
                                        {{ $tx->status_label }}
                                    </span>
                                </td>
                                <td>
                                    <span style="color: var(--dash-text-secondary);">
                                        @if($tx->owner_type === 'subscription')
                                            {{ $tx->owner_label }}
                                        @elseif($tx->owner_type === 'order' && $tx->owner_uuid)
                                            <a href="{{ route('verwaltung.orders.show', $tx->owner_uuid) }}"
                                               style="color: var(--portal-primary); text-decoration: none;"
                                               onmouseover="this.style.textDecoration='underline'"
                                               onmouseout="this.style.textDecoration='none'">
                                                {{ $tx->owner_label }}
                                            </a>
                                        @else
                                            {{ $tx->owner_label }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span style="color: var(--dash-text-secondary);">{{ $tx->created_at->format('d.m.Y H:i') }}</span>
                                </td>
                                <td class="text-right">
                                    @if($tx->can_download_invoice)
                                        <button wire:click="downloadInvoice({{ $tx->id }})"
                                                class="dash-btn dash-btn-ghost dash-btn-sm">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            Rechnung
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card-List --}}
            <div class="dash-mobile-cards">
                @foreach($transactions as $tx)
                    <div class="dash-mobile-card">
                        <div class="dash-mobile-card-header">
                            <span class="dash-mobile-card-title">{{ $tx->formatted_amount }}</span>
                            <span class="dash-badge dash-badge-{{ $tx->status_color === 'success' ? 'success' : 'warning' }}">{{ $tx->status_label }}</span>
                        </div>
                        <div class="dash-mobile-card-meta">
                            <span>{{ $tx->owner_label }}</span>
                            <span>{{ $tx->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        @if($tx->can_download_invoice)
                            <div class="dash-mobile-card-actions">
                                <button wire:click="downloadInvoice({{ $tx->id }})" class="dash-btn dash-btn-sm dash-btn-secondary">Rechnung</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($transactions->hasPages())
                <div class="dash-pagination">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
