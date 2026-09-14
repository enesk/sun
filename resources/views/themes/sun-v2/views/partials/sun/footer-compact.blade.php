@php
    $portalName = $currentTenant->name ?? config('app.name');
@endphp
<footer class="bg-white border-t border-zinc-200">
  <div class="container-portal py-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-sm text-zinc-500">
    <span>© {{ now()->year }} {{ $portalName }}</span>
    <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Rechtliches">
      <a href="{{ route('portal.impressum') }}" class="hover:text-brand">Impressum</a><a href="{{ route('portal.datenschutz') }}" class="hover:text-brand">Datenschutz</a><a href="mailto:{{ config('themes.sun-v2.login.support_email') }}" class="hover:text-brand">Support</a>
    </nav>
  </div>
</footer>
