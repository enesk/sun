<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Bericht eines Laufs der Bestandsbereinigung (#10).
 *
 * Zwei Ausgaben, eine Quelle: eine Zeile im Log (fuer das Monitoring in #12)
 * und eine JSON-Datei unter `storage/app/antispam/`. Die Datei ist das, was bei
 * einer Rueckfrage spaeter zaehlt — die Konsolenausgabe ist nach dem Lauf weg.
 *
 * In den Bericht kommen nur IDs, Zahlen und Regelcodes. Keine Namen, keine
 * Adressen, keine Hashes: der Bericht liegt unverschluesselt im Dateisystem und
 * geht in Sicherungen ein.
 *
 * Geschrieben wird ausserhalb jedes Portalkontexts, damit alle Berichte
 * zusammen an einer Stelle liegen und nicht im Mandanten-Verzeichnis.
 */
final class ScanReport
{
    /**
     * @param  array<string, mixed>  $payload
     * @return string Pfad der geschriebenen Datei
     */
    public static function write(string $kind, array $payload): string
    {
        $directory = storage_path('app/antispam');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$kind.'-'.now()->format('Y-m-d_His').'.json';

        File::put($path, json_encode(
            ['kind' => $kind, 'written_at' => now()->toIso8601String()] + $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ).PHP_EOL);

        Log::info("antispam: {$kind}", ['report' => $path] + array_diff_key($payload, ['portals' => null]));

        return $path;
    }
}
