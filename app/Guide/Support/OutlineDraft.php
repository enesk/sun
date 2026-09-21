<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Import\HeadingsParser;
use Illuminate\Support\Str;

/**
 * Bearbeitungsform einer Gliederung fuer den Gliederungs-Editor (#15).
 *
 * Der Editor arbeitet auf einer flachen Zeilenliste
 * [{key, id, level, heading, original}], gespeichert wird die verschachtelte
 * Form von guide_topics.outline_json. Die `id` einer vorhandenen Ueberschrift
 * bleibt beim Umbenennen, Verschieben und Ein-/Ausruecken erhalten — sie ist
 * das Sprungziel im Artikel (OutlineAnchors) und adressiert abschnittsweise
 * Updates. Nur neue Zeilen bekommen beim Speichern eine neue, noch freie id.
 *
 * Regeln nach design/guide-dashboard.md §5.4: erste Ueberschrift ist H2,
 * 3 bis 8 H2, hoechstens 4 H3 je H2, keine leere und keine doppelte
 * Ueberschrift.
 */
final class OutlineDraft
{
    public const MIN_H2 = 3;

    public const MAX_H2 = 8;

    public const MAX_H3_PER_H2 = 4;

    /**
     * Eingabegrenze im Editor (§5.4). Gespeichert werden Ueberschriften bis
     * HeadingsParser::MAX_HEADING_LENGTH, damit importierte laengere
     * Ueberschriften eine Gliederung nicht unsperrbar machen.
     */
    public const MAX_HEADING_LENGTH = 70;

    /**
     * @param  array<int, mixed>|null  $outline  guide_topics.outline_json
     * @return list<array{key: string, id: string|null, level: int, heading: string, original: string|null}>
     */
    public static function rows(?array $outline): array
    {
        return array_map(static fn (array $entry): array => [
            'key' => $entry['id'],
            'id' => $entry['id'],
            'level' => $entry['level'],
            'heading' => $entry['text'],
            'original' => $entry['text'],
        ], OutlineAnchors::flatten($outline));
    }

    /**
     * @return array{key: string, id: null, level: int, heading: string, original: null}
     */
    public static function newRow(int $level): array
    {
        return [
            'key' => 'neu-'.Str::lower(Str::random(8)),
            'id' => null,
            'level' => $level === 3 ? 3 : 2,
            'heading' => '',
            'original' => null,
        ];
    }

    /**
     * Zeilen in die Form von guide_topics.outline_json; neue Zeilen bekommen
     * eine freie id (H2: s<n>, H3: <id der H2>-<n>).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{id: string, level: int, heading: string, children: list<array{id: string, level: int, heading: string}>}>
     */
    public static function toOutline(array $rows): array
    {
        $used = [];

        foreach ($rows as $row) {
            if (filled($row['id'] ?? null)) {
                $used[(string) $row['id']] = true;
            }
        }

        $outline = [];

        foreach (array_values($rows) as $row) {
            $heading = self::clean((string) ($row['heading'] ?? ''));
            $level = (int) ($row['level'] ?? 2) === 3 ? 3 : 2;
            $parent = array_key_last($outline);

            // Eine H3 ohne vorherige H2 kann es nach validate() nicht geben;
            // zur Sicherheit wird sie zur H2.
            if ($level === 3 && $parent === null) {
                $level = 2;
            }

            $id = filled($row['id'] ?? null) ? (string) $row['id'] : null;

            if ($level === 2) {
                $id ??= self::freeId('s', $used, count($outline) + 1);
                $outline[] = ['id' => $id, 'level' => 2, 'heading' => $heading, 'children' => []];

                continue;
            }

            $parentId = $outline[$parent]['id'];
            $id ??= self::freeId("{$parentId}-", $used, count($outline[$parent]['children']) + 1);
            $outline[$parent]['children'][] = ['id' => $id, 'level' => 3, 'heading' => $heading];
        }

        return $outline;
    }

    /**
     * Verletzte Regeln: Zeilenschluessel => Meldung, allgemeine Meldungen
     * unter dem Schluessel ''.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, list<string>>
     */
    public static function violations(array $rows): array
    {
        $errors = [];
        $rows = array_values($rows);

        if ($rows === []) {
            return ['' => [__('Die Gliederung ist leer.')]];
        }

        if ((int) ($rows[0]['level'] ?? 2) !== 2) {
            $errors[(string) $rows[0]['key']][] = __('Die erste Überschrift muss eine H2 sein.');
        }

        $seen = [];
        $h2 = 0;
        $h3 = 0;
        $currentH2 = null;

        foreach ($rows as $row) {
            $key = (string) $row['key'];
            $heading = self::clean((string) ($row['heading'] ?? ''));

            if ($heading === '') {
                $errors[$key][] = __('Überschrift ist leer.');
            } elseif (mb_strlen($heading) > HeadingsParser::MAX_HEADING_LENGTH) {
                $errors[$key][] = __('Höchstens :max Zeichen.', ['max' => HeadingsParser::MAX_HEADING_LENGTH]);
            }

            $normalized = mb_strtolower($heading);

            if ($heading !== '' && isset($seen[$normalized])) {
                $errors[$key][] = __('Diese Überschrift gibt es schon.');
            }

            $seen[$normalized] = true;

            if ((int) ($row['level'] ?? 2) === 2) {
                $h2++;
                $h3 = 0;
                $currentH2 = $key;

                continue;
            }

            $h3++;

            if ($currentH2 !== null && $h3 === self::MAX_H3_PER_H2 + 1) {
                $errors[$currentH2][] = __('Höchstens :max Unterüberschriften (H3) je H2.', ['max' => self::MAX_H3_PER_H2]);
            }
        }

        if ($h2 < self::MIN_H2 || $h2 > self::MAX_H2) {
            $errors[''][] = __(':min bis :max Überschriften H2 erforderlich, derzeit :count.', [
                'min' => self::MIN_H2,
                'max' => self::MAX_H2,
                'count' => $h2,
            ]);
        }

        return $errors;
    }

    /**
     * Zeilen, deren Text sich gegenueber der gespeicherten Fassung geaendert
     * hat, und neue Zeilen.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function changedCount(array $rows): int
    {
        return count(array_filter($rows, static fn (array $row): bool => ($row['original'] ?? null) === null
            || self::clean((string) $row['heading']) !== (string) $row['original']));
    }

    public static function clean(string $heading): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $heading));
    }

    /**
     * @param  array<string, bool>  $used  wird ergaenzt
     */
    private static function freeId(string $prefix, array &$used, int $start): string
    {
        $number = max(1, $start);

        while (isset($used["{$prefix}{$number}"])) {
            $number++;
        }

        $used["{$prefix}{$number}"] = true;

        return "{$prefix}{$number}";
    }
}
