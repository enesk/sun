{{-- Reduzierter Footer fuer Ablaufseiten (z. B. /eintragen), per @section('footer_reduced', '1') --}}
@php
    $supportEmail = (string) (tenant()?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('mail.from.address'));
@endphp
<footer class="bg-white border-t border-zinc-200">
  <div class="container-portal py-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-sm text-zinc-500">
    <span>{{ __('portal.layout.footer.copyright', ['jahr' => now()->year]) }}</span>
    <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="{{ __('portal.layout.footer.legal') }}">
      <a href="{{ route('portal.impressum') }}" class="hover:text-brand">{{ __('portal.layout.footer.imprint') }}</a>
      <a href="{{ route('portal.datenschutz') }}" class="hover:text-brand">{{ __('portal.layout.footer.privacy') }}</a>
      <a href="{{ route('terms-of-service') }}" class="hover:text-brand">{{ __('portal.layout.footer.terms') }}</a>
      @if($supportEmail !== '')
        <a href="mailto:{{ $supportEmail }}" class="hover:text-brand">{{ __('portal.layout.footer.support') }}</a>
      @endif
    </nav>
  </div>
</footer>
