<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use App\Models\Portal\CompanyOpeningHour;
use Illuminate\Support\Number;

/**
 * Lesbare Darstellung eines Diff-Werts fuer die Panel-Komponente
 * (SUN-RQ, Ticket #11).
 *
 * `request_changes.old_value`/`new_value` sind JSON, weil Oeffnungszeiten und
 * Leistungen strukturiert sind (#3). Im Panel darf davon nichts als JSON
 * ankommen — ein `{"day_of_week":0,"opens_at":"08:00:00"}` ist fuer eine
 * Freigabeentscheidung unbrauchbar. Diese Klasse macht aus jedem Wert einen
 * **Beschreiber**, den das Blade rendert:
 *
 *   ['kind' => 'empty']
 *   ['kind' => 'scalar', 'text' => '0221 123456', 'link' => 'tel:...']
 *   ['kind' => 'text',   'text' => "mehrzeiliger Freitext"]
 *   ['kind' => 'rows',   'rows' => [['label' => 'Montag', 'value' => '08:00 – 17:00'], ...]]
 *   ['kind' => 'list',   'items' => [['text' => 'Bad', 'note' => 'ab 49,00 €'], ...]]
 *   ['kind' => 'image',  'text' => 'logo.png']
 *
 * HTML entsteht hier nicht — das bleibt im Blade, damit die Darstellung an
 * einer Stelle liegt. Der einzige HTML-Lieferant ist {@see WordDiff}.
 */
final class FieldRenderer
{
    /**
     * Anzeigename je Diff-Schluessel. Schluessel sind Spalten bzw.
     * Relationen von App\Models\Portal\Company; die Liste deckt die
     * Whitelist aus config('profile-requests.auto_apply_fields') und die
     * freigabepflichtigen Felder aus docs/profile-requests.md 3 ab.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'name' => 'Firmenname',
        'tel' => 'Telefon',
        'email' => 'E-Mail',
        'website' => 'Website',
        'opening_hours' => 'Öffnungszeiten',
        'description' => 'Beschreibung',
        'services' => 'Leistungen',
        'logo' => 'Logo',
        // Sammelzeile fuer Bilder aus den Anhaengen (Logo/Galerie): sie sind
        // keine Diff-Zeile, stehen aber in der Bestaetigungsmail.
        'images' => 'Bilder',
        'social_facebook' => 'Facebook',
        'social_instagram' => 'Instagram',
        'social_linkedin' => 'LinkedIn',
        'social_youtube' => 'YouTube',
        'video_url' => 'Video',
        'street' => 'Straße',
        'house_no' => 'Hausnummer',
        'zipcode' => 'PLZ',
        'city_id' => 'Stadt',
        'city' => 'Stadt',
        'categories' => 'Kategorien',
        'user_id' => 'Inhaber',
    ];

    /**
     * Felder, deren Wert ein strukturierter Datensatz ist und deshalb nicht
     * als Text bearbeitet werden kann.
     */
    private const STRUCTURED = ['opening_hours', 'services', 'categories'];

    /** Felder, die ein Bild tragen. */
    private const IMAGES = ['logo', 'images', 'gallery'];

