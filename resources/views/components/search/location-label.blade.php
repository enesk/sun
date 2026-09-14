{{-- Ortsangabe der Suchmaske (#4): echter Ortsname oder "deiner Nähe". --}}
{{-- Importreste wie "None" gelten als nicht ermittelter Ort. --}}
{{-- Usage: Unternehmen in <x-search.location-label :city="$cityName" /> --}}
@props(['city' => null])
@if(\App\Models\Portal\City::isPlaceholderName(is_string($city) ? $city : null))
deiner Nähe
@else
{{ trim($city) }}
@endif
