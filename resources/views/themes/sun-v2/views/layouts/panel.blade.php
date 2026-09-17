{{--
    Betriebsbereich (/firmenprofil) im Theme sun-v2 (Vorlage betriebsbereich-elektrikerportal.html).

    Eigenes Layout neben layouts.sun: Panel-Header statt Portal-Header, Navigation
    mobil als Chip-Leiste, ab md als Sidebar, kein Footer, keine Anzeigen.
    Unterseiten, die sun-v2 noch nicht mitbringt, laufen weiter ueber layouts.dashboard
    aus dem Default-Theme. Texte: lang/de/portal.php (owner.*).
--}}
@php
    $portalName = ($currentTenant?->terms ?? \App\Support\Tenancy\TenantTerms::defaults())['portal'];
    // Markenfarbe wie in layouts.sun
    $brandColor = (string) ($currentTenant?->getAttribute(\App\Constants\TenantConfigConstants::PRIMARY_COLOR) ?? '');
    $projectDefault = \App\Constants\TenantConfigConstants::DEFAULTS[\App\Constants\TenantConfigConstants::PRIMARY_COLOR] ?? null;
    if (! preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $brandColor) || strcasecmp($brandColor, (string) $projectDefault) === 0) {
        $brandColor = config('themes.sun-v2.default_brand_color');
    }

    $user = auth()->user();
    $panelCompany = $company ?? $user->getOwnedCompany();
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');

    $navItems = [
        ['route' => 'portal.owner.dashboard', 'icon' => 'home', 'label' => __('portal.owner.nav.overview'), 'active' => request()->routeIs('portal.owner.dashboard')],
        ['route' => 'portal.owner.edit', 'icon' => 'pencil', 'label' => __('portal.owner.nav.edit'), 'active' => request()->routeIs('portal.owner.edit')],
        // Zahl an "Anfragen": neue Anfragen, ein Zaehlquery ueber den Index (company_id, status).
        // rescue(): ohne tenants:migrate fehlt die Tabelle, das Menue soll dann nicht die Seite kippen.
        ['route' => 'portal.owner.inquiries.index', 'icon' => 'inbox', 'label' => __('portal.owner.nav.inquiries'), 'active' => request()->routeIs('portal.owner.inquiries.*'), 'badge' => $panelCompany ? rescue(fn () => $panelCompany->inquiries()->withStatus(\App\Constants\CompanyInquiryStatus::NEW)->count(), 0, false) : 0, 'badge_class' => 'pill-brand'],
        // Zahl an "Bewertungen": veroeffentlichte Bewertungen ohne Antwort
        ['route' => 'portal.owner.reviews', 'icon' => 'star', 'label' => __('portal.owner.nav.reviews'), 'active' => request()->routeIs('portal.owner.reviews*'), 'badge' => $panelCompany?->reviews->filter(fn ($review) => $review->isApproved() && empty($review->owner_response))->count()],
        ['route' => 'portal.owner.stats', 'icon' => 'chart', 'label' => __('portal.owner.nav.stats'), 'active' => request()->routeIs('portal.owner.stats*')],
        ['route' => 'portal.owner.jobs.index', 'icon' => 'briefcase', 'label' => __('portal.owner.nav.jobs'), 'active' => request()->routeIs('portal.owner.jobs.*')],
        ['route' => 'portal.owner.settings', 'icon' => 'settings', 'label' => __('portal.owner.nav.settings'), 'active' => request()->routeIs('portal.owner.settings')],
    ];
@endphp
<!doctype html>
<html lang="de" style="--brand:{{ $brandColor }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') | {{ __('portal.owner.area') }} | {{ $portalName }}</title>
<meta name="robots" content="noindex, nofollow">
@if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
<link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
@endif
@vite(['resources/views/themes/sun-v2/css/app.css', 'resources/views/themes/sun-v2/js/app.js'])
@stack('styles')

@include('partials.analytics')
</head>
<body class="bg-zinc-50 text-zinc-700 font-sans overflow-x-hidden">

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 btn-secondary">{{ __('portal.layout.skip_link') }}</a>

