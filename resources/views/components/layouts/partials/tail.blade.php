@stack('tail')

{{-- Komponenten wie <x-turnstile /> (#6) legen ihr Script hier ab --}}
@stack('scripts')

@vite(['resources/js/app.js'])

@include('components.layouts.partials.analytics')

@php($skipCookieContentBar = $skipCookieContentBar ?? false)

{{-- Derselbe Kategorie-Hinweis wie in den Portalen (#34). Vorher stand hier
     der Spatie-Hinweis (cookie-consent::index) mit einem Knopf und dem Cookie
     `cookie_consent`; er setzte keines der drei Kategorie-Cookies, also blieb
     Statistik/Marketing auf der zentralen Domain dauerhaft `pending`. Der
     Hinweis haengt nicht mehr an einem Schalter: ohne ihn gibt es hier keinen
     Weg, eine Einwilligung zu erteilen oder zu widerrufen. --}}
@if (!$skipCookieContentBar)
    <x-cookie-consent-dialog :privacy-url="route('privacy-policy')" />
@endif

@livewireScripts
