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
                       placeholder="Suche nach Bestell-ID oder Produkt..."
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
    @if($orders->isEmpty())
        <div class="card-portal p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-base-200/50 flex items-center justify-center">
                <svg class="w-8 h-8 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-base-content mb-1">Keine Bestellungen</h3>
            <p class="text-sm text-base-content/60">Es sind noch keine Bestellungen vorhanden.</p>
        </div>
    @else
        <div class="card-portal overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-base-200 bg-base-100/50">
                            <th class="text-left p-4 font-medium text-base-content/60">
                                Bestell-ID
                            </th>
                            <th class="text-left p-4 font-medium text-base-content/60 cursor-pointer hover:text-base-content"
                                wire:click="sort('total_amount')">
                                Betrag
                                @if($sortBy === 'total_amount')
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
                            <th class="text-left p-4 font-medium text-base-content/60 hidden md:table-cell">
                                Produkte
                            </th>
                            <th class="text-left p-4 font-medium text-base-content/60 cursor-pointer hover:text-base-content"
                                wire:click="sort('updated_at')">
                                Datum
                                @if($sortBy === 'updated_at')
                                    <span class="text-xs">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="text-right p-4 font-medium text-base-content/60">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr class="border-b border-base-200/50 hover:bg-base-100/30 transition-colors">
                                <td class="p-4 text-base-content font-mono text-xs">
                                    #{{ substr($order->uuid, 0, 8) }}
                                </td>
                                <td class="p-4 font-medium text-base-content">{{ $order->formatted_amount }}</td>
                                <td class="p-4">
                                    @php
                                        $colorMap = [
                                            'success' => 'success',
                                            'danger' => 'error',
                                            'warning' => 'warning',
                                        ];
                                        $badgeColor = $colorMap[$order->status_color] ?? 'warning';
                                    @endphp
                                    <span class="badge-portal badge-portal-{{ $badgeColor }}">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td class="p-4 text-base-content/70 hidden md:table-cell">
                                    @if($order->items->isNotEmpty())
                                        {{ $order->items->map(fn($i) => $i->oneTimeProduct->name ?? '-')->join(', ') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="p-4 text-base-content/70">{{ $order->updated_at->format('d.m.Y H:i') }}</td>
                                <td class="p-4 text-right">
                                    <a href="{{ route('verwaltung.orders.show', $order->uuid) }}"
                                       class="btn-portal btn-portal-ghost text-xs">
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
                <div class="p-4 border-t border-base-200">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
