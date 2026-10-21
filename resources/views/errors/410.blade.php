{{-- 410 Gone: Profil dauerhaft geloescht (Loeschpipeline, SUN-RQ #8).
     Ausgeliefert von App\Http\Middleware\GoneUrls, nicht von der
     Exception-Behandlung — der Eintrag ist weg, nicht kaputt. --}}
<x-layouts.error code="410" message="{{ __('Dieser Eintrag wurde auf Wunsch des Betriebs dauerhaft gelöscht.') }}">
</x-layouts.error>
