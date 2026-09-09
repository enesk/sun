{{--
    Tagesblatt des Redaktionskalenders (#19): die Artikel eines Tages, nach
    Portal gruppiert (design/content-dashboard.md, §3). „Termin ändern" ist
    der Weg ohne Maus — dieselbe Aenderung wie das Ziehen in der Wochenansicht.
--}}
@if (count($groups) === 0)
    <p class="text-content-body text-text-base">
        {{ __('Für diesen Tag ist nichts geplant und nichts erschienen.') }}
    </p>
@else
    <div class="space-y-content-6">
        @foreach ($groups as $tenant => $cards)
            <section>
                <h3 class="text-content-h3 font-semibold text-text-strong">{{ $tenant }}</h3>

                <ul class="mt-content-2 space-y-content-2">
                    @foreach ($cards as $card)
                        <li class="flex items-start gap-content-3 border-t border-line-soft pt-content-2">
                            <span class="w-12 flex-none text-content-label text-text-muted">
                                {{ $card['time'] ?? '—' }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-content-table font-medium text-text-strong">
                                    {{ $card['title'] }}
                                </span>
                                <span class="mt-content-1 flex items-center gap-content-2">
                                    <span class="content-status content-status--{{ $card['status'] }}">{{ $card['status_label'] }}</span>
                                    <span class="text-content-label text-text-muted">{{ $card['region'] }}</span>
                                </span>
                            </span>

                            @if ($card['movable'])
                                <button
                                    type="button"
                                    wire:click="mountAction('reschedule', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                                    class="h-8 flex-none rounded-content-md border border-content-600 px-content-3 text-content-label font-medium text-content-700"
                                >
                                    {{ __('Termin ändern') }}
                                </button>
                            @else
                                <span class="flex-none text-content-label text-text-muted">{{ __('fixiert') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
