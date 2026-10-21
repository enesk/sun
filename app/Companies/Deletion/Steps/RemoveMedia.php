<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Bilder loeschen (#8): Logo, Titelbild, Galerie — Datenbankzeile **und**
 * Datei samt aller Conversions.
 *
 * `Media::delete()` statt eines Massen-`delete()` auf der Tabelle: nur das
 * Model-Event der Media Library raeumt die Dateien im Tenant-Storage
 * (`storage/tenant<uuid>/app/public`) ab. Ein `DELETE FROM media` liesse
 * 873 438 Bilder auf der Platte liegen und waere keine Loeschung.
 *
 * Dazu `companies.logo_path` — das alte, nicht ueber die Media Library
 * gefuehrte Feld.
 *
 * Nicht leistbar: der Cloudflare-Cache vor dem Ursprung. Es gibt keinen
 * Cloudflare-Zugang im Projekt; die Bild-URLs sind sofort 404, der
 * Kanten-Cache laeuft mit seiner TTL aus. Der Schritt vermerkt das im
 * Protokoll, damit es bei Bedarf von Hand gepurgt werden kann.
 */
class RemoveMedia implements DeletionStep
{
    public function key(): string
    {
        return 'remove_media';
    }

    public function label(): string
    {
        return 'Bilder löschen';
    }

    public function plan(DeletionContext $context): string
    {
        $count = $context->company->media()->count();

        return sprintf('%d Bilddatei(en) samt Conversions aus dem Tenant-Storage löschen, logo_path leeren.', $count);
    }

    public function run(DeletionContext $context): StepResult
    {
        $company = $context->company;
        $deleted = 0;
        $errors = [];

        /** @var iterable<Media> $items */
        $items = $company->media()->get();

        foreach ($items as $media) {
            try {
                $media->delete();
                $deleted++;
            } catch (Throwable $exception) {
                $errors[] = $media->getKey().': '.$exception->getMessage();
            }
        }

        if ($company->logo_path !== null) {
            $company->forceFill(['logo_path' => null])->save();
        }

        $payload = [
            'media_deleted' => $deleted,
            'edge_cache' => 'Cloudflare-Cache nicht automatisch löschbar — läuft mit seiner TTL aus.',
        ];

        if ($errors !== []) {
            return StepResult::failed(
                sprintf('%d von %d Bildern gelöscht, %d Fehler.', $deleted, $deleted + count($errors), count($errors)),
                $payload + ['errors' => array_slice($errors, 0, 10)],
            );
        }

        return StepResult::ok(sprintf('%d Bilddatei(en) gelöscht.', $deleted), $payload);
    }
}
