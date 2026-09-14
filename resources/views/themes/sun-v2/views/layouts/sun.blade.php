{{--
    Layout des Themes sun-v2.

    Heisst bewusst nicht layouts/app: Seiten, die sun-v2 noch nicht selbst
    mitbringt, kommen aus dem Default-Theme und erweitern layouts.app — die
    brauchen weiter DaisyUI und resources/css/app.css. Eine umgestellte Seite
    wechselt mit @extends('layouts.sun') in dieses Layout.
--}}
@php
    $portalName = $currentTenant->name ?? config('app.name');
    // Markenfarbe: eigene Primaerfarbe des Portals, sonst die der Vorlage.
    // Die projektweite Voreinstellung #3B82F6 zaehlt als "nicht gepflegt" —
    // sie erreicht unter weisser Schrift nur 3,7:1.
    $brandColor = (string) ($currentTenant?->getAttribute(\App\Constants\TenantConfigConstants::PRIMARY_COLOR) ?? '');
    $projectDefault = \App\Constants\TenantConfigConstants::DEFAULTS[\App\Constants\TenantConfigConstants::PRIMARY_COLOR] ?? null;
    if (! preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $brandColor) || strcasecmp($brandColor, (string) $projectDefault) === 0) {
        $brandColor = config('themes.sun-v2.default_brand_color');
    }
    $hasStickyAd = \App\View\Components\AdSlot::hasSlotsForPosition('mobile_sticky_bottom');
    // Robots/Canonical: SeoService (#7) hat Vorrang, sonst die @section der Seite.
    $seo = app(\App\Services\Seo\SeoService::class);
    $metaRobots = $seo->robots() !== null ? e($seo->robots()) : ($__env->hasSection('meta_robots') ? $__env->yieldContent('meta_robots') : null);
    $canonicalUrl = $seo->canonical() !== null ? e($seo->canonical()) : $__env->yieldContent('canonical', url()->current());
@endphp
<!doctype html>
<html lang="de" style="--brand:{{ $brandColor }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', $portalName)</title>
<meta name="description" content="@yield('meta_description', '')">
@if($metaRobots !== null)
<meta name="robots" content="{!! $metaRobots !!}">
@endif
<link rel="canonical" href="{!! $canonicalUrl !!}">
<x-seo.json-ld />

<meta property="og:title" content="@yield('title', $portalName)">
<meta property="og:description" content="@yield('meta_description', '')">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:url" content="{!! $canonicalUrl !!}">
<meta property="og:site_name" content="{{ $portalName }}">
<meta property="og:locale" content="de_DE">
@if(!empty($currentTenant) && $currentTenant->getAttribute('branding.og_image_path'))
<meta property="og:image" content="{{ asset($currentTenant->getAttribute('branding.og_image_path')) }}">
@endif
<meta name="twitter:card" content="summary_large_image">

@if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
<link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
@endif

{{-- CSS eingebettet statt verlinkt: keine render-blockierende Anfrage (~8 KB gzip) --}}
@if(app()->isProduction() && ! \Illuminate\Support\Facades\Vite::isRunningHot())
<style>{!! \Illuminate\Support\Facades\Vite::content('resources/views/themes/sun-v2/css/app.css') !!}</style>
@vite(['resources/views/themes/sun-v2/js/app.js'])
@else
@vite(['resources/views/themes/sun-v2/css/app.css', 'resources/views/themes/sun-v2/js/app.js'])
@endif
@stack('styles')

@include('partials.analytics')

{{-- Auto Ads: auf Ratgeber-Routen nicht ausliefern (Vorgabe #100) --}}
@if(\App\View\Components\AdSlot::autoAdsAllowedHere())
    <x-ad-slot position="auto_ads" />
@endif
</head>
<body class="bg-zinc-50 text-zinc-700 font-sans overflow-x-hidden @if($hasStickyAd) pb-[60px] lg:pb-0 @endif @yield('body_class')">

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 btn-secondary">Zum Inhalt springen</a>

@include('partials.sun.header')

<main id="main">
    @yield('content')
</main>

@hasSection('footer_reduced')
    @include('partials.sun.footer-reduced')
@else
    {{-- Schlanker Footer fuer Konto-Seiten (Vorlage elektrikerportal-login.html) --}}
@hasSection('footer_compact')
@include('partials.sun.footer-compact')
@else
@include('partials.sun.footer')
@endif
@endif

@if($hasStickyAd)
    <div class="fixed bottom-0 inset-x-0 z-40 lg:hidden bg-white/90 backdrop-blur-sm">
        <x-ad-slot position="mobile_sticky_bottom" />
    </div>
@endif

@include('partials.sun.cookie-consent')

@stack('scripts')
</body>
</html>
