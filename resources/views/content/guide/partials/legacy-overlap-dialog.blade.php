{{--
    Rückfrage zu „Adresse übernehmen …“ bzw. „Weiterleiten (301) …“
    (design/guide-dashboard.md §5.6.4). $row ist der Stand beim Öffnen.
--}}
@if ($row === null)
    <p class="text-sm text-text-base">{{ __('Dieses Paar ist bereits erledigt.') }}</p>
@elseif ($kind === 'adopt')
    <div class="flex flex-col gap-3 text-left text-sm text-text-base">
        <p>
            {!! __('Der Beitrag :title unter :path wird zum Artikel des Themas :question.', [
                'title' => '<em>„'.e($row['post_title']).'“</em>',
                'path' => '<span class="font-mono text-[13px] [overflow-wrap:anywhere]">'.e($row['post_path']).'</span>',
                'question' => '<em>„'.e($row['question']).'“</em>',
            ]) !!}
        </p>
        <ol class="list-decimal space-y-1 pl-5 text-[15px]">
            <li>{{ __('Adresse und Erstveröffentlichungsdatum bleiben — Suchmaschinen-Rang und Verweise gehen nicht verloren.') }}</li>
            <li>{{ __('Beim nächsten Lauf schreibt das System den Inhalt nach der Gliederung des Themas neu. Der bisherige Text wird dabei ersetzt.') }}</li>
            @if ($row['has_draft'])
                <li>{{ __('Der bisherige Entwurf des Themas wird vom Thema gelöst.') }}</li>
            @endif
        </ol>
        <p class="text-text-muted">{{ __('Andere Altartikel zu diesem Thema können Sie danach per 301 hierher weiterleiten.') }}</p>
    </div>
@else
    <div class="flex flex-col gap-3 text-left text-sm text-text-base">
        <p class="font-mono text-[13px] [overflow-wrap:anywhere]">
            {{ $row['post_path'] }} <span aria-label="{{ __('leitet weiter auf') }}">→</span> {{ $row['target_path'] ?? '–' }}
        </p>
        <p class="text-[15px]">{{ __('Der Altartikel wird archiviert und ist nicht mehr erreichbar. Wer seine Adresse aufruft, landet dauerhaft (301) auf dem Artikel des Themas.') }}</p>
    </div>
@endif
