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
                       placeholder="Suche nach Bestell-ID oder Produkt..."
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
    @if($orders->isEmpty())
        <div class="dash-card">
            <div class="dash-empty">
                <svg class="dash-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
                <p class="dash-empty-title">Keine Bestellungen</p>
                <p class="dash-empty-description">Es sind noch keine Bestellungen vorhanden.</p>
            </div>
        </div>
    @else
        <div class="dash-card overflow-hidden">
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th scope="col">Bestell-ID</th>
                            <th scope="col">
                                <button wire:click="sort('total_amount')" class="dash-table-sort {{ $sortBy === 'total_amount' ? 'dash-table-sort-active' : '' }}">
                                    Betrag
                                    @if($sortBy === 'total_amount')
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
                            <th scope="col" class="hidden md:table-cell">Produkte</th>
                            <th scope="col">
                                <button wire:click="sort('updated_at')" class="dash-table-sort {{ $sortBy === 'updated_at' ? 'dash-table-sort-active' : '' }}">
                                    Datum
                                    @if($sortBy === 'updated_at')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <span class="font-mono text-xs" style="color: var(--dash-text-primary);">#{{ substr($order->uuid, 0, 8) }}</span>
                                </td>
                                <td>
                                    <span class="font-medium" style="color: var(--dash-text-primary);">{{ $order->formatted_amount }}</span>
                                </td>
                                <td>
                                    @php
                                        $colorMap = [
                                            'success' => 'success',
                                            'danger' => 'danger',
                                            'warning' => 'warning',
                                        ];
                                        $badgeColor = $colorMap[$order->status_color] ?? 'warning';
                                    @endphp
                                    <span class="dash-badge dash-badge-{{ $badgeColor }}">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td class="hidden md:table-cell">
                                    <span style="color: var(--dash-text-secondary);">
                                        @if($order->items->isNotEmpty())
                                            {{ $order->items->map(fn($i) => $i->oneTimeProduct->name ?? '-')->join(', ') }}
                                        @else
                                            —
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span style="color: var(--dash-text-secondary);">{{ $order->updated_at->format('d.m.Y H:i') }}</span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('verwaltung.orders.show', $order->uuid) }}"
                                       class="dash-btn dash-btn-ghost dash-btn-sm">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($orders->hasPages())
                <div class="dash-pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
