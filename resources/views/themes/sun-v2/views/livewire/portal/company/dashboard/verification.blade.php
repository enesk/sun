{{--
    Verifiziert-Badge im Betriebsbereich, Theme sun-v2 (#11).
    Logik: App\Livewire\Portal\Company\Dashboard\Verification. Texte: portal.owner.verification.*
--}}
<section id="verifizierung" class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-verifizierung">
  <h2 id="sec-verifizierung" class="text-2xl font-semibold text-zinc-900 flex flex-wrap items-center gap-2">
    {{ __('portal.owner.verification.title') }}
    @unless($allowed)
      <span class="pill-brand text-xs">{{ __('portal.owner.edit.locked.badge') }}</span>
    @endunless
  </h2>

  @if($company->verified_at)
    <p class="mt-3 flex items-start gap-2 rounded-xl bg-brand-50 p-4 text-zinc-700">
      <span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>
      <span>{{ $allowed ? __('portal.owner.verification.verified', ['datum' => $company->verified_at->format('d.m.Y')]) : __('portal.owner.verification.verified_hidden') }}</span>
    </p>
  @elseif(! $allowed)
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.verification.locked') }}</p>
    <a href="{{ route('portal.owner.premium') }}" class="mt-4 btn-secondary"><x-sun.icon name="sparkles" class="icon" />{{ __('portal.owner.verification.locked_cta') }}</a>
  @elseif($pending)
    <p class="mt-3 rounded-xl bg-zinc-100 p-4 text-zinc-700">{{ __('portal.owner.verification.pending', ['datum' => $pending->created_at->format('d.m.Y')]) }}</p>
  @else
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.verification.intro') }}</p>
    @if($rejected)
      <p class="mt-3 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700">{{ __('portal.owner.verification.rejected', ['grund' => $rejected->rejection_reason]) }}</p>
    @endif

    <form wire:submit="submit" class="mt-4 flex flex-col gap-4">
      <div>
        <label for="verification-type" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.verification.type_label') }}</label>
        <select id="verification-type" wire:model="documentType" class="input">
          <option value="">–</option>
          @foreach($documentTypes as $type)
            <option value="{{ $type->value }}">{{ $type->label() }}</option>
          @endforeach
        </select>
        @error('documentType')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <label for="verification-document" class="flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50 p-6 text-center cursor-pointer hover:border-brand focus-within:border-brand">
          <span class="font-medium text-zinc-900">{{ $document ? $document->getClientOriginalName() : __('portal.owner.verification.file_label') }}</span>
          <span class="text-sm text-zinc-500">{{ __('portal.owner.verification.file_hint') }}</span>
          <input id="verification-document" type="file" wire:model="document" accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
        </label>
        <p wire:loading wire:target="document" class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.verification.uploading') }}</p>
        @error('document')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <button type="submit" wire:loading.attr="disabled" wire:target="submit,document" class="btn-primary">
          <span wire:loading.remove wire:target="submit">{{ __('portal.owner.verification.submit') }}</span>
          <span wire:loading wire:target="submit">{{ __('portal.owner.verification.sending') }}</span>
        </button>
      </div>
    </form>
  @endif
</section>
