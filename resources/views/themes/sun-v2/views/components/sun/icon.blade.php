{{-- Icon aus public/themes/sun-v2/images/icons.svg. Strich-Attribute wie in der Vorlage, pro Aufruf ueberschreibbar. --}}
@props(['name'])
<svg {{ $attributes->merge([
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => '2',
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
    'aria-hidden' => 'true',
]) }}><use href="{{ \App\Themes\SunV2\Asset::icon($name) }}"/></svg>
