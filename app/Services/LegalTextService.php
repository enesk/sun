<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LegalTextKey;
use App\Models\LegalText;

/**
 * Lesen und Schreiben der zentralen Rechtstexte (Ticket #17).
 *
 * Eine Fassung je Textart fuer alle Portale. Die portalspezifischen Angaben
 * kommen erst beim Ausliefern dazu, ueber die Platzhalter `[BETREIBER_*]` in
 * {@see TenantBrandingService::resolveLegalPlaceholders()}.
 *
 * **Bewusst ohne Cache.** Der Anwendungscache ist in diesem Projekt nicht
 * mandantengetrennt (`CacheTenancyBootstrapper` ist in config/tenancy.php
 * auskommentiert), und der file-Store zeigt im Tenant-Kontext auf
 * `storage/tenant<uuid>/…`. Gelesen wird hier im Portal-Kontext, gespeichert
 * im Panel ohne Tenant — ein Eintrag, den das Portal angelegt hat, waere vom
 * Panel aus nicht zu leeren, und die Portale wuerden nach einer Aenderung
 * unterschiedlich lange den alten Text zeigen. Ein Rechtstext darf nicht
 * veraltet ausgeliefert werden, und Rechtstextseiten werden selten aufgerufen:
 * ein Primaerschluessel-Treffer je Aufruf ist der richtige Preis.
 */
class LegalTextService
{
    /**
     * Die zentrale Fassung, noch mit Platzhaltern. `null` heisst: zentral ist
     * nichts gepflegt — dann entscheidet der Aufrufer, was er tut.
     */
    public function get(LegalTextKey $key): ?string
    {
        return $this->normalize(
            LegalText::query()->where('key', $key->value)->value('content')
        );
    }

    /**
     * Leeren Inhalt zu `null` machen — **auch wenn er aus leerem Markup
     * besteht.**
     *
     * Der RichEditor gibt ein geleertes Feld als `<p></p>` zurück, nicht als
     * leeren String. Wuerde das als gepflegt gelten, liefert die zentrale
     * Fassung allen 23 Portalen eine **leere Rechtstextseite** — bei
     * Pflichtangaben der schlimmste denkbare Fehler dieses Vorhabens. Ein
     * Feld, in dem nichts steht, muss deshalb dasselbe bedeuten wie ein nie
     * gepflegtes Feld: die Portale behalten ihren eigenen Text.
     */
    private function normalize(mixed $inhalt): ?string
    {
        if (! is_string($inhalt)) {
            return null;
        }

        $inhalt = trim($inhalt);

        if ($inhalt === '') {
            return null;
        }

        // Tags entfernen und geschuetzte Leerzeichen mitzaehlen: '<p></p>',
        // '<p><br></p>' und '<p>&nbsp;</p>' sind alle leer.
        $text = html_entity_decode(strip_tags($inhalt), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', '', $text) ?? '';

        // Ein Bild oder ein Trennstrich ohne Text ist zwar seltsam, aber
        // Inhalt — sonst verwirft ein Logo im Impressum den ganzen Text.
        $hatElemente = preg_match('/<(img|hr|table|iframe)\b/i', $inhalt) === 1;

        return ($text === '' && ! $hatElemente) ? null : $inhalt;
    }

    /**
     * @return array<string, string|null> Schluessel aus LegalTextKey => Inhalt
     */
    public function all(): array
    {
        $gepflegt = LegalText::query()->pluck('content', 'key')->all();

        $ergebnis = [];

        foreach (LegalTextKey::all() as $key) {
            $ergebnis[$key->value] = $this->normalize($gepflegt[$key->value] ?? null);
        }

        return $ergebnis;
    }

    /**
     * Schreibt eine Fassung. Der Aufrufer ist fuer das Saubermachen des HTML
     * verantwortlich ({@see TenantBrandingService::sanitizeLegalHtml()}) — hier
     * wird nicht noch einmal gefiltert, weil ein zweiter Durchlauf die
     * Platzhalter beschaedigen koennte.
     */
    public function set(LegalTextKey $key, ?string $content, ?int $updatedBy = null): void
    {
        LegalText::query()->updateOrCreate(
            ['key' => $key->value],
            ['content' => $content, 'updated_by' => $updatedBy],
        );
    }

    /**
     * Ist zentral alles gepflegt? Solange das nicht stimmt, darf die zentrale
     * Auslieferung nicht greifen — sonst stehen Portale ohne Rechtstext da.
     */
    public function isComplete(): bool
    {
        foreach ($this->all() as $inhalt) {
            if ($inhalt === null) {
                return false;
            }
        }

        return true;
    }

    /** @return list<LegalTextKey> Textarten, die zentral noch fehlen */
    public function missing(): array
    {
        $fehlend = [];

        foreach (LegalTextKey::all() as $key) {
            if ($this->get($key) === null) {
                $fehlend[] = $key;
            }
        }

        return $fehlend;
    }
}
