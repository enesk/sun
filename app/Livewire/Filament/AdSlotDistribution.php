<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\DTOs\ImportPreview;
use App\DTOs\ImportResult;
use App\Jobs\BulkAdSlotImportJob;
use App\Models\Tenant;
use App\Services\AdSlotExportService;
use App\Services\AdSlotImportService;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;

class AdSlotDistribution extends Component
{
    use WithFileUploads;

    // Export
    public ?string $exportTenantId = null;
    public ?int $exportSlotCount = null;

    // Import
    public $importFile = null;
    public ?array $importData = null;
    public ?array $validationErrors = null;
    public ?array $previewData = null;
    public array $importTenantIds = [];
    public string $importMode = 'add';
    public string $conflictStrategy = 'skip';
    public ?array $importResultData = null;
    public bool $showReplaceConfirmation = false;
    public string $replaceConfirmText = '';

    public function render()
    {
        return view('livewire.filament.ad-slot-distribution', [
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id')->toArray(),
        ]);
    }

    /**
     * Wenn ein Export-Tenant gewählt wird: Slot-Anzahl laden.
     */
    public function updatedExportTenantId(?string $value): void
    {
        $this->exportSlotCount = null;

        if (! $value) {
            return;
        }

        $tenant = Tenant::find($value);
        if (! $tenant) {
            return;
        }

        $exportService = app(AdSlotExportService::class);
        $data = $exportService->export($tenant);
        $this->exportSlotCount = $data['meta']['slot_count'];
    }

    /**
     * Export: JSON-Datei herunterladen.
     */
    public function export()
    {
        if (! $this->exportTenantId) {
            Notification::make()
                ->title('Bitte wähle einen Quell-Tenant.')
                ->danger()
                ->send();

            return;
        }

        $tenant = Tenant::find($this->exportTenantId);
        if (! $tenant) {
            Notification::make()
                ->title('Tenant nicht gefunden.')
                ->danger()
                ->send();

            return;
        }

        $exportService = app(AdSlotExportService::class);
        $json = $exportService->exportToJson($tenant);

        $filename = 'ad-slots-export-' . str($tenant->name)->slug() . '-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Wenn eine Import-Datei hochgeladen wird: validieren und Daten parsen.
     */
    public function updatedImportFile(): void
    {
        $this->resetImportState();

        if (! $this->importFile) {
            return;
        }

        $this->validate([
            'importFile' => ['file', 'max:5120', 'extensions:json'],
        ]);

        try {
            $content = file_get_contents($this->importFile->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->validationErrors = ['Die Datei enthält kein gültiges JSON: ' . json_last_error_msg()];

                return;
            }

            $importService = app(AdSlotImportService::class);
            $validation = $importService->validate($data);

            if (! $validation->valid) {
                $this->validationErrors = $validation->errors;

                return;
            }

            $this->importData = $data;
        } catch (\Throwable $e) {
            $this->validationErrors = ['Fehler beim Lesen der Datei: ' . $e->getMessage()];
        }
    }

    /**
     * Vorschau aktualisieren wenn sich Ziel-Tenant oder Daten ändern.
     */
    public function updatedImportTenantIds(): void
    {
        $this->updatePreview();
    }

    public function updatedImportMode(): void
    {
        $this->showReplaceConfirmation = false;
    }

    public function updatePreview(): void
    {
        $this->previewData = null;

        if (! $this->importData || empty($this->importTenantIds)) {
            return;
        }

        // Vorschau für den ersten gewählten Tenant
        $firstTenantId = $this->importTenantIds[0] ?? null;
        if (! $firstTenantId) {
            return;
        }

        $tenant = Tenant::find($firstTenantId);
        if (! $tenant) {
            return;
        }

        $importService = app(AdSlotImportService::class);
        $preview = $importService->preview($this->importData, $tenant);

        $this->previewData = [
            'slotCount' => $preview->slotCount,
            'positions' => $preview->positions,
            'conflicts' => $preview->conflicts,
            'newCount' => $preview->newCount,
            'conflictCount' => $preview->conflictCount,
            'existingSlotCount' => $preview->existingSlotCount,
            'tenantName' => $tenant->name,
        ];
    }

    /**
     * Import starten — ggf. Bestätigung für Ersetzen-Modus.
     */
    public function startImport(): void
    {
        if (! $this->importData) {
            Notification::make()
                ->title('Bitte lade zuerst eine gültige JSON-Datei hoch.')
                ->danger()
                ->send();

            return;
        }

        if (empty($this->importTenantIds)) {
            Notification::make()
                ->title('Bitte wähle mindestens einen Ziel-Tenant.')
                ->danger()
                ->send();

            return;
        }

        if ($this->importMode === 'replace') {
            $this->showReplaceConfirmation = true;

            return;
        }

        $this->executeImport();
    }

    /**
     * Bestätigung für Ersetzen-Modus.
     */
    public function confirmReplace(): void
    {
        if ($this->replaceConfirmText !== 'ERSETZEN') {
            return;
        }

        $this->showReplaceConfirmation = false;
        $this->replaceConfirmText = '';
        $this->executeImport();
    }

    public function cancelReplace(): void
    {
        $this->showReplaceConfirmation = false;
        $this->replaceConfirmText = '';
    }

    /**
     * Import ausführen — direkt oder über Queue.
     */
    private function executeImport(): void
    {
        $tenants = Tenant::whereIn('id', $this->importTenantIds)->get();

        if ($tenants->isEmpty()) {
            Notification::make()
                ->title('Keine gültigen Tenants gefunden.')
                ->danger()
                ->send();

            return;
        }

        // Bei mehreren Tenants: Queue-Jobs dispatchen
        if ($tenants->count() > 1) {
            foreach ($tenants as $tenant) {
                BulkAdSlotImportJob::dispatch(
                    $this->importData,
                    $tenant,
                    $this->importMode,
                    $this->conflictStrategy,
                );
            }

            Notification::make()
                ->title($tenants->count() . ' Import-Jobs in die Queue gestellt')
                ->body('Die Imports werden asynchron im Hintergrund verarbeitet. Ergebnisse werden im Log protokolliert.')
                ->success()
                ->send();

            $this->resetImportState();

            return;
        }

        // Einzel-Tenant: direkt importieren
        $tenant = $tenants->first();
        $importService = app(AdSlotImportService::class);

        try {
            $result = $importService->import(
                $this->importData,
                $tenant,
                $this->importMode,
                $this->conflictStrategy,
            );

            $this->importResultData = [
                'imported' => $result->imported,
                'skipped' => $result->skipped,
                'updated' => $result->updated,
                'errors' => $result->errors,
                'tenantName' => $tenant->name,
            ];

            Notification::make()
                ->title('Import erfolgreich')
                ->body("Tenant: {$tenant->name} — {$result->imported} importiert, {$result->skipped} übersprungen, {$result->updated} aktualisiert")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Import fehlgeschlagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Import-Bereich zurücksetzen.
     */
    public function resetImport(): void
    {
        $this->importFile = null;
        $this->resetImportState();
    }

    private function resetImportState(): void
    {
        $this->importData = null;
        $this->validationErrors = null;
        $this->previewData = null;
        $this->importResultData = null;
        $this->showReplaceConfirmation = false;
        $this->replaceConfirmText = '';
    }
}
