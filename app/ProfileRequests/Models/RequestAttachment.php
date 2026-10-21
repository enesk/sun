<?php

declare(strict_types=1);

namespace App\ProfileRequests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Throwable;

/**
 * Anhang einer Anfrage oder die Rohmail selbst (SUN-RQ, Ticket #3).
 *
 * `path` ist relativ zur Platte `local`, also storage/app/private/ — die
 * Dateien sind nie oeffentlich erreichbar. `purge_after` setzt #5 auf
 * created_at + retention_days.
 *
 * @property int $id
 * @property int $request_id
 * @property string $path
 * @property string|null $mime
 * @property int $size
 * @property bool $is_raw_mail
 * @property string|null $image_role
 * @property \Illuminate\Support\Carbon|null $purge_after
 */
class RequestAttachment extends Model
{
    use CentralConnection;

    /**
     * Platte, auf der Rohmails und Anhaenge liegen.
     *
     * `requests` und nicht `local`: `local` wird vom
     * FilesystemTenancyBootstrapper waehrend eines Portal-Requests auf
     * storage/tenant<id>/app/ umgebogen (config/tenancy.php,
     * `filesystem.root_override`). Ein Anhang aus dem
     * Self-Service-Formular (#4) landete damit in der Ablage des Portals,
     * waehrend das Panel und `requests:purge-raw-mail` zentral suchen —
     * Datei im Panel nicht da, Loeschfrist wirkungslos (Security-Review
     * #12, Befund 2). Der Disk `requests` hat denselben Wurzelpfad wie das
     * zentrale `local` und wird nie umgebogen.
     */
    public const DISK = 'requests';

    /** Rollen eines Bild-Anhangs, gesetzt in der Diff-Komponente (#11). */
    public const ROLE_LOGO = 'logo';

    public const ROLE_GALLERY = 'gallery';

    /** Ausdruecklich nicht uebernehmen — der Haken bleibt eine Entscheidung. */
    public const ROLE_IGNORE = 'ignore';

    protected $table = 'request_attachments';

    protected $fillable = [
        'request_id',
        'path',
        'mime',
        'size',
        'is_raw_mail',
        'image_role',
        'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_raw_mail' => 'boolean',
            'purge_after' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileRequest::class, 'request_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeRawMail(Builder $query): void
    {
        $query->where('is_raw_mail', true);
    }

    /** Bild-Anhaenge — die Vorschau der Diff-Komponente (#11). @param  Builder<self>  $query */
    public function scopeImages(Builder $query): void
    {
        $query->where('is_raw_mail', false)->where('mime', 'like', 'image/%');
    }

    /** Ist das ein Bild und damit als Logo oder Galeriebild verwendbar? */
    public function isImage(): bool
    {
        return ! $this->is_raw_mail && str_starts_with((string) $this->mime, 'image/');
    }

    public function fileName(): string
    {
        return basename($this->path);
    }

    /** Faellige Aufbewahrungsfristen (#5). @param  Builder<self>  $query */
    public function scopeDuePurge(Builder $query): void
    {
        $query->whereNotNull('purge_after')->where('purge_after', '<=', now());
    }

    /** Ablageordner einer Anfrage — eine Stelle, damit #5 und #6 sie teilen. */
    public static function directoryFor(int $requestId): string
    {
        return "requests/{$requestId}";
    }

    /**
     * Thumbnail als Data-URI fuer die Diff-Komponente (#11).
     *
     * Data-URI und keine URL: die Anhaenge liegen auf der privaten Platte
     * (storage/app/private/), und eine oeffentliche URL dafuer waere ein
     * neuer Weg nach draussen fuer Dateien, die uns ein Fremder geschickt
     * hat. Das verkleinerte WebP liegt bei ca. 3 kB, dafuer braucht es
     * keinen Controller.
     *
     * Das Ergebnis wird als thumb-{id}.webp neben dem Original abgelegt und
     * beim naechsten Aufruf von dort gelesen — die Inbox (#7) rendert diese
     * Komponente bei jedem Livewire-Update neu.
     *
     * null, wenn der Anhang kein Bild ist oder sich nicht dekodieren laesst
     * (eine kaputte Datei darf das Panel nicht umbringen).
     */
    public function thumbnailDataUri(int $size = 160): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $thumbPath = self::directoryFor((int) $this->request_id)."/thumb-{$this->id}-{$size}.webp";

        try {
            if (! $disk->exists($thumbPath)) {
                if (! $disk->exists($this->path)) {
                    return null;
                }

                $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;

                $thumb = (new ImageManager($driver))
                    ->read((string) $disk->get($this->path))
                    // contain statt cover: ein Logo darf nicht angeschnitten
                    // werden, sonst entscheidet Enes anhand eines Ausschnitts.
                    ->scaleDown($size, $size)
                    ->toWebp(70);

                $disk->put($thumbPath, (string) $thumb);
            }

            return 'data:image/webp;base64,'.base64_encode((string) $disk->get($thumbPath));
        } catch (Throwable $e) {
            Log::warning('Thumbnail eines Anfrage-Anhangs fehlgeschlagen', [
                'attachment_id' => $this->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Datei von der Platte nehmen und die Zeile loeschen; Anfrage, Diff und
     * Protokoll bleiben stehen.
     */
    public function purge(): bool
    {
        $disk = Storage::disk(self::DISK);
        $disk->delete($this->path);

        // Die Thumbnails aus #11 liegen daneben und gehen mit.
        $prefix = self::directoryFor((int) $this->request_id)."/thumb-{$this->id}-";

        foreach ($disk->files(self::directoryFor((int) $this->request_id)) as $file) {
            if (str_starts_with($file, $prefix)) {
                $disk->delete($file);
            }
        }

        return (bool) $this->delete();
    }
}
