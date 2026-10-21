{{-- Oeffentliche Statusseite eines Vorgangs (App\Livewire\Public\RequestStatus, SUN-RQ #10).

     Sie-Form (Vorgabe des Ticketsets fuer alles, was an einen Betrieb geht),
     mobile first, geprueft ab 360 px. Huelle: profile-requests.page.

     Reihenfolge ist Absicht: Stand, dann die Handlung, dann der Zeitstrahl,
     zuletzt die eingereichten Werte. Wer die Seite aus der Loeschmail
     aufruft, braucht den Stornoknopf ohne Scrollen. --}}
@php
    $request = $this->profileRequest;
    $timeline = $this->timeline();
    $fields = $this->submittedFields();
@endphp

<div>
    <div class="card">
        <p class="muted" style="margin: 0 0 .25rem;">
            {{ $request->type?->label() }} · Vorgang REQ-{{ $request->id }}
        </p>

        <h1>{{ $this->headline() }}</h1>

        @if ($notice !== null)
            <p class="notice">{{ $notice }}</p>
        @endif

        <p style="margin: 0;">{{ $this->explanation() }}</p>

        @if ($this->canCancelDeletion())
            <div style="margin-top: 1.25rem;">
                <button type="button" class="btn" wire:click="cancelDeletion"
                        wire:confirm="Löschung stoppen? Ihr Eintrag bleibt dann wie er ist."
                        wire:loading.attr="disabled">
                    Löschung stornieren
                </button>
            </div>
        @elseif ($this->canWithdraw())
            <div style="margin-top: 1.25rem;">
                <button type="button" class="btn btn-secondary" wire:click="withdraw"
                        wire:confirm="Anfrage zurückziehen? Wir legen den Vorgang dann zu den Akten."
                        wire:loading.attr="disabled">
                    Anfrage zurückziehen
                </button>
            </div>
        @endif
    </div>

    @if ($timeline !== [])
        <div class="card" style="margin-top: 1rem;">
            <h2>Was bisher passiert ist</h2>
            <ol style="margin: 0; padding: 0; list-style: none;">
                @foreach ($timeline as $step)
                    <li style="display: flex; gap: .75rem; padding: .5rem 0;">
                        <span aria-hidden="true" style="flex: 0 0 auto; margin-top: .45rem; width: .5rem; height: .5rem; border-radius: 9999px; background-color: var(--brand);"></span>
                        <span style="min-width: 0;">
                            <span style="color: #18181b; font-weight: 500;">{{ $step['label'] }}</span><br>
                            <span class="muted">
                                {{ $step['at']?->timezone('Europe/Berlin')->format('d.m.Y, H:i') ?? '—' }} Uhr
                            </span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if ($fields !== [])
        <div class="card" style="margin-top: 1rem;">
            <h2>Das haben Sie eingereicht</h2>

            @foreach ($fields as $field)
                <div style="padding: .75rem 0; border-top: 1px solid #f4f4f5;">
                    <p class="muted" style="margin: 0 0 .25rem;">{{ $field['label'] }}</p>

                    @php $value = $field['value']; @endphp

                    @switch($value['kind'])
                        @case('scalar')
                            <p style="margin: 0; color: #18181b; overflow-wrap: anywhere;">
                                @if (! empty($value['link']))
                                    <a href="{{ $value['link'] }}">{{ $value['text'] }}</a>
                                @else
                                    {{ $value['text'] }}
                                @endif
                            </p>
                            @break

                        @case('text')
                            <p style="margin: 0; color: #18181b; white-space: pre-line; overflow-wrap: anywhere;">{{ $value['text'] }}</p>
                            @break

                        @case('rows')
                            <table style="width: 100%; border-collapse: collapse;">
                                @foreach ($value['rows'] as $row)
                                    <tr>
                                        <th scope="row" style="padding: .125rem .75rem .125rem 0; text-align: left; font-weight: 400; color: #71717a;">{{ $row['label'] }}</th>
                                        <td style="padding: .125rem 0; color: {{ empty($row['muted']) ? '#18181b' : '#a1a1aa' }};">{{ $row['value'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            @break

                        @case('list')
                            <ul style="margin: 0; padding-left: 1.125rem; color: #18181b;">
                                @foreach ($value['items'] as $item)
                                    <li style="margin-bottom: .25rem;">
                                        {{ $item['text'] }}
                                        @if (! empty($item['note']))
                                            <span class="muted">{{ $item['note'] }}</span>
                                        @endif
                                        @if (! empty($item['description']))
                                            <br><span class="muted">{{ $item['description'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @break

                        @case('image')
                            {{-- Nur der Dateiname: das Bild liegt auf einer nicht
                                 oeffentlichen Platte und geht erst mit der Freigabe
                                 ins Profil (#4). --}}
                            <p style="margin: 0; color: #18181b; overflow-wrap: anywhere;">{{ $value['text'] }} (hochgeladen)</p>
                            @break

                        @default
                            <p style="margin: 0; color: #a1a1aa;">Keine Angabe</p>
                    @endswitch
                </div>
            @endforeach
        </div>
    @endif

    @if (trim((string) $request->message) !== '')
        <div class="card" style="margin-top: 1rem;">
            <h2>Ihre Nachricht</h2>
            <p style="margin: 0; white-space: pre-line; overflow-wrap: anywhere;">{{ $request->message }}</p>
        </div>
    @endif

    <p class="muted" style="margin: 1rem 0 0;">
        Wir schreiben zu diesem Vorgang an {{ $this->requesterEmail() }}.
    </p>
</div>
