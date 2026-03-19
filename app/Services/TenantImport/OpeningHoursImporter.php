<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\Models\Portal\CompanyOpeningHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpeningHoursImporter
{
    /**
     * Importiert Öffnungszeiten eines Places in die Company.
     *
     * @return int Anzahl importierter Einträge
     */
    public function import(int $oldPlaceId, int $newCompanyId, string $tempConnection): int
    {
        $rows = DB::connection($tempConnection)
            ->table('place_opening_hours')
            ->where('place_id', $oldPlaceId)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $imported = 0;

        foreach ($rows as $row) {
            $dayOfWeek = $this->remapDay($row->day ?? null);

            if ($dayOfWeek === null) {
                Log::warning("TenantImport OpeningHours: Ungültiger Wochentag für Place #{$oldPlaceId}.", [
                    'day' => $row->day ?? 'NULL',
                ]);
                continue;
            }

            $opensAt = $this->parseTime($row->opened ?? null);
            $closesAt = $this->parseTime($row->closed ?? null);
            $isClosed = empty($row->opened) && empty($row->closed);

            CompanyOpeningHour::updateOrCreate(
                [
                    'company_id' => $newCompanyId,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'opens_at' => $isClosed ? null : $opensAt,
                    'closes_at' => $isClosed ? null : $closesAt,
                    'is_closed' => $isClosed,
                ],
            );

            $imported++;
        }

        return $imported;
    }

    /**
     * Remapping: Alt day 1-7 (1=Mo, 7=So) → Neu day_of_week 0-6 (0=Mo, 6=So).
     * Wert 0 wird als Sonntag (6) interpretiert.
     */
    private function remapDay(?int $day): ?int
    {
        if ($day === null) {
            return null;
        }

        // 0 = Sonntag in manchen Systemen
        if ($day === 0) {
            return 6;
        }

        // 1-7 (1=Mo, 7=So) → 0-6
        if ($day >= 1 && $day <= 7) {
            return $day - 1;
        }

        return null;
    }

    /**
     * Parst verschiedene Zeitformate zu H:i:s.
     * Unterstützt: H:i, H:i:s, H.i, Hi, HHmm
     */
    private function parseTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Bereits H:i:s
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        // H:i
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        // H.i
        if (preg_match('/^(\d{1,2})\.(\d{2})$/', $value, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        // HHmm oder Hmm (z.B. "0700" oder "700")
        if (preg_match('/^(\d{1,2})(\d{2})$/', $value, $m)) {
            $hour = (int) $m[1];
            $min = (int) $m[2];
            if ($hour <= 23 && $min <= 59) {
                return sprintf('%02d:%02d:00', $hour, $min);
            }
        }

        Log::warning("TenantImport OpeningHours: Ungültiges Zeitformat.", ['value' => $value]);
        return null;
    }
}
