{{--
    Leistungskatalog im Betriebsbereich, Theme sun-v2 (#13, ab Pro).
    Logik: App\Livewire\Portal\Company\Dashboard\Services. Texte: portal.owner.services.*
--}}
<section id="leistungen" class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-leistungen">
  <h2 id="sec-leistungen" class="text-2xl font-semibold text-zinc-900 flex flex-wrap items-center gap-2">
    {{ __('portal.owner.services.title') }}
    @unless($allowed)
      <span class="pill-brand text-xs"><x-sun.icon name="lock" class="size-3.5 shrink-0" />{{ __('portal.owner.services.locked_badge') }}</span>
    @endunless
  </h2>

  @unless($allowed)
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.services.locked') }}</p>
    @if($services->isNotEmpty())
      <p class="mt-1 text-sm text-zinc-700">{{ trans_choice('portal.owner.services.stored', $services->count(), ['anzahl' => $services->count()]) }}</p>
    @endif
    <a href="{{ route('portal.owner.premium') }}" class="mt-4 btn-secondary"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.unlock') }}</a>
  @else
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.services.intro') }}</p>
  @endunless

  @if($services->isNotEmpty())
    <ul class="mt-4 divide-y divide-zinc-200 rounded-xl border border-zinc-200">
      @foreach($services as $service)
        <li class="flex flex-wrap items-start justify-between gap-2 p-4" wire:key="service-{{ $service->id }}">
          <div class="min-w-0">
            <p class="font-semibold text-zinc-900 break-words">{{ $service->name }}</p>
            <p class="text-sm text-zinc-500">{{ $service->price_from_display ? __('portal.owner.services.price_from', ['preis' => $service->price_from_display]) : __('portal.owner.services.price_on_request') }}</p>
          </div>
          <div class="flex flex-wrap gap-1">
            @if($allowed)
              <button type="button" wire:click="edit({{ $service->id }})" class="btn-secondary px-4"><x-sun.icon name="pencil" class="icon" />{{ __('portal.owner.services.edit') }}</button>
            @endif
            <button type="button" wire:click="delete({{ $service->id }})" wire:confirm="{{ __('portal.owner.services.confirm_delete') }}" class="btn-ghost text-zinc-600 hover:bg-zinc-100">{{ __('portal.owner.edit.remove') }}</button>
          </div>
        </li>
      @endforeach
    </ul>
  @endif

  @if($allowed)
    @if($showForm)
      <form wire:submit="save" class="mt-4 flex flex-col gap-4 rounded-xl bg-zinc-50 p-4">
        <div class="grid sm:grid-cols-[1fr_10rem] gap-3">
          <div>
            <label for="service-name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.services.fields.name') }}</label>
            <input id="service-name" type="text" wire:model="name" class="input" maxlength="255">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="service-price" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.services.fields.price_from') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
            <input id="service-price" type="text" inputmode="decimal" wire:model="priceFrom" class="input" placeholder="89,00">
            @error('priceFrom')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
        </div>
        <div>
          <label for="service-description" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.services.fields.description') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
          <textarea id="service-description" wire:model="description" rows="3" class="input" maxlength="2000"></textarea>
          @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('portal.owner.services.save') }}</button>
          <button type="button" wire:click="cancel" class="btn-ghost">{{ __('portal.owner.services.cancel') }}</button>
        </div>
      </form>
    @elseif($canAdd)
      <button type="button" wire:click="create" class="mt-4 btn-secondary"><x-sun.icon name="plus" class="icon" />{{ __('portal.owner.services.add') }}</button>
    @endif
  @endif
</section>
