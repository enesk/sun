@php
    $portalName = ($currentTenant?->terms ?? \App\Support\Tenancy\TenantTerms::defaults())['portal'];
    $ownedCompany = auth()->check() ? auth()->user()->getOwnedCompany() : null;

    $navLinks = [
        ['label' => __('portal.layout.header.cities'), 'href' => route('portal.cities.index')],
        ['label' => __('portal.layout.header.guide'), 'href' => route('portal.blog.index')],
        ['label' => __('portal.layout.header.jobs'), 'href' => route('portal.jobs.index')],
    ];

    $cta = $ownedCompany
        ? ['label' => __('portal.layout.header.cta_owner'), 'href' => route('portal.owner.dashboard')]
        : ['label' => __('portal.layout.header.cta_signup'), 'href' => route('portal.companies.create')];

    // Auf der Eintragen-Seite selbst entfaellt der Button (Vorlage elektrikerportal-firma-eintragen.html)
    if (request()->routeIs('portal.companies.create')) {
        $cta = null;
    }
@endphp
<header class="sticky top-0 z-40 bg-white border-b border-zinc-200">
  <div class="container-portal flex items-center justify-between h-16">
    <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="{{ __('portal.layout.header.home_label') }}">
      <span class="size-9 rounded-xl bg-brand text-white flex items-center justify-center">
        <x-sun.icon :name="config('themes.sun-v2.brand_icon')" class="size-5" />
      </span>
      <span class="text-lg font-bold tracking-tight text-zinc-900">{{ $portalName }}</span>
    </a>
    <nav class="hidden xl:flex items-center gap-1" aria-label="{{ __('portal.layout.header.nav_label') }}">
      @foreach($navLinks as $link)
        <a href="{{ $link['href'] }}" class="btn-ghost px-3">{{ $link['label'] }}</a>
      @endforeach
      @auth
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="btn-ghost px-3">{{ __('portal.layout.header.logout') }}</button>
        </form>
      @else
        <a href="{{ route('login') }}" class="btn-ghost px-3">{{ __('portal.layout.header.login') }}</a>
      @endauth
      @if($cta)<a href="{{ $cta['href'] }}" class="btn-primary ml-1">{{ $cta['label'] }}</a>@endif
    </nav>
    <button type="button" data-menu-toggle class="xl:hidden btn-ghost px-3" aria-label="{{ __('portal.layout.header.menu_open') }}" aria-expanded="false" aria-controls="mobileMenu">
      <x-sun.icon name="menu" class="icon" stroke-linejoin="miter" />
    </button>
  </div>
  <div id="mobileMenu" class="hidden xl:hidden border-t border-zinc-200 bg-white">
    <nav class="container-portal py-3 flex flex-col gap-1" aria-label="{{ __('portal.layout.header.nav_mobile_label') }}">
      @foreach($navLinks as $link)
        <a href="{{ $link['href'] }}" class="btn-ghost justify-start">{{ $link['label'] }}</a>
      @endforeach
      @auth
        <form method="POST" action="{{ route('logout') }}" class="flex flex-col">
          @csrf
          <button type="submit" class="btn-ghost justify-start">{{ __('portal.layout.header.logout') }}</button>
        </form>
      @else
        <a href="{{ route('login') }}" class="btn-ghost justify-start">{{ __('portal.layout.header.login') }}</a>
      @endauth
      @if($cta)<a href="{{ $cta['href'] }}" class="btn-primary mt-2">{{ $cta['label'] }}</a>@endif
    </nav>
  </div>
</header>
