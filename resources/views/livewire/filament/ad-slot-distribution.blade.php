<div class="space-y-6">

    {{-- ==================== EXPORT ==================== --}}
    <x-filament::section>
        <x-slot name="heading">Export</x-slot>
        <x-slot name="description">Ad-Slots eines Tenants als JSON exportieren</x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label for="exportTenant" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Quell-Tenant
                    </label>
                    <select
                        id="exportTenant"
                        wire:model.live="exportTenantId"
                        class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">— Tenant wählen —</option>
                        @foreach($tenants as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-4">
                    @if($exportSlotCount !== null)
                        <div class="text-sm text-gray-600 dark:text-gray-400 pb-2">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $exportSlotCount }}</span> Ad-Slot(s) vorhanden
                            @if($exportSlotCount === 0)
                                <span class="text-warning-600 dark:text-warning-400">— Export erzeugt eine leere Datei</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <x-filament::button
                    wire:click="export"
                    :disabled="!$exportTenantId || $exportSlotCount === 0"
                    icon="heroicon-o-arrow-down-tray"
                >
                    Exportieren
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    {{-- ==================== IMPORT ==================== --}}
    <x-filament::section>
        <x-slot name="heading">Import</x-slot>
        <x-slot name="description">Ad-Slots aus einer JSON-Datei in Ziel-Tenants importieren</x-slot>

        <div class="space-y-6">

            {{-- Datei-Upload --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    JSON-Datei
                </label>
                <input
                    type="file"
                    wire:model="importFile"
                    accept=".json,application/json"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-gray-700 dark:file:text-gray-300"
                />
                <div wire:loading wire:target="importFile" class="mt-2 text-sm text-gray-500">
                    Datei wird hochgeladen...
                </div>
            </div>

            {{-- Validierungsfehler --}}
            @if($validationErrors)
                <div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-4 border border-danger-200 dark:border-danger-800">
                    <div class="flex">
                        <x-heroicon-s-exclamation-triangle class="h-5 w-5 text-danger-400 shrink-0" />
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-danger-800 dark:text-danger-200">
                                Validierungsfehler
                            </h3>
                            <ul class="mt-2 list-disc list-inside text-sm text-danger-700 dark:text-danger-300 space-y-1">
                                @foreach($validationErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Import-Daten geladen --}}
            @if($importData)
                <div class="rounded-lg bg-success-50 dark:bg-success-950 p-4 border border-success-200 dark:border-success-800">
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="h-5 w-5 text-success-500 shrink-0" />
                        <span class="text-sm font-medium text-success-800 dark:text-success-200">
                            Datei gültig — {{ $importData['meta']['slot_count'] ?? 0 }} Slot(s),
                            Quelle: {{ $importData['meta']['source_tenant'] ?? '—' }},
                            Version: {{ $importData['meta']['schema_version'] ?? '—' }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- Ziel-Tenants --}}
            <div x-data="{ tenantSearch: '' }">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Ziel-Tenant(s)
                </label>
                @unless($importData)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                        Bitte zuerst eine JSON-Datei hochladen.
                    </p>
                @endunless
                <input
                    type="text"
                    x-model="tenantSearch"
                    placeholder="Tenants durchsuchen…"
                    class="block w-full mb-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                    @unless($importData) disabled @endunless
                />
                <div class="max-h-60 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-3 space-y-1 {{ !$importData ? 'opacity-50 pointer-events-none' : '' }}">
                    @foreach($tenants as $id => $name)
                        <label
                            x-show="!tenantSearch || '{{ strtolower(addslashes($name)) }}'.includes(tenantSearch.toLowerCase())"
                            class="flex items-center gap-2 cursor-pointer py-1 px-2 rounded hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            <input
                                type="checkbox"
                                wire:model.live="importTenantIds"
                                value="{{ $id }}"
                                class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-600"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
                @if(count($importTenantIds) > 1)
                    <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">
                        {{ count($importTenantIds) }} Tenants gewählt — Import wird über die Queue verarbeitet.
                    </p>
                @endif
            </div>

            {{-- Vorschau --}}
            @if($previewData)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Vorschau für „{{ $previewData['tenantName'] }}"
                        </h4>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Slots in Datei:</span>
                                <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['slotCount'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Neue Slots:</span>
                                <span class="ml-1 font-semibold text-success-600 dark:text-success-400">{{ $previewData['newCount'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Konflikte:</span>
                                <span class="ml-1 font-semibold {{ $previewData['conflictCount'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">
                                    {{ $previewData['conflictCount'] }}
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Bestehend:</span>
                                <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['existingSlotCount'] }}</span>
                            </div>
                        </div>

                        {{-- Ersetzen-Warnung --}}
                        @if($importMode === 'replace' && ($previewData['existingSlotCount'] ?? 0) > 0)
                            <div class="rounded-md bg-danger-50 dark:bg-danger-950 p-3 border border-danger-200 dark:border-danger-800">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-s-exclamation-triangle class="h-4 w-4 text-danger-500 shrink-0" />
                                    <span class="text-sm font-medium text-danger-800 dark:text-danger-200">
                                        {{ $previewData['existingSlotCount'] }} bestehende Slot(s) in „{{ $previewData['tenantName'] }}" werden gelöscht und durch {{ $previewData['slotCount'] }} neue ersetzt.
                                    </span>
                                </div>
                            </div>
                        @endif

                        {{-- Positionen --}}
                        @if(!empty($previewData['positions']))
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Positionen</span>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach($previewData['positions'] as $pos)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            {{ $pos['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Konflikte --}}
                        @if(!empty($previewData['conflicts']))
                            <div>
                                <span class="text-xs font-medium text-warning-600 dark:text-warning-400 uppercase tracking-wider">Konflikte</span>
                                <div class="mt-1 space-y-1">
                                    @foreach($previewData['conflicts'] as $conflict)
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">
                                                Konflikt
                                            </span>
                                            <span class="text-gray-700 dark:text-gray-300">
                                                „{{ $conflict['name'] }}" in {{ $conflict['position_label'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Import-Modus --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Import-Modus
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="importMode" value="add"
                                class="fi-radio-input text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <strong>Hinzufügen</strong> — bestehende Slots bleiben erhalten
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="importMode" value="replace"
                                class="fi-radio-input text-danger-600 focus:ring-danger-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <strong class="text-danger-600 dark:text-danger-400">Ersetzen</strong> — alle bestehenden Slots werden gelöscht
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Konfliktstrategie (nur bei Hinzufügen) --}}
                @if($importMode === 'add')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Bei Konflikten
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model="conflictStrategy" value="skip"
                                    class="fi-radio-input text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    <strong>Überspringen</strong> — bestehende Slots nicht ändern
                                </span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model="conflictStrategy" value="update"
                                    class="fi-radio-input text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    <strong>Aktualisieren</strong> — bestehende Slots überschreiben
                                </span>
                            </label>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Import-Button --}}
            <div class="flex items-center gap-4">
                <x-filament::button
                    wire:click="startImport"
                    wire:loading.attr="disabled"
                    wire:target="startImport, confirmReplace"
                    :disabled="!$importData || empty($importTenantIds)"
                    :color="$importMode === 'replace' ? 'danger' : 'primary'"
                    icon="heroicon-o-arrow-up-tray"
                >
                    <span wire:loading.remove wire:target="startImport">
                        @if(count($importTenantIds) > 1)
                            In {{ count($importTenantIds) }} Tenants importieren
                        @else
                            Importieren
                        @endif
                    </span>
                    <span wire:loading wire:target="startImport">
                        Import läuft...
                    </span>
                </x-filament::button>

                @if($importData)
                    <x-filament::button
                        wire:click="resetImport"
                        color="gray"
                        outlined
                    >
                        Zurücksetzen
                    </x-filament::button>
                @endif
            </div>

            {{-- Bestätigungsdialog für Ersetzen --}}
            @if($showReplaceConfirmation)
                <div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-4 border-2 border-danger-300 dark:border-danger-700">
                    <div class="flex items-start gap-3">
                        <x-heroicon-s-exclamation-triangle class="h-6 w-6 text-danger-500 shrink-0 mt-0.5" />
                        <div class="space-y-3">
                            <div>
                                <h4 class="text-sm font-bold text-danger-800 dark:text-danger-200">
                                    Achtung: Ersetzen-Modus
                                </h4>
                                <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">
                                    @if($previewData && ($previewData['existingSlotCount'] ?? 0) > 0)
                                        <strong>{{ $previewData['existingSlotCount'] }}</strong> bestehende Ad-Slot(s) in
                                    @else
                                        Alle bestehenden Ad-Slots in
                                    @endif
                                    @if(count($importTenantIds) === 1 && $previewData)
                                        „{{ $previewData['tenantName'] }}"
                                    @elseif(count($importTenantIds) > 1)
                                        den {{ count($importTenantIds) }} gewählten Tenants
                                    @else
                                        dem gewählten Tenant
                                    @endif
                                    werden <strong>unwiderruflich gelöscht</strong> und durch {{ $previewData['slotCount'] ?? 'die importierten' }} Slot(s) ersetzt.
                                </p>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-xs font-medium text-danger-700 dark:text-danger-300 mb-1">
                                        Tippe <strong>ERSETZEN</strong> zur Bestätigung:
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="replaceConfirmText"
                                        placeholder="ERSETZEN"
                                        class="block w-48 rounded-md border-danger-300 text-sm shadow-sm focus:border-danger-500 focus:ring-danger-500 dark:border-danger-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                                <div class="flex gap-2">
                                    <x-filament::button
                                        wire:click="confirmReplace"
                                        color="danger"
                                        size="sm"
                                        :disabled="$replaceConfirmText !== 'ERSETZEN'"
                                    >
                                        Ja, ersetzen
                                    </x-filament::button>
                                    <x-filament::button
                                        wire:click="cancelReplace"
                                        color="gray"
                                        size="sm"
                                        outlined
                                    >
                                        Abbrechen
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Import-Ergebnis --}}
            @if($importResultData)
                <div class="rounded-lg bg-success-50 dark:bg-success-950 p-4 border border-success-200 dark:border-success-800">
                    <h4 class="text-sm font-bold text-success-800 dark:text-success-200 mb-2">
                        Import-Ergebnis für „{{ $importResultData['tenantName'] }}"
                    </h4>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-success-600 dark:text-success-400">Importiert:</span>
                            <span class="ml-1 font-bold">{{ $importResultData['imported'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Übersprungen:</span>
                            <span class="ml-1 font-bold">{{ $importResultData['skipped'] }}</span>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400">Aktualisiert:</span>
                            <span class="ml-1 font-bold">{{ $importResultData['updated'] }}</span>
                        </div>
                    </div>
                    @if(!empty($importResultData['errors']))
                        <div class="mt-2 text-sm text-danger-700 dark:text-danger-300">
                            <strong>Fehler:</strong>
                            <ul class="list-disc list-inside mt-1">
                                @foreach($importResultData['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</div>
