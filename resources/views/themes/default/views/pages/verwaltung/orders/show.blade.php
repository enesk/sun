@extends('layouts.verwaltung')

@section('title', 'Bestellung — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Bestellung #{{ substr($order->uuid, 0, 8) }}</h1>
            <p class="dash-page-subtitle">Bestelldetails und Positionen</p>
        </div>
        <a href="{{ route('verwaltung.orders.index') }}" class="dash-btn dash-btn-ghost dash-btn-sm">
            ← Zurück
        </a>
    </div>

    {{-- Order Detail --}}
    <div class="space-y-6">
        {{-- Summary Card --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-card-header-title" style="margin-bottom:1rem;">Zusammenfassung</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <span class="dash-stat-label">Gesamtbetrag</span>
                    <p class="dash-stat-value" style="font-size:1.125rem;">
                        @if($order->transactions->isNotEmpty())
                            {{ money($order->transactions->first()->amount, $order->transactions->first()->currency->code) }}
                        @else
                            {{ money($order->total_amount_after_discount, $order->currency->code) }}
                        @endif
                    </p>
                </div>
                <div>
                    <span class="dash-stat-label">Status</span>
                    <p class="mt-1">
                        @php
                            $mapper = app(\App\Mapper\OrderStatusMapper::class);
                            $color = $mapper->mapColor($order->status);
                        @endphp
                        <span class="dash-badge {{ $color === 'success' ? 'dash-badge-success' : 'dash-badge-warning' }}">
                            {{ $mapper->mapForDisplay($order->status) }}
                        </span>
                    </p>
                </div>
                <div>
                    <span class="dash-stat-label">Datum</span>
                    <p style="color:var(--dash-text-primary);">{{ $order->updated_at->format(config('app.datetime_format', 'd.m.Y H:i')) }}</p>
                </div>
            </div>

            @if($order->discounts->isNotEmpty())
                <div class="mt-4 pt-4" style="border-top:1px solid var(--dash-border);">
                    <span class="dash-stat-label">Rabatt</span>
                    <p style="color:var(--dash-text-primary);">
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
            <div class="dash-card dash-card-padded">
                <h2 class="dash-card-header-title" style="margin-bottom:1rem;">Positionen</h2>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Produkt</th>
                                <th>Anzahl</th>
                                <th>Einzelpreis</th>
                                <th>Nach Rabatt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>{{ $item->oneTimeProduct->name ?? '-' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ money($item->price_per_unit, $order->currency->code) }}</td>
                                    <td>{{ money($item->price_per_unit_after_discount, $order->currency->code) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
