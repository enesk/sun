{{--
    Key-Facts im Theme sun-v2. Herkunftszeile mit Quelle und Stand ist Pflicht,
    sobald Werte angezeigt werden.
    Erwartet: $keyFacts — ['rows' => [[label, value]], 'caption' => ?string, 'numeric' => bool].
--}}
@if(!empty($keyFacts['rows']))
  <div class="mt-8 overflow-x-auto">
    <table class="w-full text-base">
      <caption class="text-left font-semibold text-zinc-900 pb-2">Das Wichtigste in Zahlen</caption>
      <tbody class="divide-y divide-zinc-100">
        @foreach($keyFacts['rows'] as $row)
          <tr>
            <th scope="row" class="py-3 pr-4 text-left font-normal text-zinc-700">{{ $row['label'] }}</th>
            <td class="py-3 font-semibold text-zinc-900 {{ !empty($keyFacts['numeric']) ? 'tabular-nums whitespace-nowrap text-right' : '' }}">{{ $row['value'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    @if(!empty($keyFacts['caption']))
      <p class="mt-2 text-sm text-zinc-500">{{ $keyFacts['caption'] }}</p>
    @endif
  </div>
@endif
