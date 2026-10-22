{{--
    Diff einer Profil-Anfrage im Central-Panel (SUN-RQ, #11).

    Kein <table>: ab 1024 px (lg) stehen Feld, aktuell und neu in einem
    vierspaltigen Grid nebeneinander, darunter stapeln sie sich untereinander.
    Eine Tabelle muesste dafuer quer scrollen, und eine Quer-Scroll-Tabelle
    mit drei Textspalten ist auf einem 13-Zoll-Notebook unbrauchbar.
--}}
<div class="space-y-4">

    @if($this->isLowConfidenceSuggestion())
        <x-filament::callout icon="heroicon-m-sparkles" color="warning">
            Der Diff ist ein KI-Vorschlag mit
            {{ number_format((float) $this->request()->ai_confidence * 100, 0, ',', '.') }} % Sicherheit —
            die Haken sind deshalb vorab aus. Bitte Zeile für Zeile prüfen.
        </x-filament::callout>
    @endif

    @if(count($rows) === 0)
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Diese Anfrage enthält keinen Feld-Diff.
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">

            {{-- Kopfzeile nur ab 1024 px; gestapelt tragen die Zellen ihre eigenen Labels. --}}
            <div class="hidden lg:grid lg:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)_minmax(0,2fr)_6rem] gap-4 border-b border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                <div>Feld</div>
                <div>Aktuell</div>
                <div>Neu</div>
                <div class="text-right">Übernehmen</div>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach($rows as $row)
                    <div
                        class="grid grid-cols-1 gap-3 px-4 py-4 lg:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)_minmax(0,2fr)_6rem] lg:gap-4 {{ ($accepted[$row['id']] ?? false) ? '' : 'bg-gray-50/60 dark:bg-white/[0.02]' }}"
                        wire:key="diff-row-{{ $row['id'] }}"
                    >
                        {{-- Feld --}}
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $row['label'] }}</div>
                            <div class="font-mono text-xs text-gray-400 dark:text-gray-500">{{ $row['field'] }}</div>
                            @if($row['needsApproval'])
                                <x-filament::badge color="warning" size="xs">manuelle Freigabe</x-filament::badge>
                            @endif
                        </div>

                        {{-- Aktuell --}}
                        <div class="space-y-1">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-400 lg:hidden dark:text-gray-500">Aktuell</div>
                            @include('livewire.central.partials.request-diff-value', [
                                'value' => $row['old'],
                                'html' => $row['wordDiff']['old'] ?? null,
                            ])
                        </div>

                        {{-- Neu, mit Inline-Bearbeitung --}}
                        <div class="space-y-1">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-400 lg:hidden dark:text-gray-500">Neu</div>

                            @if($editing === $row['id'])
                                <div class="space-y-2">
                                    @if($row['multiline'])
                                        <textarea
                                            wire:model.defer="editValues.{{ $row['id'] }}"
                                            rows="6"
                                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        ></textarea>
                                    @else
                                        <input
                                            type="text"
                                            wire:model.defer="editValues.{{ $row['id'] }}"
                                            wire:keydown.enter="saveEdit({{ $row['id'] }})"
                                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        >
                                    @endif

                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::button size="xs" wire:click="saveEdit({{ $row['id'] }})" wire:loading.attr="disabled">
                                            Speichern
                                        </x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="cancelEdit">
                                            Abbrechen
                                        </x-filament::button>
                                    </div>
                                </div>
                            @else
                                @include('livewire.central.partials.request-diff-value', [
                                    'value' => $row['new'],
                                    'html' => $row['wordDiff']['new'] ?? null,
                                ])

                                @if($row['editable'])
                                    <div>
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $row['id'] }})"
                                            class="mt-1 text-xs text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            Wert bearbeiten
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>

                        {{-- Haken --}}
                        <div class="flex items-start gap-2 lg:justify-end">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    @checked($accepted[$row['id']] ?? false)
                                    @disabled($readOnly)
                                    wire:click="toggle({{ $row['id'] }})"
                                    class="fi-checkbox-input rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                >
                                <span class="lg:hidden">Übernehmen</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bild-Anhänge --}}
    @if($attachments->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Bilder aus der Anfrage</x-slot>
            <x-slot name="description">Je Bild entscheiden, ob es Logo oder Galeriebild wird. Logo gibt es nur einmal.</x-slot>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($attachments as $attachment)
                    @php($thumb = $attachment->thumbnailDataUri())
                    <div class="space-y-2 rounded-lg border border-gray-200 p-2 dark:border-white/10" wire:key="attachment-{{ $attachment->id }}">
                        <div class="flex h-32 items-center justify-center overflow-hidden rounded bg-gray-50 dark:bg-white/5">
                            @if($thumb !== null)
                                <img src="{{ $thumb }}" alt="{{ $attachment->fileName() }}" class="max-h-32 max-w-full object-contain">
                            @else
                                <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">Keine Vorschau</span>
                            @endif
                        </div>

                        {{-- Auch das Bild bekommt seinen Weg zur Originaldatei: ein
                             Nachweis zum Uebernahmeantrag ist auf dem 160-Pixel-Thumbnail
                             nicht lesbar. --}}
                        <a
                            href="{{ route('admin.requests.attachment', ['request' => $attachment->request_id, 'attachment' => $attachment->getKey()]) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block truncate font-mono text-xs text-primary-600 hover:underline dark:text-primary-400"
                            title="{{ $attachment->fileName() }}"
                        >{{ $attachment->fileName() }}</a>

                        <div class="flex flex-wrap gap-1">
                            @foreach([
                                \App\ProfileRequests\Models\RequestAttachment::ROLE_LOGO => 'Logo',
                                \App\ProfileRequests\Models\RequestAttachment::ROLE_GALLERY => 'Galerie',
                                \App\ProfileRequests\Models\RequestAttachment::ROLE_IGNORE => 'Ignorieren',
                            ] as $role => $roleLabel)
                                <button
                                    type="button"
                                    @disabled($readOnly)
                                    wire:click="setAttachmentRole({{ $attachment->id }}, '{{ $role }}')"
                                    class="rounded-md px-2 py-1 text-xs font-medium transition
                                        {{ ($attachmentRoles[$attachment->id] ?? '') === $role
                                            ? 'bg-primary-600 text-white'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}"
                                >
                                    {{ $roleLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</div>
