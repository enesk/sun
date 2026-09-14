{{--
    Bewertungsformular im Theme sun-v2. Logik unveraendert in
    App\Livewire\Portal\SubmitReviewForm, nur die Darstellung mit den
    Theme-Komponenten. Das Wurzelelement ist display:contents, damit Knopf und
    aufgeklapptes Formular im Kopf der Bewertungskarte als eigene Zeilen umbrechen.
--}}
<div class="contents">
  @if(! $submitted)
    <button type="button" wire:click="toggleForm" class="btn-secondary self-start sm:self-auto" aria-expanded="{{ $showForm ? 'true' : 'false' }}" aria-controls="review-form-{{ $company->id }}">
      Bewertung schreiben
    </button>
  @endif

  @if($showForm && ! $submitted)
    <div id="review-form-{{ $company->id }}" class="basis-full w-full mt-2 rounded-2xl bg-zinc-50 p-5 flex flex-col gap-4"
         x-data="{ hover: 0, selected: @entangle('rating') }">
      <h3 class="text-lg font-semibold text-zinc-900">Bewertung für {{ $company->name }} schreiben</h3>

      <div>
        <p class="text-sm font-medium text-zinc-700 mb-2">Wie bewertest du den Betrieb? *</p>
        <div class="flex items-center gap-1" role="radiogroup" aria-label="Bewertung in Sternen" @mouseleave="hover = 0">
          @for($i = 1; $i <= 5; $i++)
            <div class="relative size-11">
              <x-sun.icon name="star" class="size-11 pointer-events-none" stroke="none" x-bind:class="(hover || selected) >= {{ $i }} ? 'fill-amber-500' : 'fill-zinc-300'" />
              <x-sun.icon name="star" class="absolute inset-0 size-11 pointer-events-none" stroke="none" style="clip-path: inset(0 50% 0 0)" x-bind:class="(hover || selected) >= {{ $i - 0.5 }} ? 'fill-amber-500' : 'fill-zinc-300'" />
              <button type="button" wire:click="setRating({{ $i - 0.5 }})" @mouseenter="hover = {{ $i - 0.5 }}" class="absolute inset-y-0 left-0 w-1/2 rounded-l-full focus-visible:outline-hidden focus-visible:ring-2" role="radio" x-bind:aria-checked="selected === {{ $i - 0.5 }}" aria-label="{{ str_replace('.', ',', (string) ($i - 0.5)) }} Sterne"></button>
              <button type="button" wire:click="setRating({{ $i }})" @mouseenter="hover = {{ $i }}" class="absolute inset-y-0 right-0 w-1/2 rounded-r-full focus-visible:outline-hidden focus-visible:ring-2" role="radio" x-bind:aria-checked="selected === {{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'Stern' : 'Sterne' }}"></button>
            </div>
          @endfor
          <span class="ml-2 text-sm font-medium text-zinc-700" x-show="(hover || selected) > 0" x-text="(hover || selected).toFixed(1).replace('.', ',') + ' / 5'"></span>
        </div>
        @error('rating')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="review-author" class="block text-sm font-medium text-zinc-700 mb-1">Dein Name <span class="text-zinc-500 font-normal">(optional)</span></label>
          <input id="review-author" type="text" wire:model.blur="authorName" class="input" maxlength="100" autocomplete="name" placeholder="z. B. Maria S.">
          <p class="mt-1 text-sm text-zinc-500">Wird öffentlich angezeigt. Leer = „Anonym“</p>
          @error('authorName')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
          <label for="review-title" class="block text-sm font-medium text-zinc-700 mb-1">Titel <span class="text-zinc-500 font-normal">(optional)</span></label>
          <input id="review-title" type="text" wire:model.blur="title" class="input" maxlength="150" placeholder="z. B. Sehr zufrieden">
          @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
      </div>

      <div>
        <label for="review-body" class="block text-sm font-medium text-zinc-700 mb-1">Deine Erfahrung <span class="text-zinc-500 font-normal">(optional)</span></label>
        <textarea id="review-body" wire:model.blur="body" rows="4" maxlength="2000" class="input py-3 h-auto" placeholder="Wie lief der Auftrag?"></textarea>
        @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <p class="text-sm text-zinc-500">Deine Bewertung wird geprüft und erscheint erst nach der Freigabe.</p>

      <div class="flex flex-col-reverse sm:flex-row gap-2">
        <button type="button" wire:click="toggleForm" class="btn-ghost">Abbrechen</button>
        <button type="button" wire:click="submit" wire:loading.attr="disabled" class="btn-primary" x-bind:disabled="selected === 0">
          <span wire:loading.remove wire:target="submit">Bewertung absenden</span>
          <span wire:loading wire:target="submit">Wird gesendet …</span>
        </button>
      </div>
    </div>
  @endif

  @if($submitted)
    <div class="basis-full w-full mt-2 rounded-2xl bg-brand-50 p-5 flex items-start gap-3" role="status">
      <span class="size-10 rounded-full bg-white text-brand flex items-center justify-center shrink-0"><x-sun.icon name="check" class="icon" /></span>
      <div>
        <p class="font-semibold text-zinc-900">Danke für deine Bewertung!</p>
        <p class="mt-1 text-sm text-zinc-700">Wir prüfen sie und schalten sie danach frei — erst dann erscheint sie auf dieser Seite, meist innerhalb von 24 Stunden.</p>
      </div>
    </div>
  @endif
</div>
