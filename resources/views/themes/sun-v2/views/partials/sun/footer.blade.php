@php
    $portalName = $currentTenant->name ?? config('app.name');
@endphp
<footer class="bg-white border-t border-zinc-200">
  <div class="container-portal py-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
      <div>
        <h3 class="font-semibold text-zinc-900 mb-3">Portal</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="{{ route('portal.companies.index') }}" class="hover:text-brand">Firmenverzeichnis</a></li>
          <li><a href="{{ route('portal.categories.index') }}" class="hover:text-brand">Leistungen</a></li>
          <li><a href="{{ route('portal.cities.index') }}" class="hover:text-brand">Städte</a></li>
          <li><a href="{{ route('portal.jobs.index') }}" class="hover:text-brand">Stellenanzeigen</a></li>
        </ul>
      </div>
      <div>
        <h3 class="font-semibold text-zinc-900 mb-3">Für Betriebe</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="{{ route('portal.companies.create') }}" class="hover:text-brand">Firma eintragen</a></li>
          <li><a href="{{ route('portal.owner.premium') }}" class="hover:text-brand">Premium</a></li>
          @guest
            <li><a href="{{ route('login') }}" class="hover:text-brand">Anmelden</a></li>
          @endguest
        </ul>
      </div>
      <div>
        <h3 class="font-semibold text-zinc-900 mb-3">Ratgeber</h3>
        <ul class="space-y-2 text-sm">
          @forelse($sunFooterPostCategories as $postCategory)
            <li><a href="{{ route('portal.blog.category', $postCategory->slug) }}" class="hover:text-brand">{{ $postCategory->name }}</a></li>
          @empty
            <li><a href="{{ route('portal.blog.index') }}" class="hover:text-brand">Alle Artikel</a></li>
          @endforelse
        </ul>
      </div>
      <div>
        <h3 class="font-semibold text-zinc-900 mb-3">Rechtliches</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="{{ route('portal.impressum') }}" class="hover:text-brand">Impressum</a></li>
          <li><a href="{{ route('portal.datenschutz') }}" class="hover:text-brand">Datenschutz</a></li>
          <li><button type="button" class="hover:text-brand" data-cookie-action="settings">Datenschutz-Einstellungen</button></li>
          <li><a href="{{ route('portal.blog.editorial') }}" class="hover:text-brand">Redaktionsprinzipien</a></li>
        </ul>
      </div>
    </div>
    <div class="mt-10 pt-6 border-t border-zinc-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-sm text-zinc-500">
      <div class="flex items-center gap-2">
        <span class="size-7 rounded-lg bg-brand text-white flex items-center justify-center"><x-sun.icon :name="config('themes.sun-v2.brand_icon')" class="size-4" /></span>
        <span>© {{ now()->year }} {{ $portalName }}</span>
      </div>
      <span>{{ config('themes.sun-v2.footer.operator') }}</span>
    </div>
  </div>
</footer>
