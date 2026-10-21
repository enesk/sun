{{--
    Ein Diff-Wert, lesbar gerendert (SUN-RQ, #11).

    $value   Beschreiber aus App\ProfileRequests\Support\FieldRenderer::describe()
    $html    optional: vorgefaerbter Wort-Diff (Illuminate\Support\HtmlString)
--}}
@php($html = $html ?? null)

@if($html !== null)
    <div class="text-sm text-gray-900 dark:text-white whitespace-pre-line break-words [&_ins]:bg-success-100 [&_ins]:text-success-800 [&_ins]:no-underline [&_ins]:rounded [&_ins]:px-0.5 [&_del]:bg-danger-100 [&_del]:text-danger-800 [&_del]:rounded [&_del]:px-0.5 dark:[&_ins]:bg-success-500/20 dark:[&_ins]:text-success-300 dark:[&_del]:bg-danger-500/20 dark:[&_del]:text-danger-300">{!! $html !!}</div>
@elseif($value['kind'] === 'empty')
    <span class="text-sm italic text-gray-400 dark:text-gray-500">— leer —</span>
@elseif($value['kind'] === 'scalar')
    @if(! empty($value['link']))
        <a href="{{ $value['link'] }}" target="_blank" rel="noopener nofollow"
           class="text-sm text-primary-600 hover:underline break-all dark:text-primary-400">{{ $value['text'] }}</a>
    @else
        <span class="text-sm text-gray-900 break-words dark:text-white">{{ $value['text'] }}</span>
    @endif
@elseif($value['kind'] === 'text')
    <p class="text-sm text-gray-900 whitespace-pre-line break-words dark:text-white">{{ $value['text'] }}</p>
@elseif($value['kind'] === 'image')
    <span class="text-sm text-gray-900 break-all dark:text-white">{{ $value['text'] }}</span>
@elseif($value['kind'] === 'rows')
    {{-- Öffnungszeiten: Tag und Zeiten, nie als JSON --}}
    <dl class="space-y-0.5 text-sm">
        @foreach($value['rows'] as $row)
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                <dd class="{{ ($row['muted'] ?? false) ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white' }}">{{ $row['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@elseif($value['kind'] === 'list')
    {{-- Leistungen, Kategorien --}}
    <ul class="space-y-1 text-sm">
        @foreach($value['items'] as $item)
            <li>
                <span class="text-gray-900 dark:text-white">{{ $item['text'] }}</span>
                @if(! empty($item['note']))
                    <span class="text-gray-500 dark:text-gray-400">· {{ $item['note'] }}</span>
                @endif
                @if(! empty($item['description']))
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item['description'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
