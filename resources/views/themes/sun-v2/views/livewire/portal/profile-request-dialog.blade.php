{{--
    Self-Service am Firmenprofil (SUN-RQ #4), Theme sun-v2.
    Logik: App\Livewire\Portal\ProfileRequestDialog. Texte: portal.profile_request.*

    Aufbau wie der Anfrage-Dialog des Profils (partials/sun/lead-dialog):
    unter sm ein Blatt am unteren Rand, darueber ein mittiger Kasten; der
    Rumpf scrollt, Kopf und Fuss bleiben stehen. Der Rumpf traegt `min-w-0`,
    und jede Zeile darf umbrechen — bei 360 px bleiben im Blatt rund 328 px,
    und genau daran lief das Profil schon einmal quer (#18).

    Der Vergleich mit dem aktuellen Stand passiert serverseitig
    (BuildChangeSet), deshalb binden die Textfelder mit `wire:model.blur`:
    beim Verlassen eines Feldes aktualisiert sich die Zusammenfassung
    "Sie ändern: …", ohne dass jeder Tastendruck einen Umlauf kostet.
--}}
<div>
  {{-- Skripte unbedingt, auch wenn das Widget erst nach einem Livewire-Umlauf
       im Dialog erscheint: der Stack 'scripts' wird nur beim Rendern des
       Layouts geleert (#53). --}}
  <x-turnstile-scripts action="profile_request" />

  {{-- Dezenter Einstieg, Stil der Zeile "Stimmt etwas nicht?" im Profil --}}
  <p class="text-sm text-zinc-500">
    {{ __('portal.profile_request.trigger.text') }}
    <button type="button" wire:click="open('change')" class="text-brand hover:underline">{{ __('portal.profile_request.trigger.change') }}</button>
    {{ __('portal.profile_request.trigger.or') }}
    <button type="button" wire:click="open('delete')" class="text-brand hover:underline">{{ __('portal.profile_request.trigger.delete') }}</button>
  </p>

  @if($mode !== '')
    @php $istLoeschen = $mode === 'delete'; @endphp
    <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="profileRequestTitle"
         x-data x-on:keydown.escape.window="$wire.close()">
      <div class="absolute inset-0 bg-zinc-900/40" wire:click="close" aria-hidden="true"></div>

      <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-2xl max-h-[92dvh] sm:max-h-[90vh] flex flex-col shadow-lg">

          <div class="flex items-start justify-between gap-3 p-4 sm:p-5 border-b border-zinc-200">
            <div class="min-w-0">
              <h2 id="profileRequestTitle" class="text-lg font-semibold text-zinc-900">
                {{ $istLoeschen ? __('portal.profile_request.delete.title') : __('portal.profile_request.change.title') }}
              </h2>
              <p class="text-sm text-zinc-500 break-words">{{ $company->name }}</p>
            </div>
            <button type="button" wire:click="close" class="btn-ghost px-3 -mr-1 -mt-1 shrink-0" aria-label="{{ __('portal.profile_request.close') }}"><x-sun.icon name="x" class="icon" /></button>
          </div>

          @if($submitted)
            {{-- Fertig: wohin die Bestaetigung geht, Vorgangsnummer, Link auf
                 die Status-Seite (#10) --}}
            <div class="p-5 sm:p-6 flex flex-col items-center gap-3 text-center">
              <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
              <h3 class="text-lg font-semibold text-zinc-900">{{ __('portal.profile_request.done.heading') }}</h3>
              <p class="text-zinc-700 max-w-sm">
                @if($this->hasCompanyEmail())
                  {{ __('portal.profile_request.done.to_company', ['adresse' => $this->verificationTarget()]) }}
                @else
                  {{ __('portal.profile_request.done.manual', ['adresse' => $requesterEmail]) }}
                @endif
              </p>
              @if($istLoeschen)
                <p class="rounded-xl bg-zinc-50 p-3 text-sm text-zinc-700 max-w-sm">{{ __('portal.profile_request.delete.grace') }}</p>
              @endif
              @if($requestId !== null)
                <p class="text-sm text-zinc-500">{{ __('portal.profile_request.done.reference', ['nummer' => '#'.$requestId]) }}</p>
              @endif
              <div class="flex flex-wrap justify-center gap-2 pt-2">
                @if($this->statusUrl())
                  <a href="{{ $this->statusUrl() }}" class="btn-primary">{{ __('portal.profile_request.done.status') }}</a>
                @endif
                <button type="button" wire:click="close" class="btn-secondary">{{ __('portal.profile_request.done.close') }}</button>
              </div>
            </div>
          @else
            <form wire:submit="submit" class="flex flex-col flex-1 min-h-0" novalidate>
              <div class="p-4 sm:p-5 overflow-y-auto flex-1 min-w-0 flex flex-col gap-5">

                <p class="text-sm text-zinc-700">{{ $istLoeschen ? __('portal.profile_request.delete.intro') : __('portal.profile_request.change.intro') }}</p>

                {{-- Honigtopf mit Zufallsnamen und Mindest-Ausfuellzeit (#22) --}}
                <x-antispam-fields wire />

                @error('mode')<p class="rounded-xl bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

                @if($istLoeschen)
                  @include('livewire.portal.partials.profile-request-delete')
                @else
                  {{-- dayNames wird uebergeben und nicht im Teil ueber $this
                       geholt: in einem @include ist die Komponente nicht als
                       $this gebunden. --}}
                  @include('livewire.portal.partials.profile-request-change', ['dayNames' => $this->dayNames()])
                @endif

                {{-- Anfragender: bei beiden Wegen gleich --}}
                <fieldset class="flex flex-col gap-3">
                  <legend class="text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.requester') }}</legend>

                  <div>
                    <label for="pr-rname" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.requester.name') }}</label>
                    <input id="pr-rname" type="text" wire:model.blur="requesterName" class="input @error('requesterName') border-red-500 @enderror" maxlength="100" autocomplete="name">
                    @error('requesterName')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                  </div>

                  <div>
                    <label for="pr-remail" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.requester.email') }}</label>
                    <input id="pr-remail" type="email" wire:model.blur="requesterEmail" class="input @error('requesterEmail') border-red-500 @enderror" maxlength="255" autocomplete="email">
                    @error('requesterEmail')
                      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @else
                      <p class="mt-1 text-sm text-zinc-500">{{ __('portal.profile_request.requester.hint') }}</p>
                    @enderror
                  </div>

                  <div>
                    <label for="pr-rphone" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.requester.phone') }} <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span></label>
                    <input id="pr-rphone" type="tel" wire:model.blur="requesterPhone" class="input @error('requesterPhone') border-red-500 @enderror" maxlength="50" autocomplete="tel">
                    @error('requesterPhone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                  </div>
                </fieldset>

                {{-- Wohin die Bestaetigung geht: die beim Eintrag hinterlegte
                     Adresse, maskiert — nie die hier eingegebene (#6). --}}
                <p class="flex items-start gap-2 rounded-xl bg-brand-50 p-3 text-sm text-zinc-900">
                  <x-sun.icon name="mail" class="icon shrink-0 text-brand" />
                  <span>
                    @if($this->hasCompanyEmail())
                      {{ __('portal.profile_request.verification.to_company', ['adresse' => $this->verificationTarget()]) }}
                    @else
                      {{ __('portal.profile_request.verification.manual') }}
                    @endif
                  </span>
                </p>

                {{-- Turnstile (#4), Modus non_interactive. gate="false": der
                     Knopf haengt am Formular, die Rule entscheidet. --}}
                <x-turnstile action="profile_request" wire="turnstileToken" field="turnstileToken" size="flexible" gate="false" class="mt-0" />
              </div>

              <div class="border-t border-zinc-200 p-4 sm:p-5 flex flex-col gap-3">
                @unless($istLoeschen)
                  {{-- Zusammenfassung direkt ueber dem Knopf: der Besucher
                       soll vor dem Absenden sehen, was er aendert. --}}
                  <div class="text-sm">
                    @if($this->changedLabels() !== [])
                      <p class="text-zinc-900"><span class="font-semibold">{{ __('portal.profile_request.change.summary_heading') }}</span> {{ implode(', ', $this->changedLabels()) }}</p>
                    @else
                      <p class="text-zinc-500">{{ __('portal.profile_request.change.summary_empty') }}</p>
                    @endif
                  </div>
                @endunless

                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="submit">
                    {{ $istLoeschen ? __('portal.profile_request.delete.submit') : __('portal.profile_request.change.submit') }}
                  </button>
                  <button type="button" wire:click="{{ $istLoeschen ? "open('change')" : "open('delete')" }}" class="btn-ghost w-full sm:w-auto">
                    {{ $istLoeschen ? __('portal.profile_request.switch_to_change') : __('portal.profile_request.switch_to_delete') }}
                  </button>
                </div>
              </div>
            </form>
          @endif
        </div>
      </div>
    </div>
  @endif
</div>