    /** Felder, deren Wert ein mehrzeiliger Freitext ist. */
    private const LONG_TEXT = ['description', 'message'];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? str_replace('_', ' ', $field);
    }

    /**
     * Darf der neue Wert im Panel von Hand nachgebessert werden? Nur bei
     * Text und Skalaren — eine Oeffnungszeitentabelle als JSON-Textarea zu
     * bearbeiten waere eine Einladung zum kaputten Datensatz.
     */
    public static function isEditable(string $field): bool
    {
        return ! in_array($field, self::STRUCTURED, true)
            && ! in_array($field, self::IMAGES, true);
    }

    public static function isMultiline(string $field): bool
    {
        return in_array($field, self::LONG_TEXT, true);
    }

    public static function isImage(string $field): bool
    {
        return in_array($field, self::IMAGES, true);
    }

    /**
     * Steht das Feld auf der Whitelist der Auto-Uebernahme? Alles andere
     * braucht die Freigabe von Enes (docs/profile-requests.md 3) und wird in
     * der Komponente entsprechend markiert.
     */
    public static function isWhitelisted(string $field): bool
    {
        return in_array($field, (array) config('profile-requests.auto_apply_fields', []), true);
    }

    /**
     * Beschreiber des Werts.
     *
     * @return array<string, mixed>
     */
    public static function describe(string $field, mixed $value): array
    {
        if (self::isEmpty($value)) {
            return ['kind' => 'empty'];
        }

        return match ($field) {
            'opening_hours' => self::openingHours($value),
            'services' => self::services($value),
            'categories' => self::simpleList($value),
            default => self::generic($field, $value),
        };
    }

    public static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === [] || $value === '') {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    /**
     * Oeffnungszeiten: eine Zeile je Wochentag, in der Reihenfolge von
     * CompanyOpeningHour::DAYS (Montag zuerst) und nicht in der Reihenfolge,
     * in der die Zeilen zufaellig im JSON stehen.
     *
     * @return array<string, mixed>
     */
    private static function openingHours(mixed $value): array
    {
        if (! is_array($value)) {
            return self::generic('opening_hours', $value);
        }

        $byDay = [];

        foreach ($value as $key => $entry) {
            // Drei Formen: Datensätze aus der Tabelle (`day_of_week`),
            // Diff-Zeilen aus Formular und KI (`day`) oder eine Abbildung
            // Tag => Zeiten. Der Schlüssel ist dabei die unzuverlässigste
            // Quelle — eine Liste, die nicht bei Montag beginnt, hat ihn
            // gegen den Tag verschoben —, deshalb kommt er zuletzt.
            $day = null;

            if (is_array($entry) && array_key_exists('day_of_week', $entry)) {
                $day = (int) $entry['day_of_week'];
            } elseif (is_array($entry) && array_key_exists('day', $entry) && is_numeric($entry['day'])) {
                $day = (int) $entry['day'];
            } elseif (is_numeric($key) && is_array($entry)) {
                $day = (int) $key;
            }

            if ($day === null) {
                continue;
            }

            $byDay[$day][] = is_array($entry) ? $entry : ['value' => $entry];
        }

        if ($byDay === []) {
            return self::simpleList($value);
        }

        $rows = [];

        foreach (CompanyOpeningHour::DAYS as $day => $name) {
            if (! isset($byDay[$day])) {
                $rows[] = ['label' => $name, 'value' => 'Keine Angabe', 'muted' => true];

                continue;
            }

            $times = array_map(self::timeSpan(...), $byDay[$day]);
            $times = array_values(array_filter($times, fn (string $t): bool => $t !== ''));

            $rows[] = [
                'label' => $name,
                'value' => $times === [] ? 'Keine Angabe' : implode(', ', $times),
                'muted' => $times === [],
            ];
        }

        return ['kind' => 'rows', 'rows' => $rows];
    }

    /** @param  array<string, mixed>  $entry */
    private static function timeSpan(array $entry): string
    {
        // Zwei Schreibweisen, beide echt: `is_closed`/`opens_at`/`closes_at`
        // kommt aus der Tabelle `company_opening_hours`, `closed`/`opens`/
        // `closes` aus dem Diff — so baut es BuildChangeSet fuer das
        // Self-Service-Formular (#4) und so liefert es die KI (#27). Wer
        // nur das Datenbankformat liest, zeigt jeden Formular-Diff als
        // „Keine Angabe" an.
        if (! empty($entry['is_closed']) || ! empty($entry['closed'])) {
            return 'Geschlossen';
        }

        if (isset($entry['value']) && is_scalar($entry['value'])) {
            return (string) $entry['value'];
        }

        $opens = self::clockValue($entry['opens_at'] ?? $entry['opens'] ?? null);
        $closes = self::clockValue($entry['closes_at'] ?? $entry['closes'] ?? null);

        if ($opens === '' && $closes === '') {
            return '';
        }

        return trim("{$opens} – {$closes}", ' –');
    }

    /** „08:00:00", „08:00", 8 -> „08:00"; leer bleibt leer. */
    private static function clockValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return substr(trim((string) $value), 0, 5);
    }

    /**
     * Leistungen: Name, dazu der Ab-Preis als Notiz und die Beschreibung
     * darunter. price_from steht wie in CompanyService in Cent.
     *
     * @return array<string, mixed>
     */
    private static function services(mixed $value): array
    {
        if (! is_array($value)) {
            return self::generic('services', $value);
        }

        $items = [];

        foreach ($value as $entry) {
            if (is_scalar($entry)) {
                $items[] = ['text' => (string) $entry];

                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $name = (string) ($entry['name'] ?? $entry['title'] ?? '');

            if (trim($name) === '') {
                continue;
            }

            $items[] = [
                'text' => $name,
                'note' => isset($entry['price_from']) && is_numeric($entry['price_from'])
                    ? 'ab '.Number::currency(((int) $entry['price_from']) / 100, in: (string) config('premium.currency', 'EUR'), locale: 'de')
                    : null,
                'description' => isset($entry['description']) && is_string($entry['description']) && trim($entry['description']) !== ''
                    ? $entry['description']
                    : null,
            ];
        }

        return $items === [] ? ['kind' => 'empty'] : ['kind' => 'list', 'items' => $items];
    }

    /**
     * Flache Liste — Kategorien und alles, was als Array ankommt, ohne dass
     * ein Feldtyp dafuer bekannt ist.
     *
     * @return array<string, mixed>
     */
    private static function simpleList(mixed $value): array
    {
        $items = [];

        foreach ((array) $value as $key => $entry) {
            if (is_scalar($entry)) {
                $items[] = ['text' => is_bool($entry) ? ($entry ? 'ja' : 'nein') : (string) $entry];

                continue;
            }

            if (is_array($entry)) {
                $text = $entry['name'] ?? $entry['title'] ?? $entry['label'] ?? null;

                $items[] = is_scalar($text)
                    // Ohne sprechendes Feld bleibt nur der Schluessel, aber
                    // nie eine JSON-Zeile.
                    ? ['text' => (string) $text]
                    : ['text' => (string) $key, 'note' => 'unbekannte Struktur'];
            }
        }

        return $items === [] ? ['kind' => 'empty'] : ['kind' => 'list', 'items' => $items];
    }

    /**
     * Skalare Felder: Text, Link, Bildname.
     *
     * @return array<string, mixed>
     */
    private static function generic(string $field, mixed $value): array
    {
        if (is_array($value)) {
            return self::simpleList($value);
        }

        if (is_bool($value)) {
            return ['kind' => 'scalar', 'text' => $value ? 'ja' : 'nein'];
        }

        $text = (string) $value;

        if (self::isImage($field)) {
            return ['kind' => 'image', 'text' => $text];
        }

        if (self::isMultiline($field)) {
            return ['kind' => 'text', 'text' => $text];
        }

        return ['kind' => 'scalar', 'text' => $text, 'link' => self::link($field, $text)];
    }

    /**
     * Anklickbar nur, wo der Wert ohne Umformung ein Ziel ergibt. Eine
     * Website ohne Schema bekommt https:// davor, weil ein relativer Link
     * im Panel landen wuerde.
     */
    private static function link(string $field, string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($field === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? "mailto:{$value}" : null;
        }

        if ($field === 'tel') {
            return 'tel:'.preg_replace('/[^\d+]/', '', $value);
        }

        if ($field === 'website' || $field === 'video_url' || str_starts_with($field, 'social_')) {
            return preg_match('#^https?://#i', $value) ? $value : "https://{$value}";
        }

        return null;
    }
}
