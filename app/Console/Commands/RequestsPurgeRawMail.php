<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProfileRequests\Models\RequestAttachment;
use Illuminate\Console\Command;

/**
 * Aufbewahrungsfrist der Rohmails und Anhaenge (SUN-RQ, Ticket #5).
 *
 * Loescht Datei und Zeile, sobald `purge_after` erreicht ist (gesetzt beim
 * Einlesen auf created_at + `profile-requests.retention_days`). Anfrage,
 * Diff und Protokoll bleiben stehen — das Protokoll ist bei Loesch- und
 * Uebernahmeantraegen die Rechtsgrundlage und ueberlebt die Mail.
 */
class RequestsPurgeRawMail extends Command
{
    protected $signature = 'requests:purge-raw-mail';

    protected $description = 'Loescht Rohmails und Anhaenge, deren Aufbewahrungsfrist abgelaufen ist';

    public function handle(): int
    {
        $geloescht = 0;

        RequestAttachment::query()
            ->duePurge()
            ->orderBy('id')
            ->chunkById(200, function ($anhaenge) use (&$geloescht): void {
                foreach ($anhaenge as $anhang) {
                    /** @var RequestAttachment $anhang */
                    $anhang->purge();
                    $geloescht++;
                }
            });

        $this->info("{$geloescht} Dateien geloescht.");

        return self::SUCCESS;
    }
}
