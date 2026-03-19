<div class="space-y-6">

    {{-- ==================== UPLOAD & VALIDIERUNG ==================== --}}
    <x-filament::section>
        <x-slot name="heading">SQL-Dump Upload</x-slot>
        <x-slot name="description">SQL-Dump-Datei (.sql oder .sql.gz) des Alt-Systems hochladen und validieren</x-slot>

        <div class="space-y-4">
            {{-- Datei-Upload --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    SQL-Datei (max. 256 MB)
                </label>
                <input
                    type="file"
                    wire:model="sqlFile"
                    accept=".sql,.gz"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-gray-700 dark:file:text-gray-300"
                    @if($importRunning) disabled @endif
                />
                <div wire:loading wire:target="sqlFile" class="mt-2 text-sm text-gray-500">
                    Datei wird hochgeladen...
                </div>
            </div>

            {{-- Validierungsfehler --}}
            @if($validationErrors)
                <div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-4 border border-danger-200 dark:border-danger-800">
                    <div class="flex">
                        <x-heroicon-s-exclamation-triangle class="h-5 w-5 text-danger-400 shrink-0" />
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-danger-800 dark:text-danger-200">Validierungsfehler</h3>
                            <ul class="mt-2 list-disc list-inside text-sm text-danger-700 dark:text-danger-300 space-y-1">
                                @foreach($validationErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Validierungswarnungen --}}
            @if($validationWarnings)
                <div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-200 dark:border-warning-800">
                    <div class="flex">
                        <x-heroicon-s-exclamation-triangle class="h-5 w-5 text-warning-400 shrink-0" />
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">Hinweise</h3>
                            <ul class="mt-2 list-disc list-inside text-sm text-warning-700 dark:text-warning-300 space-y-1">
                                @foreach($validationWarnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Datei validiert --}}
            @if($fileValidated)
                <div class="rounded-lg bg-success-50 dark:bg-success-950 p-4 border border-success-200 dark:border-success-800">
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="h-5 w-5 text-success-500 shrink-0" />
                        <span class="text-sm font-medium text-success-800 dark:text-success-200">
                            SQL-Dump erfolgreich validiert
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- ==================== KONFIGURATION & VORSCHAU ==================== --}}
    @if($fileValidated)
        <x-filament::section>
            <x-slot name="heading">Import-Konfiguration</x-slot>
            <x-slot name="description">Ziel-Tenant wählen und Import-Optionen konfigurieren</x-slot>

            <div class="space-y-6">
                {{-- Ziel-Tenant --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="targetTenant" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Ziel-Tenant
                        </label>
                        <select
                            id="targetTenant"
                            wire:model.live="targetTenantId"
                            class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            @if($importRunning) disabled @endif
                        >
                            <option value="">— Tenant wählen —</option>
                            @foreach($tenants as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Source Tenant ID --}}
                    @if(!empty($availableTenantIds))
                        <div>
                            <label for="sourceTenant" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quell-Tenant-ID (aus SQL-Dump)
                            </label>
                            <select
                                id="sourceTenant"
                                wire:model.live="sourceTenantId"
                                class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @if($importRunning) disabled @endif
                            >
                                <option value="0">Alle Tenants</option>
                                @foreach($availableTenantIds as $tid)
                                    <option value="{{ $tid }}">Tenant-ID: {{ $tid }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Falls der Dump mehrere Tenants enthält, kann hier gefiltert werden.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Import-Optionen --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Optionen
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="forceOverwrite"
                                class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-600"
                                @if($importRunning) disabled @endif />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <strong>Duplikate überschreiben</strong> — vorhandene Firmen (gleiche Google Places ID) werden aktualisiert
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="skipReviews"
                                class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-600"
                                @if($importRunning) disabled @endif />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <strong>Reviews überspringen</strong>
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="skipPhotos"
                                class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-600"
                                @if($importRunning) disabled @endif />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <strong>Fotos überspringen</strong>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Vorschau-Button --}}
                <div class="flex items-center gap-4">
                    <x-filament::button
                        wire:click="loadPreview"
                        wire:loading.attr="disabled"
                        wire:target="loadPreview"
                        :disabled="!$targetTenantId || $importRunning"
                        color="gray"
                        icon="heroicon-o-eye"
                    >
                        <span wire:loading.remove wire:target="loadPreview">Vorschau laden</span>
                        <span wire:loading wire:target="loadPreview">Vorschau wird geladen...</span>
                    </x-filament::button>
                </div>

                {{-- Vorschau-Ergebnis --}}
                @if($previewData)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                Import-Vorschau
                            </h4>
                        </div>
                        <div class="p-4 space-y-4">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Firmen:</span>
                                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['placesCount'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Neue Firmen:</span>
                                    <span class="ml-1 font-semibold text-success-600 dark:text-success-400">{{ $previewData['expectedNewCompanies'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Duplikate:</span>
                                    <span class="ml-1 font-semibold {{ $previewData['duplicatesCount'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">
                                        {{ $previewData['duplicatesCount'] }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Kategorien:</span>
                                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['categoriesCount'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Öffnungszeiten:</span>
                                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['openingHoursCount'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Reviews:</span>
                                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['reviewsCount'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Fotos:</span>
                                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $previewData['photosCount'] }}</span>
                                </div>
                            </div>

                            {{-- Fehlende Kategorien --}}
                            @if(!empty($previewData['missingCategories']))
                                <div class="rounded-md bg-warning-50 dark:bg-warning-950 p-3 border border-warning-200 dark:border-warning-800">
                                    <div class="flex items-start gap-2">
                                        <x-heroicon-s-exclamation-triangle class="h-4 w-4 text-warning-500 shrink-0 mt-0.5" />
                                        <div>
                                            <span class="text-sm font-medium text-warning-800 dark:text-warning-200">
                                                {{ count($previewData['missingCategories']) }} Kategorie(n) ohne Zuordnung (werden als "Sonstiges" importiert):
                                            </span>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach($previewData['missingCategories'] as $cat)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">
                                                        {{ $cat }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- ==================== IMPORT STARTEN ==================== --}}
    @if($previewData && !$importRunning && !$importResult)
        <x-filament::section>
            <x-slot name="heading">Import starten</x-slot>
            <x-slot name="description">Der Import wird als Queue-Job im Hintergrund ausgeführt</x-slot>

            <div class="space-y-4">
                @if($forceOverwrite && $previewData['duplicatesCount'] > 0)
                    <div class="rounded-md bg-danger-50 dark:bg-danger-950 p-3 border border-danger-200 dark:border-danger-800">
                        <div class="flex items-center gap-2">
                            <x-heroicon-s-exclamation-triangle class="h-4 w-4 text-danger-500 shrink-0" />
                            <span class="text-sm font-medium text-danger-800 dark:text-danger-200">
                                {{ $previewData['duplicatesCount'] }} bestehende Firma(en) werden überschrieben!
                            </span>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-4">
                    <x-filament::button
                        wire:click="startImport"
                        wire:loading.attr="disabled"
                        wire:target="startImport"
                        :color="$forceOverwrite ? 'danger' : 'primary'"
                        icon="heroicon-o-arrow-up-tray"
                    >
                        <span wire:loading.remove wire:target="startImport">Import starten</span>
                        <span wire:loading wire:target="startImport">Wird gestartet...</span>
                    </x-filament::button>

                    <x-filament::button
                        wire:click="resetImport"
                        color="gray"
                        outlined
                    >
                        Zurücksetzen
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- ==================== FORTSCHRITT ==================== --}}
    @if($importRunning)
        <x-filament::section wire:poll.2s="pollProgress">
            <x-slot name="heading">Import läuft...</x-slot>

            <div class="space-y-4">
                @if($progressData)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700 dark:text-gray-300">{{ $progressData['message'] ?? 'Verarbeite...' }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $progressData['percent'] ?? 0 }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div
                                class="bg-primary-600 h-3 rounded-full transition-all duration-500"
                                style="width: {{ $progressData['percent'] ?? 0 }}%"
                            ></div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3 text-sm text-gray-500">
                        <svg class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Warte auf Import-Job...
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- ==================== ERGEBNIS ==================== --}}
    @if($importResult)
        <x-filament::section>
            <x-slot name="heading">Import-Ergebnis</x-slot>

            <div class="space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-success-600 dark:text-success-400">Importiert:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['companiesImported'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Übersprungen:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['companiesSkipped'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-danger-600 dark:text-danger-400">Fehlgeschlagen:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['companiesFailed'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-blue-600 dark:text-blue-400">Kategorien:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['categoriesMapped'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Öffnungszeiten:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['openingHoursImported'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Reviews:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['reviewsImported'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Fotos:</span>
                        <span class="ml-1 font-bold text-gray-900 dark:text-white">{{ $importResult['photosImported'] ?? 0 }}</span>
                    </div>
                </div>

                @if(!empty($importResult['errors']))
                    <div class="rounded-md bg-danger-50 dark:bg-danger-950 p-3 border border-danger-200 dark:border-danger-800">
                        <h4 class="text-sm font-medium text-danger-800 dark:text-danger-200 mb-2">Fehler:</h4>
                        <ul class="list-disc list-inside text-sm text-danger-700 dark:text-danger-300 space-y-1 max-h-40 overflow-y-auto">
                            @foreach(array_slice($importResult['errors'], 0, 50) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @if(count($importResult['errors']) > 50)
                                <li class="font-medium">... und {{ count($importResult['errors']) - 50 }} weitere Fehler (siehe Log)</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div>
                    <x-filament::button
                        wire:click="resetImport"
                        color="gray"
                        outlined
                        icon="heroicon-o-arrow-path"
                    >
                        Neuen Import starten
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</div>
