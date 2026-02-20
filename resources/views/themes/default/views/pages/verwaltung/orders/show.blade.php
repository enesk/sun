@extends('layouts.verwaltung')

@section('title', 'Bestellung — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-base-content">Bestellung #{{ substr($order->uuid, 0, 8) }}</h1>
            <p class="text-sm text-base-content/60 mt-1">Bestelldetails und Positionen</p>
        </div>
        <a href="{{ route('verwaltung.orders.index') }}"
           class="btn-portal btn-portal-ghost text-sm">
            ← Zurück
        </a>
    </div>

    {{-- Order Detail --}}
    <div class="space-y-6">
        {{-- Summary Card --}}
        <div class="card-portal p-6">
            <h2 class="text-lg font-semibold text-base-content mb-4">Zusammenfassung</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <span class="text-sm text-base-content/60">Gesamtbetrag</span>
                    <p class="text-lg font-semibold text-base-content">
                        @if($order->transactions->isNotEmpty())
                            {{ money($order->transactions->first()->amount, $order->transactions->first()->currency->code) }}
                        @else
                            {{ money($order->total_amount_after_discount, $order->currency->code) }}
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-sm text-base-content/60">Status</span>
                    <p class="mt-1">
                        @php
                            $mapper = app(\App\Mapper\OrderStatusMapper::class);
                            $color = $mapper->mapColor($order->status);
                        @endphp
                        <span class="badge-portal badge-portal-{{ $color === 'success' ? 'success' : 'warning' }}">
                            {{ $mapper->mapForDisplay($order->status) }}
                        </span>
                    </p>
                </div>
                <div>
                    <span class="text-sm text-base-content/60">Datum</span>
                    <p class="text-base-content">{{ $order->updated_at->format(config('app.datetime_format', 'd.m.Y H:i')) }}</p>
                </div>
            </div>

            @if($order->discounts->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-base-200">
                    <span class="text-sm text-base-content/60">Rabatt</span>
                    <p class="text-base-content">
                        @php $discount = $order->discounts->first(); @endphp
                        @if($discount->type === \App\Constants\DiscountConstants::TYPE_PERCENTAGE)
                            {{ $discount->amount }}%
                        @else
                            {{ money($discount->amount, $order->currency->code) }}
                        @endif
                    </p>
                </div>
            @endif
        </div>

        {{-- Order Items --}}
        @if($order->items->isNotEmpty())
            <div class="card-portal p-6">
                <h2 class="text-lg font-semibold text-base-content mb-4">Positionen</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-base-200 text-left">
                                <th class="pb-3 font-medium text-base-content/60">Produkt</th>
                                <th class="pb-3 font-medium text-base-content/60">Anzahl</th>
                                <th class="pb-3 font-medium text-base-content/60">Einzelpreis</th>
                                <th class="pb-3 font-medium text-base-content/60">Nach Rabatt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b border-base-200/50">
                                    <td class="py-3 text-base-content">{{ $item->oneTimeProduct->name ?? '-' }}</td>
                                    <td class="py-3 text-base-content">{{ $item->quantity }}</td>
                                    <td class="py-3 text-base-content">{{ money($item->price_per_unit, $order->currency->code) }}</td>
                                    <td class="py-3 text-base-content">{{ money($item->price_per_unit_after_discount, $order->currency->code) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
