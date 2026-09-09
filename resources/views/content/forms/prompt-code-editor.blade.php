{{--
    Prompt-Editor mit Platzhalterhervorhebung (#54).

    Aufbau wie die View des eingebauten Code-Editors (filament-forms::
    components.code-editor), nur mit eigener Alpine-Komponente: sie bringt
    die CodeMirror-Decoration fuer die Platzhalter mit. Das Muster und der
    Pfad zu den Beispielwerten kommen aus PHP, damit die JS-Seite nicht von
    PromptRenderer abweichen kann.
--}}
@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $isDisabled = $isDisabled();
    $isLive = $isLive();
    $isLiveOnBlur = $isLiveOnBlur();
    $isLiveDebounced = $isLiveDebounced();
    $liveDebounce = $getLiveDebounce();
    $language = $getLanguage();
    $statePath = $getStatePath();
    $variablesStatePath = $getVariablesStatePath();
    $livewireKey = $getLivewireKey();
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :valid="! $errors->has($statePath)"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
                ->class(['fi-fo-code-editor'])
        "
    >
        <div
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('prompt-code-editor') }}"
            x-data="promptCodeEditorFormComponent({
                        canWrap: @js($canWrap()),
                        isDisabled: @js($isDisabled),
                        isLive: @js($isLive),
                        isLiveDebounced: @js($isLiveDebounced),
                        isLiveOnBlur: @js($isLiveOnBlur),
                        liveDebounce: @js($liveDebounce),
                        language: @js($language?->value),
                        placeholderPattern: @js($getPlaceholderPattern()),
                        state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')", isOptimisticallyLive: false) }},
                        variables: @if ($variablesStatePath) $wire.$entangle('{{ $variablesStatePath }}') @else null @endif,
                    })"
            wire:ignore
            wire:key="{{ $livewireKey }}.{{
                substr(md5(serialize([
                    $isDisabled,
                    $language?->value,
                ])), 0, 64)
            }}"
            {{ $getExtraAlpineAttributeBag() }}
        >
            <div x-ref="editor" x-cloak></div>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
