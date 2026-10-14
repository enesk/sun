<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Jobs\ProcessTenantImportJob;
use App\Jobs\ProcessTenantPreviewJob;
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

    /**
     * Obergrenze des Uploads in Kilobyte (~95 MB).
     *
     * Muss unter post_max_size (100M, siehe docker-php.ini-Dateien) und unter der
     * 100-MB-Grenze von Cloudflare Free bleiben — sonst bricht die Kante mit
     * 413 ab, bevor PHP ueberhaupt validieren kann.
     */
    public const MAX_UPLOAD_KILOBYTES = 95000;

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

    public bool $previewRunning = false;

    public ?string $previewMessage = null;

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
            // 95 MB: unter post_max_size (100M) und unter der 100-MB-Grenze der
            // Cloudflare-Kante, damit der Nutzer eine Validierungsmeldung statt 413 sieht.
            'sqlFile' => ['file', 'max:'.self::MAX_UPLOAD_KILOBYTES, 'extensions:sql,gz'],
        ], [
            'sqlFile.max' => 'Die Datei ist zu gross (max. 95 MB). Grössere Dumps bitte per SSH direkt auf dem Ursprung-Server einspielen, nicht durch die Cloudflare-Kante.',
        ]);

        try {
            // Datei SOFORT dauerhaft speichern — Livewire-Temp-Dateien werden zu schnell bereinigt
            $extension = $this->sqlFile->getClientOriginalExtension() ?: 'sql';
            $fileName = uniqid('import_', true).'.'.$extension;
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
                ->body('Gefundene Tabellen: '.implode(', ', $validation->foundTables))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->validationErrors = ['Fehler bei der Validierung: '.$e->getMessage()];
        }
    }

    /**
     * Vorschau anstossen: SQL-Dump wird im Hintergrund-Job in eine Temp-DB
     * eingespielt und analysiert. Synchron riss das bei grossen Dumps die
     * 100-s-Grenze der Cloudflare-Kante (524).
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

        Cache::forget(ProcessTenantPreviewJob::getCacheKey($tenant->id));

        ProcessTenantPreviewJob::dispatch($tenant, $this->storedFilePath, $this->sourceTenantId);

        $this->validationErrors = null;
        $this->previewData = null;
        $this->previewRunning = true;
        $this->previewMessage = 'Vorschau-Job gestartet...';

        Notification::make()
            ->title('Vorschau wird im Hintergrund erstellt')
            ->body('Grosse Dumps brauchen einige Minuten. Die Seite aktualisiert sich selbst.')
            ->success()
            ->send();
    }

    /**
     * Fortschritt der laufenden Vorschau abfragen (Polling).
     */
    public function pollPreview(): void
    {
        if (! $this->previewRunning || ! $this->targetTenantId) {
            return;
        }

        $state = Cache::get(ProcessTenantPreviewJob::getCacheKey($this->targetTenantId));

        if (! $state) {
            return;
        }

        $this->previewMessage = $state['message'] ?? null;

        if ($state['status'] === 'completed') {
            $this->previewRunning = false;
            $preview = $state['preview'] ?? [];
            $this->availableTenantIds = $preview['availableTenantIds'] ?? [];
            $this->previewData = $preview;

            Notification::make()->title('Vorschau fertig')->success()->send();

            return;
        }

        if ($state['status'] === 'failed') {
            $this->previewRunning = false;
            $this->validationErrors = [$state['message'] ?? 'Unbekannter Fehler bei der Vorschau.'];
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
        $this->previewRunning = false;
        $this->previewMessage = null;
        $this->availableTenantIds = [];
        $this->sourceTenantId = 0;
        $this->storedFilePath = null;
    }
}
