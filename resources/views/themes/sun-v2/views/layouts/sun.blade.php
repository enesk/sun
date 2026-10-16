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
    // Vorschau der Werbeplaetze (#6): gueltiger Editor-Token in der Adresse.
    // Schaltet Analytics, Cookie-Hinweis und Turnstile ab, setzt noindex und
    // bindet allein hier das Editor-Bundle ein.
    $adEditor = \App\Ads\Support\AdEditorMode::active();
    // Robots/Canonical: SeoService (#7) hat Vorrang, sonst die @section der Seite.
    $seo = app(\App\Services\Seo\SeoService::class);
    $metaRobots = $seo->robots() !== null ? e($seo->robots()) : ($__env->hasSection('meta_robots') ? $__env->yieldContent('meta_robots') : null);
    // Eine signierte Adresse ist nicht geheim: die Vorschau darf unter keinen
    // Umstaenden in den Index, darum hat sie Vorrang vor allem anderen.
    if ($adEditor) {
        $metaRobots = 'noindex,nofollow';
    }
    $canonicalUrl = $seo->canonical() !== null ? e($seo->canonical()) : $__env->yieldContent('canonical', url()->current());
    // Title/Description/OG wie im Default-Layout: SeoService (Ratgeber, #17) vor @section
    $metaTitle = $seo->title() !== null ? e($seo->title()) : $__env->yieldContent('title', $portalName);
    $metaDescription = $seo->description() !== null ? e($seo->description()) : $__env->yieldContent('meta_description', '');
    $metaOgType = $seo->ogType() !== null ? e($seo->ogType()) : $__env->yieldContent('og_type', 'website');
    $metaOgImage = $seo->ogImage() !== null ? e($seo->ogImage()) : ($__env->hasSection('og_image') ? $__env->yieldContent('og_image') : null);
@endphp
<!doctype html>
<html lang="de" style="--brand:{{ $brandColor }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{!! $metaTitle !!}</title>
<meta name="description" content="{!! $metaDescription !!}">
@if($metaRobots !== null)
<meta name="robots" content="{!! $metaRobots !!}">
@endif
<link rel="canonical" href="{!! $canonicalUrl !!}">
<x-seo.json-ld />

<meta property="og:title" content="{!! $metaTitle !!}">
<meta property="og:description" content="{!! $metaDescription !!}">
<meta property="og:type" content="{!! $metaOgType !!}">
<meta property="og:url" content="{!! $canonicalUrl !!}">
<meta property="og:site_name" content="{{ $portalName }}">
<meta property="og:locale" content="de_DE">
@if($metaOgImage !== null)
<meta property="og:image" content="{!! $metaOgImage !!}">
@elseif(!empty($currentTenant) && $currentTenant->getAttribute('branding.og_image_path'))
<meta property="og:image" content="{{ asset($currentTenant->getAttribute('branding.og_image_path')) }}">
@endif
<meta name="twitter:card" content="summary_large_image">

@if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
<link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
@endif

{{-- CSS eingebettet statt verlinkt: keine render-blockierende Anfrage (~8 KB gzip) --}}
@if(app()->isProduction() && ! \Illuminate\Support\Facades\Vite::isRunningHot())
{{-- Schrift vorab laden: der Hero-Text ist das LCP-Element und wartet sonst auf die Schrift --}}
<link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/views/themes/sun-v2/fonts/figtree-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<style>{!! \Illuminate\Support\Facades\Vite::content('resources/views/themes/sun-v2/css/app.css') !!}</style>
@vite(['resources/views/themes/sun-v2/js/app.js'])
@else
@vite(['resources/views/themes/sun-v2/css/app.css', 'resources/views/themes/sun-v2/js/app.js'])
@endif
@stack('styles')

{{-- Skripte, die ein Werbemittel im <head> braucht (#5): die Komponente
     <x-sun.ad-slot> sammelt sie je Seite ein und wirft Doppelte weg — der
     AdSense-Loader steht also auch bei sechs Ankern genau einmal hier. Der
     Stack wird vom Seiten-View gefuellt, der vor dem Layout gerendert wird. --}}
@stack('head')

@if(! $adEditor)
@include('partials.analytics')
@endif

{{-- Bruecke zum Panel (#6). Eigenes Bundle, nur in der Vorschau geladen. --}}
@if($adEditor)
@vite(['resources/views/themes/sun-v2/js/ad-editor.js'])
@endif

{{-- Auto Ads: auf Ratgeber-Routen nicht ausliefern (Vorgabe #100) --}}
@if(\App\View\Components\AdSlot::autoAdsAllowedHere())
    <x-ad-slot position="auto_ads" />
@endif

{{-- Auto Ads aus der Portal-Einstellung `ad_settings.adsense_auto_ads` (#18).
     Haelt sich zurueck, solange das Altsystem die Position `auto_ads` in
     diesem Portal noch selbst ausliefert; mit dessen Abschaltung (#11) fallen
     die vier Zeilen darueber weg und diese Komponente uebernimmt. --}}
<x-sun.auto-ads />
</head>
<body class="bg-zinc-50 text-zinc-700 font-sans overflow-x-clip @if($hasStickyAd) pb-[60px] lg:pb-0 @endif @yield('body_class')"
@if($adEditor)
    data-ad-editor
    data-ad-editor-origin="{{ \App\Ads\EditorToken::origin() }}"
    data-ad-catalog-version="{{ \App\Ads\EditorToken::catalogVersion() }}"
    data-ad-page-type="{{ \App\Ads\Enums\AdPageType::fromRoute(request()->route()?->getName())?->value ?? \App\Ads\EditorToken::pageType(request()) }}"
@endif>

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 btn-secondary">{{ __('portal.layout.skip_link') }}</a>

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

{{-- Kein Cookie-Hinweis in der Vorschau (#6): es wird nichts Fremdes
     geladen, und der Kasten verdeckt die unteren Anker. --}}
@if(! $adEditor)
@include('partials.sun.cookie-consent')
@endif

@stack('scripts')
</body>
</html>
