{{--
    Reiterleiste der Einstellungen (#20), design/content-dashboard.md, §7.

    Vier Reiter unter einem Navigationspunkt. Der Quellen-Monitor ist
    ausdruecklich ein Reiter hier und kein sechster Navigationspunkt: er wird
    selten und anlassbezogen geoeffnet.

    Die Reiter sind echte Verweise auf eigene Seiten, keine Livewire-Zustaende —
    damit steht jeder Reiter in der Adresszeile und ist teilbar.
--}}
@php
    $tabs = [
        ['label' => __('Portal'), 'url' => \App\Filament\Content\Pages\Settings::getUrl(), 'key' => 'portal'],
        ['label' => __('Prompts'), 'url' => \App\Filament\Content\Resources\PromptTemplates\PromptTemplateResource::getUrl(), 'key' => 'prompts'],
        ['label' => __('Quellen'), 'url' => \App\Filament\Content\Pages\SourceMonitor::getUrl(), 'key' => 'quellen'],
    ];
@endphp

<div class="mb-content-4 flex items-center gap-content-1 border-b border-line-soft" role="tablist" aria-label="{{ __('Einstellungen') }}">
    @foreach ($tabs as $tab)
        <a
            href="{{ $tab['url'] }}"
            role="tab"
            aria-selected="{{ ($active ?? '') === $tab['key'] ? 'true' : 'false' }}"
            @class([
                'flex h-11 items-center px-content-4 text-content-table font-medium',
                'border-b-2 border-content-600 text-content-700' => ($active ?? '') === $tab['key'],
                'text-text-muted' => ($active ?? '') !== $tab['key'],
            ])
        >{{ $tab['label'] }}</a>
    @endforeach
</div>
