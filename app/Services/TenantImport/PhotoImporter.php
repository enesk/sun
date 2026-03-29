<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\Models\Portal\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoImporter
{
    private bool $columnsLogged = false;

    /**
     * Importiert Fotos eines Places in die Company.
     *
     * Erwartet die Foto-Dateien im Tenant-Storage unter photos/
     * z.B. storage/tenant{ID}/app/public/photos/690d324a7c44d.jpg
     *
     * Spatie Media Library verschiebt sie automatisch nach media/{id}/
     *
     * @return int Anzahl importierter Fotos
     */
    public function import(int $oldPlaceId, Company $company, string $tempConnection): int
    {
        $rows = DB::connection($tempConnection)
            ->table('place_photos')
            ->where('place_id', $oldPlaceId)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        // Einmalig die Spalten loggen für Debugging
        if (!$this->columnsLogged && $rows->isNotEmpty()) {
            $columns = array_keys((array) $rows->first());
            Log::info("TenantImport Photo: place_photos Spalten", ['columns' => $columns]);
            $this->columnsLogged = true;
        }

        $imported = 0;
        $isFirst = true;

        // Foto-Quellverzeichnis im Tenant-Storage
        $photosBasePath = Storage::disk('public')->path('photos');

        foreach ($rows as $row) {
            // Flexibel: name, path, filename — je nach Dump-Schema
            $fileName = $row->name ?? $row->path ?? $row->filename ?? null;

            if (!$fileName) {
                Log::info("TenantImport Photo: Kein Dateiname für Place #{$oldPlaceId}", [
                    'row' => (array) $row,
                ]);
                continue;
            }

            // Falls path einen Verzeichnispfad enthält, nur den Dateinamen extrahieren
            $fileName = basename($fileName);

            $filePath = $photosBasePath . '/' . $fileName;

            if (!file_exists($filePath)) {
                Log::info("TenantImport Photo: Datei nicht gefunden, übersprungen", [
                    'company_id' => $company->id,
                    'file' => $fileName,
                    'expected_path' => $filePath,
                ]);
                continue;
            }

            $collection = $isFirst ? 'logo' : 'gallery';

            try {
                $company->addMedia($filePath)
                    ->preservingOriginal()
                    ->toMediaCollection($collection);

                Log::info("TenantImport Photo: importiert für Company #{$company->id}", [
                    'collection' => $collection,
                    'file' => $fileName,
                ]);

                $isFirst = false;
                $imported++;
            } catch (\Exception $e) {
                Log::warning("TenantImport Photo: Fehler bei Place #{$oldPlaceId}", [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }
}
