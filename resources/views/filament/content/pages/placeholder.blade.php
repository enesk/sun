{{--
    Platzhalter der fuenf Bereiche des Content-Panels (#4).
    Panel, Guard, Navigation und Portalauswahl stehen; der Inhalt kommt
    in den Tickets #19, #20 und #25. Die Seite zeigt bewusst an, welches
    Portal gerade gewaehlt ist — daran haengt spaeter jede Kennzahl.
--}}
<x-filament-panels::page>
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
                        ({{ filament()->auth()->user()?->role?->label() }})
                    </span>
                </dd>
            </div>
        </dl>
    </div>
</x-filament-panels::page>
