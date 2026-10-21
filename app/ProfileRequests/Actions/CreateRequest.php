<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\Company;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Legt eine Anfrage aus dem Self-Service-Formular an (SUN-RQ #4).
 *
 * Alles in einer Transaktion auf der Central-Verbindung: Anfrage, Diff und
 * die Zeilen zu den Anhaengen. Ein Abbruch mitten drin hinterlaesst sonst
 * eine Aenderungsanfrage ohne Diff — im Panel (#7) nicht von "nichts
 * geaendert" zu unterscheiden.
 *
 * Status ist direkt `classified`: Typ, Portal, Betrieb und Diff liegen beim
 * Formularweg strukturiert vor, es gibt nichts zu klassifizieren
 * (RequestStatus, docs/profile-requests.md 8). Die Verifikation — Magic-Link
 * an die beim Profil hinterlegte Firmen-E-Mail — stoesst #6 an; diese Aktion
 * verschickt keine Mail und erzeugt bewusst keinen Token, denn ein Token
 * ohne Mail waere eine Anfrage, die bis zum Ablauf auf eine Bestaetigung
 * wartet, die niemand bekommen hat.
 *
 * Die Dateien liegen auf der Platte `local` unter requests/{id}/ und werden
 * **nicht** ins Profil geschrieben: erst eine Freigabe im Panel (#7) oder
 * eine Auto-Regel (#9) macht ein hochgeladenes Logo zum Logo des Betriebs.
 */
class CreateRequest
{
    /**
     * @param  array{name?: string|null, email: string, phone?: string|null}  $requester
     * @param  array<string, array{old: mixed, new: mixed}>  $diff  Ergebnis von {@see BuildChangeSet}
     * @param  array<int, TemporaryUploadedFile>  $files
     */
    public function __invoke(
        Company $company,
        RequestType $type,
        array $requester,
        array $diff = [],
        array $files = [],
        ?string $message = null,
    ): ProfileRequest {
        // Das Portal steht im laufenden Request; der Betrieb kommt aus seiner
        // Tenant-DB, die Anfrage geht in die Central-DB (#3).
        $tenantId = tenant() !== null ? (int) tenant()->getKey() : null;

        $request = DB::connection((new ProfileRequest)->getConnectionName())->transaction(
            function () use ($company, $type, $requester, $diff, $message, $tenantId): ProfileRequest {
                $request = ProfileRequest::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $company->getKey(),
                    'type' => $type,
                    'source' => RequestSource::FORM,
                    'status' => RequestStatus::CLASSIFIED,
                    'requester_name' => $requester['name'] ?? null,
                    'requester_email' => $requester['email'] ?? '',
                    'requester_phone' => $requester['phone'] ?? null,
                    'message' => $message,
                ]);

                foreach ($diff as $field => $values) {
                    $request->changes()->create([
                        'field' => $field,
                        'old_value' => $values['old'] ?? null,
                        'new_value' => $values['new'] ?? null,
                    ]);
                }

                return $request;
            }
        );

        // Erst nach der Transaktion: ein Rollback kann eine schon verschobene
        // Datei nicht zurueckholen, und der Ablagepfad braucht die ID.
        if ($files !== []) {
            $this->storeFiles($request, $files);
        }

        return $request;
    }

    /**
     * Anhaenge ablegen. Jede Datei bekommt einen neuen, zufaelligen Namen —
     * der Originalname eines Uploads ist Fremdeingabe und hat auf der Platte
     * nichts zu suchen. Scheitert eine Datei, bleibt die Anfrage bestehen:
     * sie ist das Wichtigere, und das Panel sieht am fehlenden Anhang, dass
     * nachgefragt werden muss.
     *
     * @param  array<int, TemporaryUploadedFile>  $files
     */
    private function storeFiles(ProfileRequest $request, array $files): void
    {
        $directory = RequestAttachment::directoryFor((int) $request->getKey());
        $purgeAfter = now()->addDays((int) config('profile-requests.retention_days', 90));

        foreach ($files as $file) {
            $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $name = Str::random(40).'.'.$extension;

            $path = $file->storeAs($directory, $name, RequestAttachment::DISK);

            if ($path === false) {
                continue;
            }

            $request->attachments()->create([
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'is_raw_mail' => false,
                'purge_after' => $purgeAfter,
            ]);
        }
    }
}
