{{--
    Key-Facts („Das Wichtigste in Zahlen“). Herkunftszeile mit Quelle und
    Stand ist Pflicht, sobald Werte angezeigt werden.

    Erwartet: $keyFacts — ['rows' => [[label, value]], 'caption' => ?string, 'numeric' => bool].
--}}
@if(!empty($keyFacts['rows']))
    <div class="ratgeber-facts">
        <table class="ratgeber-facts__table">
            <caption class="ratgeber-facts__caption">Das Wichtigste in Zahlen</caption>
            <tbody>
                @foreach($keyFacts['rows'] as $row)
                    <tr>
                        <th scope="row" class="ratgeber-facts__label">{{ $row['label'] }}</th>
                        <td class="ratgeber-facts__value {{ !empty($keyFacts['numeric']) ? 'ratgeber-facts__value--numeric' : '' }}">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(!empty($keyFacts['caption']))
            <p class="ratgeber-facts__source">{{ $keyFacts['caption'] }}</p>
        @endif
    </div>
@endif
