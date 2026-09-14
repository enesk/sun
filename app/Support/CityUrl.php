<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Portal\City;

/**
 * Einzige Quelle fuer Links auf die Stadtseite /staedte/{slug} (#6).
 *
 * Breadcrumbs, Stadtlisten und "Weitere Betriebe in ..."-Links zeigen immer
 * auf die Landingpage ohne Sortier- oder Filterparameter, damit sich die
 * Linkkraft nicht auf /firmen?city=...-Varianten verteilt. Filterlinks in der
 * Suchergebnis- und Stadtseite selbst bauen ihre URL weiterhin eigenstaendig.
 *
 * Der Slug kommt ausschliesslich aus der cities-Tabelle (eindeutig, auch bei
 * Namensdubletten) — nie aus dem Stadtnamen in der View sluggen.
 */
final class CityUrl
{
    public static function show(City $city): string
    {
        return self::fromSlug((string) $city->slug);
    }

    /**
     * Fuer vorbereitete Listen, die nur den gespeicherten Slug tragen.
     */
    public static function fromSlug(string $slug): string
    {
        return route('portal.cities.show', $slug);
    }
}
