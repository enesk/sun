<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\ClaimRequest;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Nachweisdokumente eines Übernahmeantrags an den Inbox-Vorgang hängen.
 *
 * Der Nachweis entsteht **nach** dem Antrag: der Dialog legt den Antrag an
 * und leitet auf die Verifizierungsseite, dort lädt der Antragsteller die
 * Datei in die Media-Collection `claim_documents`. Ohne diesen Schritt
 * stünde der Vorgang in der Inbox ohne Nachweis, während die Datei nur am
 * Altantrag hängt.
 *
 * Kopiert, nicht verschoben: solange das alte Panel lebt, muss der Nachweis
 * auch dort sichtbar bleiben.
 *
 * Idempotent über die Zahl der Anhänge — ein zweiter Lauf (der Nutzer
 * tauscht die Datei aus) ersetzt sie, statt sie zu sammeln.
 */
class AttachClaimDocuments
{
    public function __invoke(ClaimRequest $claimRequest, ProfileRequest $request): int
    {
        $medien = $claimRequest->getMedia('claim_documents');

        if ($medien->isEmpty()) {
            return 0;
        }

        // Austausch statt Sammlung: auf der Verifizierungsseite leert
        // ClaimVerification die Collection, bevor sie neu ablegt. Die Inbox
        // folgt dem, sonst stünden dort beide Fassungen.
        foreach ($request->attachments()->where('is_raw_mail', false)->get() as $alt) {
            Storage::disk(RequestAttachment::DISK)->delete((string) $alt->path);
            $alt->delete();
        }

        $verzeichnis = RequestAttachment::directoryFor((int) $request->getKey());
        $aufbewahrung = now()->addDays((int) config('profile-requests.retention_days', 90));
        $uebernommen = 0;

        foreach ($medien as $media) {
            try {
                $endung = Str::lower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION) ?: 'bin');
                $ziel = $verzeichnis.'/'.Str::random(40).'.'.$endung;

                $strom = $media->stream();

                if (! is_resource($strom)) {
                    throw new RuntimeException('Datei zum Media-Eintrag fehlt auf der Ablage');
                }

                Storage::disk(RequestAttachment::DISK)->writeStream($ziel, $strom);

                if (is_resource($strom)) {
                    fclose($strom);
                }

                $request->attachments()->create([
                    'path' => $ziel,
                    'mime' => (string) $media->mime_type,
                    'size' => (int) $media->size,
                    'is_raw_mail' => false,
                    'purge_after' => $aufbewahrung,
                ]);

                $uebernommen++;
            } catch (Throwable $e) {
                $request->recordEvent(RequestEventType::NOTE, [
                    'note' => "Nachweisdokument konnte nicht übernommen werden: {$e->getMessage()}",
                ]);
            }
        }

        if ($uebernommen > 0) {
            $request->recordEvent(RequestEventType::NOTE, [
                'note' => $uebernommen === 1
                    ? 'Nachweis zum Übernahmeantrag eingegangen.'
                    : "{$uebernommen} Nachweisdokumente zum Übernahmeantrag eingegangen.",
            ]);
        }

        return $uebernommen;
    }
}
