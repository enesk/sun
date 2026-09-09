{{--
    Zustandsstreifen zum Aktualisierungsstand (#99).

    Genau ein Zustand, als Satz und nicht als Farbfleck: der Ton stuetzt die
    Aussage, traegt sie aber nicht. Trifft kein Zustand zu, kommt hier gar
    nichts an ($refresh ist null) und der Streifen entfaellt ersatzlos — kein
    Kasten mit "keine Aktualisierung".

    $refresh    Zustand aus App\Content\Services\RefreshStatus
    $childClick Optional: Livewire-Ausdruck, der die Kindfassung oeffnet.
                Ohne ihn steht der Satz ohne Link — er ist auch so vollstaendig.
--}}
@php
    $refresh = $refresh ?? null;
    $childClick = $childClick ?? null;
@endphp

@if ($refresh)
    <p class="content-refresh-strip content-refresh-strip--{{ $refresh['tone'] }}">
        <span>{{ $refresh['text'] }}</span>

        @if ($childClick && ($refresh['child_id'] ?? null))
            <span aria-hidden="true">→</span>
            <button
                type="button"
                wire:click="{{ $childClick }}"
                class="font-medium text-content-700 underline underline-offset-4"
            >
                {{ $refresh['child_label'] }}
            </button>
        @endif
    </p>
@endif
