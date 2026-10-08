{{--
    429 bei einem ueberschrittenen Rate-Limit der Tiefenverteidigung (#8).

    Eigene Seite statt errors/429, weil der Besucher hier erfahren soll, wann
    er es wieder versuchen kann — der Retry-After-Header steht ohnehin in der
    Antwort, aber den liest kein Mensch. Text aus lang/de/antispam.php.

    Gerendert von App\AntiSpam\Support\RateLimitGuard::throttleResponse();
    dieselbe Meldung geht bei einer JSON-Anfrage als `message` heraus.

    x-layouts.error nimmt keinen Slot entgegen (siehe die Komponente), deshalb
    steht der Hinweis mit in der Meldung.
--}}
<x-layouts.error code="429" message="{{ $meldung }}" />
