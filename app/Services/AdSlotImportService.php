<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ImportPreview;
use App\DTOs\ImportResult;
use App\DTOs\ValidationResult;
use App\Models\Portal\AdSlot;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class AdSlotImportService
{
    /**
     * Validiert die Import-Daten gegen das erwartete Schema.
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        if (! isset($data['meta']['schema_version'])) {
            $errors[] = 'meta.schema_version fehlt.';
        }

        if (! isset($data['slots']) || ! is_array($data['slots'])) {
            $errors[] = 'slots muss ein Array sein.';

            return ValidationResult::failed($errors);
        }

        $validPositions = array_keys(config('ad-positions', []));
        $validDevices = ['desktop', 'tablet', 'mobile'];

        foreach ($data['slots'] as $index => $slot) {
            $prefix = "slots[$index]";

            if (empty($slot['name'])) {
                $errors[] = "$prefix: name ist ein Pflichtfeld.";
            }

            if (empty($slot['position'])) {
                $errors[] = "$prefix: position ist ein Pflichtfeld.";
            } elseif (! in_array($slot['position'], $validPositions, true)) {
                $errors[] = "$prefix: Unbekannte Position '{$slot['position']}'. Erlaubt: " . implode(', ', $validPositions);
            }

            if (isset($slot['device_visibility'])) {
                if (! is_array($slot['device_visibility'])) {
                    $errors[] = "$prefix: device_visibility muss ein Array sein.";
                } else {
                    $invalid = array_diff($slot['device_visibility'], $validDevices);
                    if (! empty($invalid)) {
                        $errors[] = "$prefix: Ungültige device_visibility-Werte: " . implode(', ', $invalid);
                    }
                }
            }
        }

        return empty($errors)
            ? ValidationResult::ok()
            : ValidationResult::failed($errors);
    }

    /**
     * Erstellt eine Vorschau des Imports mit Konflikterkennung.
     */
    public function preview(array $data, Tenant $tenant): ImportPreview
    {
        $slots = $data['slots'] ?? [];
        $positionLabels = config('ad-positions', []);

        $positions = collect($slots)
            ->pluck('position')
            ->unique()
            ->map(fn (string $pos) => [
                'key' => $pos,
                'label' => $positionLabels[$pos] ?? $pos,
            ])
            ->values()
            ->all();

        $existingSlots = $tenant->run(function () {
            return AdSlot::all(['name', 'position'])->toArray();
        });

        $existingSlotCount = count($existingSlots);

        $existingIndex = [];
        foreach ($existingSlots as $existing) {
            $key = $existing['position'] . '::' . $existing['name'];
            $existingIndex[$key] = true;
        }

        $conflicts = [];
        $newCount = 0;

        foreach ($slots as $slot) {
            $key = $slot['position'] . '::' . $slot['name'];
            if (isset($existingIndex[$key])) {
                $conflicts[] = [
                    'name' => $slot['name'],
                    'position' => $slot['position'],
                    'position_label' => $positionLabels[$slot['position']] ?? $slot['position'],
                ];
            } else {
                $newCount++;
            }
        }

        return new ImportPreview(
            slotCount: count($slots),
            positions: $positions,
            conflicts: $conflicts,
            newCount: $newCount,
            conflictCount: count($conflicts),
            existingSlotCount: $existingSlotCount,
        );
    }

    /**
     * Importiert Ad-Slots in einen Ziel-Tenant.
     *
     * @param  string  $mode  'add' oder 'replace'
     * @param  string  $conflictStrategy  'skip' oder 'update' (nur bei mode=add)
     */
    public function import(array $data, Tenant $tenant, string $mode = 'add', string $conflictStrategy = 'skip'): ImportResult
    {
        $slots = $data['slots'] ?? [];

        return $tenant->run(function () use ($slots, $mode, $conflictStrategy) {
            return DB::connection('tenant')->transaction(function () use ($slots, $mode, $conflictStrategy) {
                if ($mode === 'replace') {
                    AdSlot::query()->delete();

                    return $this->insertAll($slots);
                }

                return $this->importAdd($slots, $conflictStrategy);
            });
        });
    }

    private function insertAll(array $slots): ImportResult
    {
        $imported = 0;
        $errors = [];

        foreach ($slots as $index => $slotData) {
            try {
                AdSlot::create($this->mapSlotData($slotData));
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "slots[$index] ({$slotData['name']}): {$e->getMessage()}";
            }
        }

        if (! empty($errors)) {
            throw new \RuntimeException('Import fehlgeschlagen: ' . implode('; ', $errors));
        }

        return new ImportResult(imported: $imported);
    }

    private function importAdd(array $slots, string $conflictStrategy): ImportResult
    {
        $existingSlots = AdSlot::all(['id', 'name', 'position']);
        $existingIndex = [];
        foreach ($existingSlots as $existing) {
            $key = $existing->position . '::' . $existing->name;
            $existingIndex[$key] = $existing->id;
        }

        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $errors = [];

        foreach ($slots as $index => $slotData) {
            $key = $slotData['position'] . '::' . $slotData['name'];

            try {
                if (isset($existingIndex[$key])) {
                    if ($conflictStrategy === 'update') {
                        AdSlot::where('id', $existingIndex[$key])
                            ->update($this->mapSlotData($slotData));
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    AdSlot::create($this->mapSlotData($slotData));
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = "slots[$index] ({$slotData['name']}): {$e->getMessage()}";
            }
        }

        if (! empty($errors)) {
            throw new \RuntimeException('Import fehlgeschlagen: ' . implode('; ', $errors));
        }

        return new ImportResult(
            imported: $imported,
            skipped: $skipped,
            updated: $updated,
        );
    }

    private function mapSlotData(array $slotData): array
    {
        return [
            'name' => $slotData['name'],
            'position' => $slotData['position'],
            'code' => $slotData['code'] ?? null,
            'is_active' => $slotData['is_active'] ?? false,
            'sort_order' => $slotData['sort_order'] ?? 0,
            'device_visibility' => $slotData['device_visibility'] ?? ['desktop', 'tablet', 'mobile'],
        ];
    }
}
