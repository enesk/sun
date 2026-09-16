{{--
    Abschlusskarte der Checkout-Seiten in sun-v2 (empty-state-Layout).
    Parameter: $icon, $heading, $text, $ctaUrl, $ctaLabel, $steps (bool, naechste Schritte im Profil zeigen).
--}}
<div @class(['container-portal py-8 md:py-16' => ! auth()->check()])>
  <div class="card p-6 md:p-10 w-full max-w-xl mx-auto md:mt-8 text-center flex flex-col items-center" role="status">
    <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon :name="$icon" class="size-6" /></span>
    <h1 class="mt-4 text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ $heading }}</h1>
    <p class="mt-2 text-base text-zinc-700 max-w-prose">{{ $text }}</p>

    @if($steps ?? false)
      <div class="mt-6 w-full text-left">
        <h2 class="text-sm text-zinc-500">{{ __('portal.owner.checkout.success.steps_title') }}</h2>
        <ul class="mt-2 divide-y divide-zinc-200 border-y border-zinc-200">
          @foreach(['description' => ['text', '#description'], 'cover' => ['image', '#sec-titelbild'], 'photos' => ['upload', '#sec-fotos']] as $key => [$stepIcon, $anchor])
            <li>
              <a href="{{ route('portal.owner.edit').$anchor }}" class="flex items-center gap-3 min-h-11 py-2 text-base font-medium text-zinc-900 hover:text-brand-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 rounded-lg">
                <x-sun.icon :name="$stepIcon" class="icon text-zinc-400" />
                <span class="flex-1">{{ __('portal.owner.checkout.success.steps.'.$key) }}</span>
                <x-sun.icon name="chevron-right" class="icon text-zinc-400" />
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    <a href="{{ $ctaUrl }}" class="btn-primary mt-6 w-full sm:w-auto">{{ $ctaLabel }}</a>
  </div>
</div>
