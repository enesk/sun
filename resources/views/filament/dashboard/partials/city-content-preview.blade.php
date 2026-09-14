{{-- Vorschau des aufgeloesten Local-Hub-Inhalts (#11), Daten aus CityContentResolver::resolve() --}}
@php
    $sourceLabel = fn (?string $source) => match ($source) {
        'override' => 'Stadt-Override',
        'template' => 'Tenant-Vorlage',
        default => 'kein Inhalt',
    };
@endphp

<div class="space-y-4 text-sm">
    @if (! $city)
        <p class="text-gray-500 dark:text-gray-400">Stadt wählen, um die Vorschau zu sehen.</p>
    @elseif (! $content)
        <p class="text-gray-500 dark:text-gray-400">Für {{ $city->name }} wird kein Local-Hub-Block ausgegeben.</p>
    @else
        <p class="text-gray-500 dark:text-gray-400">Beispielstadt: <strong>{{ $city->name }}</strong></p>

        <div>
            <p class="font-medium">Einleitung <span class="text-gray-500 dark:text-gray-400">({{ $sourceLabel($content['intro_source']) }})</span></p>
            @if ($content['intro_html'])
                <div class="prose prose-sm dark:prose-invert max-w-none mt-1">{!! $content['intro_html'] !!}</div>
            @endif
        </div>

        <div>
            <p class="font-medium">Stadtteile</p>
            <p class="mt-1">{{ $content['districts'] ? implode(' · ', $content['districts']) : '—' }}</p>
        </div>

        <div>
            <p class="font-medium">FAQ <span class="text-gray-500 dark:text-gray-400">({{ $sourceLabel($content['faq_source']) }})</span></p>
            @foreach ($content['faqs'] as $faq)
                <div class="mt-2">
                    <p class="font-semibold">{{ $faq['question'] }}</p>
                    <p class="whitespace-pre-line">{{ $faq['answer'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
