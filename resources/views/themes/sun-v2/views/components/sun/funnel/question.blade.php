{{--
    Eine Frage des Anfrage-Funnels (#26). Waehlt die Komponente zum Fragetyp und
    reicht die gemeinsamen Angaben durch: Feld-ID, Hilfetext- und Fehler-ID
    (aria-describedby), Texte mit eingesetztem Firmennamen.

    Im Funnel darf ":firma" in Label, Hilfetext und Platzhalter stehen, er wird
    hier durch den Namen des Betriebs ersetzt. Das Wrapper-Element traegt
    data-question-key und data-question-type fuer lead-dialog.js (#27).

    Erwartet: $question (App\Dto\Leads\FunnelQuestion), $firma (string).
--}}
@props(['question', 'firma'])
@php
    $replace = [':firma' => $firma];
    $id = 'lead-'.$question->key;
    $field = [
        'question' => $question,
        'id' => $id,
        'helpId' => $id.'-help',
        'errorId' => $id.'-error',
        'label' => strtr($question->label, $replace),
        'help' => $question->helpText !== null && $question->helpText !== '' ? strtr($question->helpText, $replace) : null,
        'placeholder' => isset($question->meta['placeholder']) ? strtr((string) $question->meta['placeholder'], $replace) : null,
    ];
    $component = in_array($question->type, ['text', 'email', 'phone', 'number', 'date', 'postal_code'], true) ? 'input' : $question->type;
@endphp
<div data-question-key="{{ $question->key }}" data-question-type="{{ $question->type }}" @if($question->required) data-required @endif>
  @include('components.sun.funnel.'.str_replace('_', '-', $component), $field)
  @if($field['help'] && $question->type !== 'info')
    <p id="{{ $field['helpId'] }}" class="mt-1 text-sm text-zinc-500" data-hint-for="{{ $question->key }}">{{ $field['help'] }}</p>
  @endif
  <p id="{{ $field['errorId'] }}" class="mt-1 text-sm text-red-600 hidden" data-error-for="{{ $question->key }}"></p>
</div>
