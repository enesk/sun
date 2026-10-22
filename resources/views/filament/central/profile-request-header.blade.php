{{-- Kopf der Anfrage-Detailseite: Überschrift oben, Knöpfe in einer eigenen
     Zeile darunter (SUN-RQ #7).

     Der Standardkopf von Filament (`fi-header`) stellt Überschrift und
     Aktionen ab Breite `sm` nebeneinander. Diese Seite hat bis zu sieben
     Aktionen — „Übernahme freigeben" bis „Nächste" — und die drängen die
     Überschrift in eine handtuchschmale Spalte: „REQ-127" bricht auf zwei
     Zeilen um, die Unterzeile auf vier.

     Deshalb hier gestapelt und bewusst **ohne** die Klasse `fi-header`:
     deren `sm:flex-row` säße in einer Media Query und schlüge jede einfache
     Utility-Klasse wieder. Die Knöpfe selbst brauchen nichts weiter — `.fi-ac`
     bringt `flex-wrap` schon mit und bricht über die volle Breite um. --}}
<header class="flex flex-col items-start gap-4">
    <div>
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        @if (filled($heading))
            <h1 class="fi-header-heading">{{ $heading }}</h1>
        @endif

        @if (filled($subheading))
            <p class="fi-header-subheading">{{ $subheading }}</p>
        @endif
    </div>

    @if ($actions)
        <div class="w-full">
            <x-filament::actions :actions="$actions" />
        </div>
    @endif
</header>
