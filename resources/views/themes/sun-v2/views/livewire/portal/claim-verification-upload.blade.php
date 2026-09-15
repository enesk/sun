{{--
    Nachweis-Upload im Theme sun-v2, eingebettet in Schritt 2 der Seite
    "Ist das dein Betrieb?" (livewire/portal/claim-form).
    Logik unveraendert in App\Livewire\Portal\ClaimVerification:
    1–5 Dateien, PDF/JPG/PNG, je max. 10 MB, optionaler Kommentar.
--}}
<div>
  @if($state === 'pending' || $submitted)
    {{-- ===== SCHRITT 3: IN PRÜFUNG ===== --}}
    <div class="text-center py-4 flex flex-col items-center gap-3">
      <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
      <p class="text-sm text-zinc-500">{{ __('portal.claim.step', ['schritt' => 3]) }}</p>
      <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.verification.done.heading') }}</h2>
      <p class="text-zinc-500 max-w-sm">{{ __('portal.claim.verification.done.text', ['firma' => $company->name]) }}</p>
    </div>
    <div class="mt-4 rounded-2xl bg-brand-50 p-5">
      <p class="font-semibold text-zinc-900">{{ __('portal.claim.verification.done.next_heading') }}</p>
      <ul class="mt-2 flex flex-col gap-2 text-zinc-700">
        <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>{{ __('portal.claim.verification.done.next_hours') }}</li>
        <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>{{ __('portal.claim.verification.done.next_photo') }}</li>
        <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>{{ __('portal.claim.verification.done.next_services') }}</li>
      </ul>
    </div>

  @elseif($state === 'no_request')
    <p class="text-sm text-zinc-500">{{ __('portal.claim.step', ['schritt' => 2]) }}</p>
    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.verification.heading') }}</h2>
    <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.claim.verification.no_request') }}</p>

  @else
    {{-- ===== SCHRITT 2: NACHWEIS HOCHLADEN ===== --}}
    <p class="text-sm text-zinc-500">{{ __('portal.claim.step', ['schritt' => 2]) }}</p>
    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.verification.heading') }}</h2>
    @if($state === 'rejected')
      <p class="mt-2 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700">{{ $claimRequest?->rejection_reason ? __('portal.claim.verification.rejected_reason', ['grund' => $claimRequest->rejection_reason]) : __('portal.claim.verification.rejected') }}</p>
    @else
      <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.claim.verification.intro') }}</p>
    @endif

    <div class="mt-4 flex items-start gap-3 p-4 rounded-xl border border-brand bg-brand-50">
      <span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>
      <span><span class="font-medium text-zinc-900 block">{{ __('portal.claim.verification.manual_title') }}</span><span class="text-sm text-zinc-500">{{ __('portal.claim.verification.manual_text') }}</span></span>
    </div>

    <label for="claim-documents" class="mt-5 flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50 p-6 text-center cursor-pointer hover:border-brand focus-within:border-brand">
      <span class="font-medium text-zinc-900">{{ __('portal.claim.verification.choose_files') }}</span>
      <span class="text-sm text-zinc-500">{{ __('portal.claim.verification.file_hint') }}</span>
      <input id="claim-documents" type="file" wire:model="documents" multiple accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
    </label>
    <p wire:loading wire:target="documents" class="mt-2 text-sm text-zinc-500">{{ __('portal.claim.verification.uploading') }}</p>
    @error('documents')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('documents.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    @if(count($documents) > 0)
      <ul class="mt-3 flex flex-col gap-2">
        @foreach($documents as $index => $document)
          <li wire:key="claim-doc-{{ $index }}" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-2">
            <span class="min-w-0 truncate text-sm text-zinc-700">{{ $document->getClientOriginalName() }}</span>
            <button type="button" wire:click="removeDocument({{ $index }})" class="btn-ghost px-3 shrink-0" aria-label="{{ __('portal.claim.verification.remove_label', ['datei' => $document->getClientOriginalName()]) }}">{{ __('portal.claim.verification.remove') }}</button>
          </li>
        @endforeach
      </ul>
    @endif

    <label for="claim-comment" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.claim.verification.comment_label') }} <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span></label>
    <textarea id="claim-comment" wire:model="comment" rows="3" maxlength="1000" class="input py-3 h-auto" placeholder="{{ __('portal.claim.verification.comment_placeholder') }}"></textarea>
    @error('comment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
      <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,documents" class="btn-primary flex-1 whitespace-nowrap">
        <span wire:loading.remove wire:target="submit">{{ __('portal.claim.verification.submit') }}</span>
        <span wire:loading wire:target="submit">{{ __('portal.claim.verification.sending') }}</span>
      </button>
    </div>
  @endif
</div>