<header class="sticky top-0 z-40 bg-white border-b border-zinc-200">
  <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 flex items-center justify-between h-16">
    <div class="flex items-center gap-3 min-w-0">
      <a href="{{ route('home') }}" class="flex items-center gap-2 min-w-0" aria-label="{{ __('portal.layout.header.home_label') }}">
        <span class="size-9 shrink-0 rounded-xl bg-brand text-white flex items-center justify-center">
          <x-sun.icon :name="config('themes.sun-v2.brand_icon')" class="size-5" />
        </span>
        <span class="hidden sm:inline text-lg font-bold tracking-tight text-zinc-900 truncate">{{ $portalName }}</span>
      </a>
      <span class="hidden sm:inline text-zinc-300" aria-hidden="true">/</span>
      <span class="text-sm font-medium text-zinc-500">{{ __('portal.owner.area') }}</span>
    </div>
    <nav class="flex items-center gap-1" aria-label="{{ __('portal.owner.account_label') }}">
      <a href="{{ route('home') }}" class="btn-ghost px-3 hidden md:inline-flex">
        <x-sun.icon name="arrow-left" class="icon" /> {{ __('portal.owner.back_to_portal') }}
      </a>
      <span class="hidden sm:inline-flex items-center gap-2 min-h-11 px-3 text-base font-semibold text-zinc-700">
        <span class="inline-flex size-7 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-sm font-semibold" aria-hidden="true">{{ $initials }}</span>
        {{ $user->name }}
      </span>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-ghost px-3 text-zinc-500 hover:bg-zinc-100" aria-label="{{ __('portal.layout.header.logout') }}">
          <x-sun.icon name="logout" class="icon" /><span class="hidden lg:inline">{{ __('portal.layout.header.logout') }}</span>
        </button>
      </form>
    </nav>
  </div>
</header>

<div class="mx-auto w-full max-w-7xl px-4 sm:px-6 md:grid md:grid-cols-[16rem_1fr] md:gap-8 lg:gap-10 py-4 md:py-8">

  <aside class="md:sticky md:top-24 md:self-start">
    <nav aria-label="{{ __('portal.owner.area') }}" class="-mx-4 px-4 flex gap-1 overflow-x-auto pb-2 md:mx-0 md:px-0 md:flex-col md:overflow-visible md:pb-0 md:gap-0.5">
      @foreach($navItems as $item)
        <a href="{{ route($item['route']) }}"
           class="flex items-center gap-3 min-h-11 px-4 rounded-xl text-base font-medium whitespace-nowrap transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 {{ $item['active'] ? 'bg-brand-50 text-brand-700' : 'text-zinc-700 hover:bg-zinc-100' }}"
           @if($item['active']) aria-current="page" @endif>
          <x-sun.icon :name="$item['icon']" class="icon" />{{ $item['label'] }}
          @if(! empty($item['badge']))<span class="ml-auto {{ $item['badge_class'] ?? 'pill' }} text-xs py-0.5">{{ $item['badge'] }}</span>@endif
        </a>
      @endforeach
    </nav>

    @if(request()->routeIs('portal.owner.premium', 'tenant.checkout.*'))
      <a href="{{ route('portal.owner.premium') }}" class="hidden md:flex mt-2 items-center gap-3 min-h-11 px-4 rounded-xl text-base font-medium bg-brand-50 text-brand-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2" aria-current="page">
        <x-sun.icon name="sparkles" class="icon" />{{ __('portal.owner.nav.premium') }}
      </a>
    @elseif($panelCompany && ! $panelCompany->is_premium)
      <div class="hidden md:block mt-6 rounded-2xl bg-brand-50 p-4">
        <p class="text-sm font-semibold text-brand-700 flex items-center gap-2"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.sidebar_premium.title') }}</p>
        <p class="mt-1 text-sm text-zinc-700">{{ __('portal.owner.sidebar_premium.text') }}</p>
        <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-3 w-full">{{ __('portal.owner.sidebar_premium.cta') }}</a>
      </div>
    @endif
  </aside>

  <main id="main" class="mt-4 md:mt-0 min-w-0">
    @if(session('success'))
      <p class="card mb-4 p-4 text-base text-emerald-700" role="status">{{ session('success') }}</p>
    @endif
    @if(session('error'))
      <p class="card mb-4 p-4 text-base text-red-600" role="alert">{{ session('error') }}</p>
    @endif

    @yield('content')
  </main>
</div>

@include('partials.sun.cookie-consent')

@stack('scripts')
</body>
</html>
