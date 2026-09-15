{{-- Betriebssuche am Ende der Ratgeber-Listen im Theme sun-v2 (Suche, Schlagwort). Erwartet $sunBlog. --}}
<section class="card p-5 md:p-8 bg-brand-50 border-brand-100">
  <div class="lg:flex lg:items-center lg:gap-10">
    <div class="flex-1 max-w-prose">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $sunBlog['cta']['headline'] }}</h2>
      <p class="mt-2 text-zinc-700 leading-relaxed">{{ $sunBlog['cta']['text'] }}</p>
    </div>
    <form action="{{ route('portal.companies.index') }}" method="get" role="search" class="mt-5 lg:mt-0 lg:w-80 shrink-0 grid gap-3">
      <label class="sr-only" for="cta-ort">{{ __('portal.layout.search_form.where_placeholder') }}</label>
      <input id="cta-ort" name="ort" class="input" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" autocomplete="postal-code">
      <button type="submit" class="btn-primary">{{ __('portal.blog.cta.button') }}</button>
    </form>
  </div>
</section>
