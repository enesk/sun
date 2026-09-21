{{--
    Zusammenfassung vor "Import abschließen" (design/guide-dashboard.md §4.4).
    Erwartet: $topics, $portals, $total, $new_categories, $with_outline,
    $without_outline, $cost (aus ImportWizard::summary()).
--}}
<div class="rounded-xl border border-line-strong bg-surface-card p-4 text-content-body text-text-base">
    <p>
        {!! __('Es werden bis zu <strong>:topics Themen</strong> in <strong>:portals</strong> angelegt (bis zu :total Themen); was ein Portal schon hat, wird dort übersprungen.', [
            'topics' => e($topics),
            'portals' => e(trans_choice('{0} keinem Portal|{1} einem Portal|[2,*] :count Portalen', $portals, ['count' => $portals])),
            'total' => e($total),
        ]) !!}
        @if ($new_categories > 0)
            {{ trans_choice('{1} Eine Kategorie wird neu angelegt.|[2,*] :count Kategorien werden neu angelegt.', $new_categories, ['count' => $new_categories]) }}
        @endif
    </p>
    <p class="mt-2">
        {{ __(':with Themen haben vorgegebene Überschriften — deren Gliederung wird sofort gesperrt. :without Themen bekommen im ersten Lauf einen Gliederungsvorschlag und warten danach unter „Gliederung bestätigen“ auf Ihre Freigabe.', ['with' => $with_outline, 'without' => $without_outline]) }}
    </p>
    <p class="mt-2">
        {{ __('Der Import selbst kostet nichts. Geschrieben wird erst im Tageslauf eines freigeschalteten Portals; die Ersterstellung aller Artikel kostet dann ≈ :cost.', ['cost' => $cost]) }}
    </p>
</div>
