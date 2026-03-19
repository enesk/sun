<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Jobs\ProcessTenantImportJob;
use App\Models\Tenant;
use App\Services\TenantImport\SqlDumpProcessor;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class TenantImport extends Component
{
    use WithFileUploads;

    // Upload & Validation
    public $sqlFile = null;
    public ?array $validationErrors = null;
    public ?array $validationWarnings = null;
    public bool $fileValidated = false;

    // Tenant & Source Selection
    public ?string $targetTenantId = null;
    public int $sourceTenantId = 0;
    public array $availableTenantIds = [];

    // Preview
    public ?array $previewData = null;

    // Import Options
    public bool $skipReviews = false;
    public bool $skipPhotos = false;
    public bool $forceOverwrite = false;

    // Progress
    public bool $importRunning = false;
    public ?array $progressData = null;
    public ?array $importResult = null;

    // Internal — public damit Livewire es zwischen Requests persistiert
    public ?string $storedFilePath = null;

    public function render()
    {
        return view('livewire.filament.tenant-import', [
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id')->toArray(),
        ]);
    }

    /**
     * SQL-Datei hochgeladen: validieren.
     */
    public function updatedSqlFile(): void
    {
        $this->resetState();

        if (! $this->sqlFile) {
            return;
        }

        $this->validate([
            'sqlFile' => ['file', 'max:262144', 'extensions:sql,gz'], // 256 MB
        ]);

        try {
            // Datei SOFORT dauerhaft speichern — Livewire-Temp-Dateien werden zu schnell bereinigt
            $extension = $this->sqlFile->getClientOriginalExtension() ?: 'sql';
            $fileName = uniqid('import_', true) . '.' . $extension;
            $storedPath = $this->sqlFile->storeAs(
                'tenant-imports',
                $fileName,
                'livewire-tmp',
            );

            if (! $storedPath) {
                $this->validationErrors = ['Datei konnte nicht gespeichert werden.'];
                return;
            }

            $this->storedFilePath = Storage::disk('livewire-tmp')->path($storedPath);

            $processor = app(SqlDumpProcessor::class);
            $validation = $processor->validate($this->storedFilePath);

            if (! $validation->valid) {
                $this->validationErrors = $validation->errors;
                // Datei aufräumen bei ungültigem Dump
                @unlink($this->storedFilePath);
                $this->storedFilePath = null;
                return;
            }

            $this->validationWarnings = $validation->warnings;
            $this->fileValidated = true;

            Notification::make()
                ->title('SQL-Dump validiert')
                ->body('Gefundene Tabellen: ' . implode(', ', $validation->foundTables))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->validationErrors = ['Fehler bei der Validierung: ' . $e->getMessage()];
        }
    }

    /**
     * Vorschau laden: SQL-Dump in Temp-DB importieren und analysieren.
     */
    public function loadPreview(): void
    {
        if (! $this->fileValidated || ! $this->targetTenantId) {
            Notification::make()
                ->title('Bitte SQL-Datei hochladen und Ziel-Tenant wählen.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->storedFilePath || ! file_exists($this->storedFilePath)) {
            $this->validationErrors = ['SQL-Datei nicht mehr verfügbar. Bitte erneut hochladen.'];
            $this->fileValidated = false;
            return;
        }

        $tenant = Tenant::find($this->targetTenantId);
        if (! $tenant) {
            Notification::make()->title('Tenant nicht gefunden.')->danger()->send();
            return;
        }

        try {
            $processor = app(SqlDumpProcessor::class);

            // Dump verarbeiten und Temp-DB erstellen
            $tempDbInfo = $processor->process($this->storedFilePath);

            // Verfügbare Tenant-IDs laden
            $this->availableTenantIds = $processor->getAvailableTenantIds();

            // Dry-Run für Vorschau
            $importService = app(\App\Services\TenantImport\TenantImportService::class);

            // Tenant-Context für Duplikaterkennung
            tenancy()->initialize($tenant);

            // Connection nach Tenancy-Init erneut sicherstellen (Tenancy kann Config zurücksetzen)
            SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);

            $preview = $importService->dryRun($tenant, $tempDbInfo->connectionName, $this->sourceTenantId);
            tenancy()->end();

            $this->previewData = [
                'placesCount' => $preview->placesCount,
                'categoriesCount' => $preview->categoriesCount,
                'reviewsCount' => $preview->reviewsCount,
                'photosCount' => $preview->photosCount,
                'openingHoursCount' => $preview->openingHoursCount,
                'duplicatesCount' => $preview->duplicatesCount,
                'expectedNewCompanies' => $preview->expectedNewCompanies,
                'missingCategories' => $preview->missingCategories,
                'availableTenantIds' => $preview->availableTenantIds,
            ];

            // Nur Temp-DB aufräumen, Upload-Datei bleibt für den Import erhalten
            $processor->cleanupDatabase();

        } catch (\Throwable $e) {
            $this->validationErrors = ['Fehler bei der Vorschau: ' . $e->getMessage()];
            try { tenancy()->end(); } catch (\Throwable) {}
        }
    }

    /**
     * Import starten (Queue-Job).
     */
    public function startImport(): void
    {
        if (! $this->fileValidated || ! $this->targetTenantId) {
            Notification::make()
                ->title('Bitte SQL-Datei und Ziel-Tenant wählen.')
                ->danger()
                ->send();
            return;
        }

        $tenant = Tenant::find($this->targetTenantId);
        if (! $tenant) {
            Notification::make()->title('Tenant nicht gefunden.')->danger()->send();
            return;
        }

        if (! $this->storedFilePath || ! file_exists($this->storedFilePath)) {
            $this->validationErrors = ['SQL-Datei nicht mehr verfügbar. Bitte erneut hochladen.'];
            $this->fileValidated = false;
            return;
        }

        // Queue-Job dispatchen
        ProcessTenantImportJob::dispatch(
            $tenant,
            $this->storedFilePath,
            $this->sourceTenantId,
            [
                'force' => $this->forceOverwrite,
                'skip_reviews' => $this->skipReviews,
                'skip_photos' => $this->skipPhotos,
            ],
        );

        $this->importRunning = true;

        Notification::make()
            ->title('Import-Job gestartet')
            ->body("Der Import für Tenant \"{$tenant->name}\" läuft im Hintergrund.")
            ->success()
            ->send();
    }

    /**
     * Fortschritt des laufenden Imports abfragen (Polling).
     */
    public function pollProgress(): void
    {
        if (! $this->importRunning || ! $this->targetTenantId) {
            return;
        }

        $cacheKey = "tenant_import_progress_{$this->targetTenantId}";
        $progress = Cache::get($cacheKey);

        if (! $progress) {
            return;
        }

        $this->progressData = $progress;

        if (in_array($progress['status'], ['completed', 'failed'])) {
            $this->importRunning = false;

            if ($progress['status'] === 'completed') {
                $this->importResult = $progress['result'] ?? [];
                Notification::make()
                    ->title('Import abgeschlossen')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Import fehlgeschlagen')
                    ->body($progress['message'] ?? 'Unbekannter Fehler')
                    ->danger()
                    ->send();
            }
        }
    }

    /**
     * Alles zurücksetzen.
     */
    public function resetImport(): void
    {
        $this->sqlFile = null;
        $this->resetState();
        $this->importRunning = false;
        $this->progressData = null;
        $this->importResult = null;
    }

    private function resetState(): void
    {
        // Alte Datei aufräumen wenn vorhanden
        if ($this->storedFilePath && file_exists($this->storedFilePath)) {
            @unlink($this->storedFilePath);
        }

        $this->validationErrors = null;
        $this->validationWarnings = null;
        $this->fileValidated = false;
        $this->previewData = null;
        $this->availableTenantIds = [];
        $this->sourceTenantId = 0;
        $this->storedFilePath = null;
    }
}
