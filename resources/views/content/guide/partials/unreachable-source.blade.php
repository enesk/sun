{{--
    Marke "nicht erreichbar" an einer Quelle (design/guide-dashboard.md §5.7.3):
    Pille status-review mit Symbol, darunter Statuscode und Prüfzeitpunkt.
    Die URL steht daneben als Text, nie als Verweis.
    Erwartet: $code (int|null), $checkedAt (string|null, "d.m., H:i")
--}}
<span class="content-status content-status--review before:hidden">
    <x-filament::icon icon="heroicon-m-link-slash" class="size-3.5 shrink-0" aria-hidden="true" />
    {{ __('nicht erreichbar') }}
</span>
@if (filled($code ?? null) || filled($checkedAt ?? null))
    <span class="block text-[13px] text-text-muted">
        {{ collect([
            filled($code ?? null) ? __('Statuscode :code', ['code' => $code]) : null,
            filled($checkedAt ?? null) ? __('geprüft am :date', ['date' => $checkedAt]) : null,
        ])->filter()->implode(' · ') }}
    </span>
@endif
