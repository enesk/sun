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
      <p class="text-sm text-zinc-500">Schritt 3 von 3</p>
      <h2 class="text-lg font-semibold text-zinc-900">Nachweis ist da – wir prüfen</h2>
      <p class="text-zinc-500 max-w-sm">Wir sehen uns die Unterlagen innerhalb von 2 Werktagen an. Danach gehört der Eintrag {{ $company->name }} dir und du bekommst eine E-Mail.</p>
    </div>
    <div class="mt-4 rounded-2xl bg-brand-50 p-5">
      <p class="font-semibold text-zinc-900">Als Nächstes lohnt sich:</p>
      <ul class="mt-2 flex flex-col gap-2 text-zinc-700">
        <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>Öffnungszeiten eintragen – Profile mit Zeiten bekommen 2× so viele Anrufe</li>
        <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>Ein echtes Foto vom Betrieb oder Team hochladen</li>
        <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>Die Leistungen anhaken, für die du Anfragen willst</li>
      </ul>
    </div>

  @elseif($state === 'no_request')
    <p class="text-sm text-zinc-500">Schritt 2 von 3</p>
    <h2 class="text-lg font-semibold text-zinc-900">Nachweisen, dass du zum Betrieb gehörst</h2>
    <p class="mt-2 text-zinc-700 leading-relaxed">Für diesen Eintrag liegt noch keine Übernahme von dir vor. Lade die Seite neu und starte bei Schritt 1.</p>

  @else
    {{-- ===== SCHRITT 2: NACHWEIS HOCHLADEN ===== --}}
    <p class="text-sm text-zinc-500">Schritt 2 von 3</p>
    <h2 class="text-lg font-semibold text-zinc-900">Nachweisen, dass du zum Betrieb gehörst</h2>
    @if($state === 'rejected')
      <p class="mt-2 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700">Dein letzter Nachweis hat nicht gereicht{{ $claimRequest?->rejection_reason ? ': '.$claimRequest->rejection_reason : '.' }} Lade bitte neue Unterlagen hoch.</p>
    @else
      <p class="mt-2 text-zinc-700 leading-relaxed">So kann niemand fremde Betriebe übernehmen. Lade ein Dokument hoch, das dich mit dem Betrieb verbindet.</p>
    @endif

    <div class="mt-4 flex items-start gap-3 p-4 rounded-xl border border-brand bg-brand-50">
      <span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span>
      <span><span class="font-medium text-zinc-900 block">Manuell prüfen lassen</span><span class="text-sm text-zinc-500">Du lädst Gewerbeanmeldung oder Briefkopf hoch, wir melden uns innerhalb von 2 Werktagen.</span></span>
    </div>

    <label for="claim-documents" class="mt-5 flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50 p-6 text-center cursor-pointer hover:border-brand focus-within:border-brand">
      <span class="font-medium text-zinc-900">Dateien auswählen</span>
      <span class="text-sm text-zinc-500">PDF, JPG oder PNG · bis 5 Dateien · je max. 10 MB</span>
      <input id="claim-documents" type="file" wire:model="documents" multiple accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
    </label>
    <p wire:loading wire:target="documents" class="mt-2 text-sm text-zinc-500">Wird hochgeladen …</p>
    @error('documents')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('documents.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    @if(count($documents) > 0)
      <ul class="mt-3 flex flex-col gap-2">
        @foreach($documents as $index => $document)
          <li wire:key="claim-doc-{{ $index }}" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-2">
            <span class="min-w-0 truncate text-sm text-zinc-700">{{ $document->getClientOriginalName() }}</span>
            <button type="button" wire:click="removeDocument({{ $index }})" class="btn-ghost px-3 shrink-0" aria-label="Datei entfernen: {{ $document->getClientOriginalName() }}">Entfernen</button>
          </li>
        @endforeach
      </ul>
    @endif

    <label for="claim-comment" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">Hinweis für uns <span class="text-zinc-500 font-normal">(optional)</span></label>
    <textarea id="claim-comment" wire:model="comment" rows="3" maxlength="1000" class="input py-3 h-auto" placeholder="z. B. Ich bin seit 2019 Geschäftsführer, der Briefkopf ist noch auf den alten Namen."></textarea>
    @error('comment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
      <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,documents" class="btn-primary flex-1 whitespace-nowrap">
        <span wire:loading.remove wire:target="submit">Nachweis senden</span>
        <span wire:loading wire:target="submit">Wird gesendet …</span>
      </button>
    </div>
  @endif
</div>
