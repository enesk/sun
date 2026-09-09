{{--
    Warnkasten „Anschrift fehlt" — ein Baustein, drei Einsatzorte (Vorgabe #61, §2).
    action: 'link'   = Textlink „Anschrift ergänzen" in Zeile 2
            'button' = Schaltfläche rechts (Übersicht-Banner)
            'none'   = ohne Handlung (der Nutzer steht bereits am Ziel)
--}}
@props(['action' => 'link'])

@php
    $anschriftHref = route('verwaltung.settings.general') . '?reiter=workspace#anschrift';
@endphp

<div class="dash-flash dash-flash-warning dash-flash-block" role="alert"
     style="border-radius: 0.5rem; margin-bottom: 1.5rem;">
    <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
    </svg>

    @if($action === 'button')
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full" style="gap: 0.75rem;">
            <div>
                <p style="font-weight: 600;">Ohne Anschrift bleibt die Adresszeile in Impressum und Datenschutz leer. Diese Angabe ist gesetzlich vorgeschrieben.</p>
                <p class="dash-flash-body" style="font-size: 0.75rem;">Betrifft die Seiten Impressum und Datenschutz Ihres Portals.</p>
            </div>
            <a href="{{ $anschriftHref }}" class="dash-btn dash-btn-sm" style="flex-shrink: 0;">Anschrift ergänzen</a>
        </div>
    @else
        <div>
            <p style="font-weight: 600;">Ohne Anschrift bleibt die Adresszeile in Impressum und Datenschutz leer. Diese Angabe ist gesetzlich vorgeschrieben.</p>
            @if($action === 'link')
                <a href="{{ $anschriftHref }}" class="dash-flash-link"
                   style="text-decoration: underline; font-weight: 500; color: currentColor; font-size: 0.875rem;">Anschrift ergänzen</a>
            @endif
        </div>
    @endif
</div>
