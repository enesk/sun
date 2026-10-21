{{--
    Self-Service am Firmenprofil (SUN-RQ #4), Theme default — 18 der 23
    Portale (SUN-RQ #14).
    Logik: App\Livewire\Portal\ProfileRequestDialog, unveraendert. Texte:
    portal.profile_request.*

    Aufbau mit den Modal-Klassen des Themes (portal.css:
    .suggest-edit-backdrop/-overlay/-modal), damit der Dialog so aussieht und
    sich so verhaelt wie der Rest des Profils — inklusive Dark Mode und
    reduzierter Bewegung. Der Kasten scrollt als Ganzes; einen fixen Fuss hat
    das Theme nicht, deshalb stehen Zusammenfassung und Knoepfe am Ende des
    Formulars.

    Der Einstieg traegt .suggest-edit-inline-cta, die Zeile, die vor #14 an
    dieser Stelle auf die Seite companies.suggest-edit verlinkt hat.

    Der Vergleich mit dem aktuellen Stand passiert serverseitig
    (BuildChangeSet), deshalb binden die Textfelder mit `wire:model.blur`.
--}}
<div>
    {{-- Skripte unbedingt, auch wenn das Widget erst nach einem Livewire-Umlauf
         im Dialog erscheint: der Stack 'scripts' wird nur beim Rendern des
         Layouts geleert (#53). --}}
    <x-turnstile-scripts action="profile_request" />

    <div class="suggest-edit-inline-cta reveal">
        <svg class="w-4 h-4 text-base-content/40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        <span class="text-sm text-base-content/50">{{ __('portal.profile_request.trigger.text') }}</span>
        <button type="button"
                wire:click="open('change')"
                class="text-sm font-medium hover:underline transition-colors"
                style="color: var(--portal-primary)"
                aria-haspopup="dialog">{{ __('portal.profile_request.trigger.change') }}</button>
        <span class="text-sm text-base-content/50">{{ __('portal.profile_request.trigger.or') }}</span>
        <button type="button"
                wire:click="open('delete')"
                class="text-sm font-medium hover:underline transition-colors"
                style="color: var(--portal-primary)"
                aria-haspopup="dialog">{{ __('portal.profile_request.trigger.delete') }}</button>
    </div>

    @if($mode !== '')
        @php $istLoeschen = $mode === 'delete'; @endphp
        <div class="suggest-edit-backdrop"
             x-data
             x-on:keydown.escape.window="$wire.close()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="profile-request-title">

            <div class="suggest-edit-overlay" wire:click="close" aria-hidden="true"></div>

            <div class="suggest-edit-modal">
                <div class="suggest-edit-modal__header">
                    <div class="min-w-0">
                        <h3 id="profile-request-title" class="text-lg font-semibold text-base-content">
                            {{ $istLoeschen ? __('portal.profile_request.delete.title') : __('portal.profile_request.change.title') }}
                        </h3>
                        <p class="text-sm text-base-content/60 break-words">{{ $company->name }}</p>
                    </div>
                    <button type="button"
                            wire:click="close"
                            class="suggest-edit-modal__close"
                            aria-label="{{ __('portal.profile_request.close') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @if($submitted)
                    {{-- Fertig: wohin die Bestaetigung geht, Vorgangsnummer, Link auf
                         die Status-Seite (#10) --}}
                    <div class="suggest-edit-modal__body text-center items-center py-6">
                        <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center">
                            <svg class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-base-content">{{ __('portal.profile_request.done.heading') }}</h4>
                        <p class="text-sm text-base-content/70 max-w-sm">
                            @if($this->hasCompanyEmail())
                                {{ __('portal.profile_request.done.to_company', ['adresse' => $this->verificationTarget()]) }}
                            @else
                                {{ __('portal.profile_request.done.manual', ['adresse' => $requesterEmail]) }}
                            @endif
                        </p>
                        @if($istLoeschen)
                            <p class="rounded-xl bg-base-200 p-3 text-sm text-base-content/70 max-w-sm">{{ __('portal.profile_request.delete.grace') }}</p>
                        @endif
                        @if($requestId !== null)
                            <p class="text-sm text-base-content/50">{{ __('portal.profile_request.done.reference', ['nummer' => '#'.$requestId]) }}</p>
                        @endif
                        <div class="flex flex-wrap justify-center gap-3 pt-1">
                            @if($this->statusUrl())
                                <a href="{{ $this->statusUrl() }}" class="btn-portal px-5 py-2 rounded-lg text-sm font-medium ripple">{{ __('portal.profile_request.done.status') }}</a>
                            @endif
                            <button type="button" wire:click="close" class="btn-portal-outline px-4 py-2 rounded-lg text-sm font-medium">{{ __('portal.profile_request.done.close') }}</button>
                        </div>
                    </div>
                @else
                    <form wire:submit="submit" class="suggest-edit-modal__body" novalidate>
                        <p class="text-sm text-base-content/70">{{ $istLoeschen ? __('portal.profile_request.delete.intro') : __('portal.profile_request.change.intro') }}</p>

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
                            <legend class="text-sm font-semibold text-base-content mb-1.5">{{ __('portal.profile_request.sections.requester') }}</legend>

                            <div>
                                <label for="pr-rname" class="label-portal mb-1.5">{{ __('portal.profile_request.requester.name') }}</label>
                                <input id="pr-rname" type="text" wire:model.blur="requesterName" class="input-portal w-full" maxlength="100" autocomplete="name">
                                @error('requesterName')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="pr-remail" class="label-portal mb-1.5">{{ __('portal.profile_request.requester.email') }}</label>
                                <input id="pr-remail" type="email" wire:model.blur="requesterEmail" class="input-portal w-full" maxlength="255" autocomplete="email">
                                @error('requesterEmail')
                                    <p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>
                                @else
                                    <p class="text-xs text-base-content/50 mt-1">{{ __('portal.profile_request.requester.hint') }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pr-rphone" class="label-portal mb-1.5">{{ __('portal.profile_request.requester.phone') }} <span class="text-base-content/40">{{ __('portal.layout.optional') }}</span></label>
                                <input id="pr-rphone" type="tel" wire:model.blur="requesterPhone" class="input-portal w-full" maxlength="50" autocomplete="tel">
                                @error('requesterPhone')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </fieldset>

                        {{-- Wohin die Bestaetigung geht: die beim Eintrag hinterlegte
                             Adresse, maskiert — nie die hier eingegebene (#6). --}}
                        <p class="flex items-start gap-2 rounded-xl p-3 text-sm text-base-content"
                           style="background: rgba(var(--portal-primary-rgb), 0.06);">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
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

                        @unless($istLoeschen)
                            {{-- Zusammenfassung direkt ueber dem Knopf: der Besucher
                                 soll vor dem Absenden sehen, was er aendert. --}}
                            <div class="text-sm border-t border-base-content/10 pt-3">
                                @if($this->changedLabels() !== [])
                                    <p class="text-base-content"><span class="font-semibold">{{ __('portal.profile_request.change.summary_heading') }}</span> {{ implode(', ', $this->changedLabels()) }}</p>
                                @else
                                    <p class="text-base-content/50">{{ __('portal.profile_request.change.summary_empty') }}</p>
                                @endif
                            </div>
                        @endunless

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
                            <button type="button"
                                    wire:click="{{ $istLoeschen ? "open('change')" : "open('delete')" }}"
                                    class="btn-portal-outline px-4 py-2 rounded-lg text-sm font-medium">
                                {{ $istLoeschen ? __('portal.profile_request.switch_to_change') : __('portal.profile_request.switch_to_delete') }}
                            </button>
                            <button type="submit"
                                    class="btn-portal px-5 py-2 rounded-lg text-sm font-medium ripple inline-flex items-center justify-center gap-2"
                                    wire:loading.attr="disabled"
                                    wire:target="submit"
                                    wire:loading.class="opacity-60 cursor-wait">
                                <svg wire:loading wire:target="submit" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ $istLoeschen ? __('portal.profile_request.delete.submit') : __('portal.profile_request.change.submit') }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
