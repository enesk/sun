{{--
    Platzhalter der fuenf Bereiche des Ratgeber-Dashboards (#14).
    Panel, Login, Rollen, Navigation und Portalauswahl stehen; der Inhalt
    kommt mit #15 und #16. Die Seite zeigt bewusst an, welches Portal gerade
    gewaehlt ist — daran haengt spaeter jede Kennzahl.
--}}
<x-filament-panels::page>
    @if ($overlapHint = $this->getLegacyOverlapHint())
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line-strong bg-surface-card px-6 py-4 text-[15px] text-text-base">
            <span>{{ $overlapHint['text'] }}</span>
            <a href="{{ $overlapHint['url'] }}" class="font-medium text-content-700 underline underline-offset-2">
                {{ __('Entscheiden') }}
            </a>
        </div>
    @endif

    <div class="rounded-xl border border-line-strong bg-surface-card p-6">
        <h2 class="text-base font-semibold text-text-strong">
            {{ $this->getTitle() }}
        </h2>

        <p class="mt-2 text-sm text-text-base">
            {{ __('Dieser Bereich wird in Ticket :ticket ausgebaut.', ['ticket' => $this->getFollowUpTicket()]) }}
        </p>

        <dl class="mt-6 flex flex-wrap gap-x-10 gap-y-3 text-sm">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">
                    {{ __('Gewähltes Portal') }}
                </dt>
                <dd class="mt-1 font-medium text-text-strong">
                    {{ $this->getSelectedPortalLabel() }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">
                    {{ __('Angemeldet als') }}
                </dt>
                <dd class="mt-1 font-medium text-text-strong">
                    {{ filament()->auth()->user()?->name }}
                    <span class="text-text-muted">
                        ({{ filament()->auth()->user()?->contentRole()?->label() }})
                    </span>
                </dd>
            </div>
        </dl>
    </div>

    @if ($legacyLinks = $this->getLegacyPipelineLinks())
        <div class="rounded-xl border border-line-strong bg-surface-card p-6">
            <h2 class="text-base font-semibold text-text-strong">
                {{ __('Weitere Seiten') }}
            </h2>

            <p class="mt-2 text-sm text-text-base">
                {{ __('Leistung und Portal-Einstellungen bleiben hier erreichbar, bis das neue Dashboard sie übernimmt.') }}
            </p>

            <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                @foreach ($legacyLinks as $link)
                    <li>
                        <a href="{{ $link['url'] }}" class="font-medium text-content-700 underline underline-offset-2">
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-filament-panels::page>
