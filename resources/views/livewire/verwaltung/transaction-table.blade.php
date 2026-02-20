<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="card-portal p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="flex-1">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suche nach Plan..."
                       class="input-portal w-full text-sm">
            </div>
            {{-- Status Filter --}}
            <select wire:model.live="filterStatus" class="input-portal text-sm w-full sm:w-48">
                <option value="">Alle Status</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    @if($transactions->isEmpty())
        <div class="card-portal p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-base-200/50 flex items-center justify-center">
                <svg class="w-8 h-8 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-base-content mb-1">Keine Zahlungen</h3>
            <p class="text-sm text-base-content/60">Es sind noch keine Transaktionen vorhanden.</p>
        </div>
    @else
        <div class="card-portal overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-base-200 bg-base-100/50">
                            <th class="text-left p-4 font-medium text-base-content/60 cursor-pointer hover:text-base-content"
                                wire:click="sort('amount')">
                                Betrag
                                @if($sortBy === 'amount')
                                    <span class="text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="text-left p-4 font-medium text-base-content/60 cursor-pointer hover:text-base-content"
                                wire:click="sort('status')">
                                Status
                                @if($sortBy === 'status')
                                    <span class="text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="text-left p-4 font-medium text-base-content/60">
                                Zugehörig
                            </th>
                            <th class="text-left p-4 font-medium text-base-content/60 cursor-pointer hover:text-base-content"
                                wire:click="sort('created_at')">
                                Datum
                                @if($sortBy === 'created_at')
                                    <span class="text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="text-right p-4 font-medium text-base-content/60">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr class="border-b border-base-200/50 hover:bg-base-100/30 transition-colors">
                                <td class="p-4 font-medium text-base-content">{{ $tx->formatted_amount }}</td>
                                <td class="p-4">
                                    <span class="badge-portal badge-portal-{{ $tx->status_color === 'success' ? 'success' : 'warning' }}">
                                        {{ $tx->status_label }}
                                    </span>
                                </td>
                                <td class="p-4 text-base-content/70">
                                    @if($tx->owner_type === 'subscription')
                                        {{ $tx->owner_label }}
                                    @elseif($tx->owner_type === 'order' && $tx->owner_uuid)
                                        <a href="{{ route('verwaltung.orders.show', $tx->owner_uuid) }}"
                                           class="text-[color:var(--portal-primary)] hover:underline">
                                            {{ $tx->owner_label }}
                                        </a>
                                    @else
                                        {{ $tx->owner_label }}
                                    @endif
                                </td>
                                <td class="p-4 text-base-content/70">{{ $tx->created_at->format('d.m.Y H:i') }}</td>
                                <td class="p-4 text-right">
                                    @if($tx->can_download_invoice)
                                        <button wire:click="downloadInvoice({{ $tx->id }})"
                                                class="btn-portal btn-portal-ghost text-xs">
                                            <svg class="w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
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

            {{-- Pagination --}}
            @if($transactions->hasPages())
                <div class="p-4 border-t border-base-200">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
