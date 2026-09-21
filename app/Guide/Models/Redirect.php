<?php

declare(strict_types=1);

namespace App\Guide\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * 301-Weiterleitung einer alten Ratgeber-Adresse (#18).
 *
 * Gelesen von HandleGuideRedirects, erst wenn die Route 404 liefert — eine
 * bestehende Seite wird nie umgeleitet. Angelegt automatisch beim Aendern
 * eines Kategorie-Slugs (Category::booted) oder von Hand ueber record(),
 * etwa fuer zusammengelegte Altartikel.
 *
 * @property string $source_path
 * @property string $target_path
 * @property string $reason
 */
class Redirect extends Model
{
    use TenantConnection;

    public const REASON_CATEGORY_SLUG = 'category_slug';

    public const REASON_ARTICLE_MERGE = 'article_merge';

    public const REASON_MANUAL = 'manual';

    protected $table = 'guide_redirects';

    protected $fillable = [
        'source_path',
        'target_path',
        'reason',
        'guide_category_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'guide_category_id');
    }

    /**
     * Legt eine Weiterleitung an oder aktualisiert sie. Ketten werden
     * verkuerzt (A → B und jetzt B → C ergibt A → C), und eine Adresse, die
     * wieder Ziel wird, verliert ihre eigene Weiterleitung — so entsteht nie
     * eine Schleife.
     */
    public static function record(string $source, string $target, string $reason = self::REASON_MANUAL, ?int $categoryId = null): self
    {
        $source = self::normalizePath($source);
        $target = self::normalizeTarget($target);

        if ($source === $target) {
            throw new InvalidArgumentException("Weiterleitung auf sich selbst: {$source}");
        }

        return DB::connection((new self)->getConnectionName())->transaction(function () use ($source, $target, $reason, $categoryId): self {
            self::query()->where('target_path', $source)->update(['target_path' => $target]);
            self::query()->where('source_path', $target)->delete();
            self::query()->whereColumn('source_path', 'target_path')->delete();

            return self::query()->updateOrCreate(
                ['source_path' => $source],
                ['target_path' => $target, 'reason' => $reason, 'guide_category_id' => $categoryId],
            );
        });
    }

    /**
     * Ziel fuer einen Anfragepfad, oder null.
     */
    public static function targetFor(string $path): ?string
    {
        $target = self::query()->where('source_path', self::normalizePath($path))->value('target_path');

        return $target !== null ? (string) $target : null;
    }

    /**
     * '/ratgeber/kategorie/alt/' und 'ratgeber/kategorie/alt?x=1' werden zu
     * '/ratgeber/kategorie/alt'.
     */
    public static function normalizePath(string $path): string
    {
        $path = (string) (parse_url(trim($path), PHP_URL_PATH) ?? '');

        return '/'.trim(mb_strtolower($path), '/');
    }

    /**
     * Ziele sind immer Pfade auf dem eigenen Host; von absoluten Adressen
     * ('https://fremd.de/x', '//fremd.de/x') bleibt nur der Pfad (Review S4).
     */
    private static function normalizeTarget(string $target): string
    {
        return self::normalizePath($target);
    }
}
