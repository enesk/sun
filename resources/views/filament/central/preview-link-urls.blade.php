{{-- Vorschau-Link: eine URL je Portal (Ticket #3). Der Token steht nur in diesem Dialog. --}}
<div
    class="space-y-3"
    x-data="{
        urls: @js(array_values($urls)),
        copied: null,
        copy(text, marker) {
            const done = () => {
                this.copied = marker;
                setTimeout(() => { if (this.copied === marker) this.copied = null }, 2000);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);

                return;
            }

            const area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            done();
        },
    }"
>
    @if ($urls === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Für diesen Link ist kein Portal mit Domain hinterlegt.') }}
        </p>
    @else
        <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
            @foreach ($urls as $portal => $url)
                <li class="flex items-center gap-3 px-3 py-2">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $portal }}</div>
                        <div class="truncate font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $url }}">{{ $url }}</div>
                    </div>
                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-clipboard"
                        x-on:click="copy(urls[{{ $loop->index }}], {{ $loop->index }})"
                    >
                        <span x-show="copied !== {{ $loop->index }}">{{ __('Kopieren') }}</span>
                        <span x-show="copied === {{ $loop->index }}" x-cloak>{{ __('Kopiert') }}</span>
                    </x-filament::button>
                </li>
            @endforeach
        </ul>

        <div class="flex justify-end">
            <x-filament::button
                type="button"
                size="sm"
                icon="heroicon-o-clipboard-document-list"
                x-on:click="copy(urls.join('\n'), 'all')"
            >
                <span x-show="copied !== 'all'">{{ __('Alle kopieren') }}</span>
                <span x-show="copied === 'all'" x-cloak>{{ __('Alle kopiert') }}</span>
            </x-filament::button>
        </div>
    @endif
</div>
