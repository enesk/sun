{{--
    Key-Facts-Tabelle ("Das Wichtigste in Zahlen").
    Erwartet: $keyFacts — ['rows' => [...], 'caption' => ?string, 'numeric' => bool].
--}}
@if(!empty($keyFacts['rows']))
    <div class="ratgeber-facts">
        <table class="ratgeber-facts__table">
            <caption class="ratgeber-facts__caption">Das Wichtigste in Zahlen</caption>
            <tbody>
                @foreach($keyFacts['rows'] as $row)
                    <tr>
                        <th scope="row" class="ratgeber-facts__label">{{ $row['label'] }}</th>
                        <td class="ratgeber-facts__value {{ $keyFacts['numeric'] ? 'ratgeber-facts__value--numeric' : '' }}">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(!empty($keyFacts['caption']))
            <p class="ratgeber-facts__source">{{ $keyFacts['caption'] }}</p>
        @endif
    </div>
@endif
