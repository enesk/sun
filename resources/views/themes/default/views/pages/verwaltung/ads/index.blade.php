@extends('layouts.verwaltung')

@section('title', 'Werbung verwalten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Werbung</h1>
            <p class="dash-page-subtitle">Ad-Slots erstellen und verwalten</p>
        </div>
        <div class="dash-page-actions">
            <a href="{{ route('verwaltung.ads.create') }}" class="dash-btn dash-btn-primary dash-btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Neuer Ad-Slot
            </a>
        </div>
    </div>

    @if($slots->flatten()->isEmpty())
        <div class="dash-card dash-card-padded" style="text-align: center; padding: 3rem 1.5rem;">
            <svg class="w-12 h-12 mx-auto" style="color: var(--dash-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 010 11.76m-1.98-.39a23.848 23.848 0 00-8.835 2.535M15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
            </svg>
            <h3 class="mt-3 text-sm font-medium" style="color: var(--dash-text-primary);">Keine Ad-Slots vorhanden</h3>
            <p class="mt-1 text-sm" style="color: var(--dash-text-muted);">Erstellen Sie Ihren ersten Ad-Slot, um Werbung im Portal anzuzeigen.</p>
            <div class="mt-4">
                <a href="{{ route('verwaltung.ads.create') }}" class="dash-btn dash-btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Neuer Ad-Slot
                </a>
            </div>
        </div>
    @else
        @foreach($positions as $positionKey => $positionLabel)
            @php
                $positionSlots = $slots[$positionKey] ?? collect();
            @endphp

            @if($positionSlots->isNotEmpty())
                <div class="dash-card" style="margin-bottom: 1.5rem;">
                    <div class="dash-card-header">
                        <h2 class="dash-card-header-title">{{ $positionLabel }}</h2>
                        <span class="dash-badge dash-badge-neutral">{{ $positionSlots->count() }} {{ $positionSlots->count() === 1 ? 'Slot' : 'Slots' }}</span>
                    </div>

                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Geräte</th>
                                    <th>Sortierung</th>
                                    <th class="text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($positionSlots as $slot)
                                    <tr>
                                        <td class="font-medium">{{ $slot->name }}</td>
                                        <td>
                                            @if($slot->is_active)
                                                <span class="dash-badge dash-badge-success">Aktiv</span>
                                            @else
                                                <span class="dash-badge dash-badge-neutral">Inaktiv</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($slot->device_visibility ?? [] as $device)
                                                    <span class="dash-badge dash-badge-info">{{ ucfirst($device) }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>{{ $slot->sort_order }}</td>
                                        <td class="text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('verwaltung.ads.edit', $slot->id) }}" class="dash-btn dash-btn-sm">
                                                    Bearbeiten
                                                </a>
                                                <form method="POST" action="{{ route('verwaltung.ads.destroy', $slot->id) }}"
                                                      onsubmit="return confirm('Ad-Slot &quot;{{ e($slot->name) }}&quot; wirklich löschen?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dash-btn dash-btn-sm dash-btn-danger">
                                                        Löschen
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    @endif

    {{-- ads.txt --}}
    <div class="dash-card" style="margin-top: 2rem;">
        <div class="dash-card-header">
            <h2 class="dash-card-header-title">ads.txt</h2>
            @if(!empty($adSettings->ads_txt_content))
                <a href="{{ route('portal.ads-txt') }}" target="_blank" class="dash-badge dash-badge-success" style="text-decoration: none;">
                    Aktiv
                </a>
            @else
                <span class="dash-badge dash-badge-neutral">Nicht konfiguriert</span>
            @endif
        </div>
        <div class="dash-card-padded">
            <p class="text-sm" style="color: var(--dash-text-muted); margin-bottom: 1rem;">
                Die ads.txt-Datei wird unter <code>{{ request()->getSchemeAndHttpHost() }}/ads.txt</code> bereitgestellt.
                Kopieren Sie den Inhalt aus Ihrem Google AdSense-Konto (Einstellungen &rarr; ads.txt).
            </p>
            <form method="POST" action="{{ route('verwaltung.ads.update-ads-txt') }}">
                @csrf
                @method('PUT')
                <div class="dash-form-group" style="margin-bottom: 1rem;">
                    <textarea
                        name="ads_txt_content"
                        rows="5"
                        class="dash-input"
                        placeholder="google.com, pub-XXXXXXXXXXXXXXXX, DIRECT, f08c47fec0942fa0"
                        style="font-family: monospace; font-size: 0.85rem;"
                    >{{ old('ads_txt_content', $adSettings->ads_txt_content) }}</textarea>
                    @error('ads_txt_content')
                        <p class="dash-form-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm">
                    ads.txt speichern
                </button>
            </form>
        </div>
    </div>
@endsection
