{{--
    Rahmen der Checkout-Seiten in sun-v2: Rueckweg, Titel, Einleitung und das Livewire-Formular.
    Parameter: $heading, $intro, $form (Livewire-Komponentenname).
--}}
<div @class(['container-portal py-8 md:py-12' => ! auth()->check()])>
  @auth
    <a href="{{ route('portal.owner.premium') }}" class="btn-ghost -ml-3 px-3 text-zinc-600 hover:bg-zinc-100">
      <x-sun.icon name="arrow-left" class="icon" />{{ __('portal.owner.checkout.back') }}
    </a>
  @endauth
  <h1 class="mt-2 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $heading }}</h1>
  <p class="mt-1 text-base text-zinc-500 max-w-prose">{{ $intro }}</p>

  <div class="mt-6">
    @livewire($form)
  </div>
</div>
