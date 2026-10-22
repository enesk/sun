{{--
    Detailansicht eines Vorgangs der Anfragen-Inbox (SUN-RQ, #7).

    Zweispaltig ab 1024 px (lg): links die Anfrage, rechts das Profil im
    Portal. Darunter ueber die volle Breite der Diff aus #11 — er braucht
    den Platz, und gestapelt bricht er selbst um.

    Alle Werte kommen fertig aus ViewProfileRequest::getViewData(); hier wird
    kein Tenant-Model angefasst, denn diese Seite rendert im Central-Kontext.
--}}
<x-filament-panels::page>

    @php
        $verifiziert = $profileRequest->isVerified();
    @endphp

    @if ($profileRequest->company_id === null)
        <x-filament::callout icon="heroicon-m-exclamation-triangle" color="warning">
            Dieser Anfrage ist kein Betrieb zugeordnet. „Übernehmen“ und „Löschung ausführen“
            bleiben gesperrt, bis die Zuordnung steht.
        </x-filament::callout>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- ─────────────────────────── links: die Anfrage ─────────────── --}}
        <div class="space-y-6">

            <x-filament::section>
                <x-slot name="heading">Die Anfrage</x-slot>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Portal</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $profileRequest->tenant?->name ?? '— unbekannt —' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Eingegangen</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">
                            {{ $profileRequest->created_at?->format(config('app.datetime_format')) }}
                            <span class="text-gray-500 dark:text-gray-400">({{ $profileRequest->created_at?->diffForHumans() }})</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Absender</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">
                            {{ $profileRequest->requester_name ?: '—' }}<br>
                            <a href="mailto:{{ $profileRequest->requester_email }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                {{ $profileRequest->requester_email }}
                            </a>
                            @if ($profileRequest->requester_phone)
                                <br>{{ $profileRequest->requester_phone }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Verifikation</dt>
                        <dd class="text-sm">
                            <x-filament::badge :color="$verifiziert ? 'success' : 'gray'">
                                {{ $verifiziert ? ($profileRequest->verification_method?->label() ?? 'bestätigt') : 'offen' }}
                            </x-filament::badge>
                            @if ($verifiziert)
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $profileRequest->verified_at?->format(config('app.datetime_format')) }}
                                    @if ($profileRequest->verified_email)
                                        · {{ $profileRequest->verified_email }}
                                    @endif
                                </div>
                            @elseif ($profileRequest->verification_expires_at)
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Link gültig bis {{ $profileRequest->verification_expires_at->format(config('app.datetime_format')) }}
                                </div>
                            @endif
                        </dd>
                    </div>
                    @if ($profileRequest->grace_until)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Karenzfrist</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">bis {{ $profileRequest->grace_until->format(config('app.datetime_format')) }}</dd>
                        </div>
                    @endif
                    @if ($profileRequest->rejected_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Grund der Ablehnung</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $profileRequest->rejected_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </x-filament::section>

            @if ($profileRequest->ai_summary)
                <x-filament::section icon="heroicon-m-sparkles">
                    <x-slot name="heading">Zusammenfassung der KI</x-slot>
                    <x-slot name="description">
                        {{-- Keine Direktive direkt hinter einem Buchstaben: Blade erkennt
                             sie nur, wenn vor dem Klammeraffen kein Wortzeichen steht
                             (\B im Parser), und gibt sie sonst als Text aus. Genau das
                             stand hier: der Hinweis endete mit der rohen Bedingung.
                             Der Satz wird deshalb in PHP gebaut. --}}
                        @php
                            $sicherheit = $profileRequest->ai_confidence === null
                                ? null
                                : number_format((float) $profileRequest->ai_confidence * 100, 0, ',', '.');
                        @endphp

                        {{ $sicherheit === null
                            ? 'Ein Vorschlag, keine Entscheidung.'
                            : "Ein Vorschlag, keine Entscheidung — {$sicherheit} % Sicherheit." }}
                    </x-slot>

                    <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $profileRequest->ai_summary }}</p>
                </x-filament::section>
            @endif

            <x-filament::section collapsible>
                <x-slot name="heading">Originaltext</x-slot>

                @if (filled($profileRequest->message))
                    <p class="whitespace-pre-line break-words text-sm text-gray-700 dark:text-gray-300">{{ $profileRequest->message }}</p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kein Freitext — die Anfrage kam strukturiert aus dem Formular.</p>
                @endif

                @if ($documents->isNotEmpty())
                    <div class="mt-4 space-y-1">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Anhänge</div>
                        <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                            @foreach ($documents as $document)
                                {{-- Der Link geht auf die eigene Route, nicht auf die Platte:
                                     die Dateien liegen privat und bleiben es. Die Route prueft
                                     Anmeldung, Administratorrecht und Zugehoerigkeit
                                     (App\ProfileRequests\Http\Controllers\RequestAttachmentController). --}}
                                <li class="flex items-center gap-2">
                                    <x-filament::icon icon="heroicon-m-paper-clip" class="h-4 w-4 text-gray-400" />
                                    <a
                                        href="{{ route('admin.requests.attachment', ['request' => $profileRequest->getKey(), 'attachment' => $document->getKey()]) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-mono text-xs text-primary-600 hover:underline dark:text-primary-400"
                                    >{{ $document->fileName() }}</a>
                                    <span class="text-xs text-gray-400">{{ $document->mime }} · {{ number_format($document->size / 1024, 0, ',', '.') }} kB</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>

            @if (! empty($candidates))
                <x-filament::section>
                    <x-slot name="heading">Vorschläge für die Zuordnung</x-slot>
                    <x-slot name="description">Gefunden über Adresse, Rufnummer und Name. Zuordnen über die Aktion oben.</x-slot>

                    <ul class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @foreach ($candidates as $candidate)
                            <li class="flex items-start justify-between gap-4 py-2">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $candidate['name'] }} <span class="font-mono text-xs text-gray-400">#{{ $candidate['id'] }}</span></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ trim($candidate['address'].' · '.$candidate['city'], ' ·') }}
                                        @if ($candidate['email']) · {{ $candidate['email'] }} @endif
                                    </div>
                                </div>
                                <x-filament::badge color="gray">
                                    {{ number_format($candidate['score'] * 100, 0, ',', '.') }} % · {{ implode(', ', $candidate['matched_on']) }}
                                </x-filament::badge>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif

            <x-filament::section collapsible>
                <x-slot name="heading">Protokoll</x-slot>
                <x-slot name="description">Jede Aktion mit Akteur und Zeitpunkt. Wird nie gelöscht.</x-slot>

                <ol class="space-y-3">
                    @foreach ($events as $event)
                        <li class="flex gap-3 text-sm">
                            <div class="w-32 shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                {{ $event->created_at?->format(config('app.datetime_format')) }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $event->event instanceof \App\ProfileRequests\Enums\RequestEventType ? $event->event->label() : $event->event }}
                                    @if ($event->isTransition())
                                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                            ({{ $event->from_status?->label() ?? '—' }} → {{ $event->to_status?->label() }})
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $event->actorLabel() }}@if ($event->actor_id) #{{ $event->actor_id }}@endif
                                </div>
                                @if (! empty($event->payload))
                                    <pre class="mt-1 overflow-x-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 font-mono text-[11px] text-gray-600 dark:bg-white/5 dark:text-gray-400">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-filament::section>
        </div>

        {{-- ─────────────────────── rechts: das Profil im Portal ────────── --}}
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">Profil im Portal</x-slot>
                <x-slot name="description">Der aktuelle Stand, nur zum Lesen.</x-slot>

                @if ($profile === null)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Kein Betrieb zugeordnet — oder der Eintrag liegt nicht mehr in der Datenbank des Portals.
                    </p>
                @else
                    @if ($liveUrl)
                        <div class="mb-4">
                            <x-filament::link :href="$liveUrl" target="_blank" rel="noopener" icon="heroicon-m-arrow-top-right-on-square">
                                Live-Seite öffnen
                            </x-filament::link>
                        </div>
                    @endif

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ([
                            'name' => 'Firmenname',
                            'adresse' => 'Straße',
                            'ort' => 'PLZ / Ort',
                            'tel' => 'Telefon',
                            'email' => 'E-Mail',
                            'website' => 'Website',
                            'inhaber' => 'Inhaber',
                            'zustand' => 'Zustand',
                        ] as $key => $label)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="break-words text-sm text-gray-900 dark:text-white">{{ filled($profile[$key] ?? null) ? $profile[$key] : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if (filled($profile['beschreibung'] ?? null))
                        <div class="mt-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Beschreibung</div>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $profile['beschreibung'] }}</p>
                        </div>
                    @endif

                    @if (filled($profile['logo'] ?? null))
                        <div class="mt-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Logo</div>
                            <img src="{{ $profile['logo'] }}" alt="Logo {{ $profile['name'] }}" class="mt-1 max-h-24 rounded border border-gray-200 bg-white p-1 dark:border-white/10">
                        </div>
                    @endif
                @endif
            </x-filament::section>
        </div>
    </div>

    {{-- ───────────────────────────── der Diff (#11) ───────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Änderungen</x-slot>
        <x-slot name="description">Haken je Zeile — übernommen wird nur, was angehakt ist.</x-slot>

        @livewire(
            'central.request-diff-table',
            ['requestId' => (int) $profileRequest->getKey(), 'readOnly' => ! $profileRequest->isOpen()],
            key('diff-'.$profileRequest->getKey().'-'.$profileRequest->status->value)
        )
    </x-filament::section>
</x-filament-panels::page>
