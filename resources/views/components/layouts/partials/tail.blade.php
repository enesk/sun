@stack('tail')

{{-- Komponenten wie <x-turnstile /> (#6) legen ihr Script hier ab --}}
@stack('scripts')

@vite(['resources/js/app.js'])

@include('components.layouts.partials.analytics')

@php($skipCookieContentBar = $skipCookieContentBar ?? false)

@if (!$skipCookieContentBar)
    @include('cookie-consent::index')
@endif

@livewireScripts
