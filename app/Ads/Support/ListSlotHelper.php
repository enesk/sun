<?php

declare(strict_types=1);

namespace App\Ads\Support;

/**
 * Listen-Anker im Loop (`*_in_feed_*`, Ticket #5).
 *
 * Ein Anker nach dem n-ten Listenelement ist in Blade schnell falsch: die
 * Bedingung wird an zwei Stellen gepflegt (Suche, Stadtseite), die Zahl steht
 * im Katalog-Schluessel und im Template, und die Regel "nie kurz vor dem
 * Listenende" (docs/ad-slots.md 2, Regel 6 / 3.4) wird gern vergessen. Darum
 * eine Stelle:
 *
 *     (at)php($feed = \App\Ads\Support\ListSlotHelper::make([
 *         3 => 'search_in_feed_3',
 *         8 => 'search_in_feed_8',
 *     ], $companies->count()))
 *
 *     (at)forelse($companies as $company)
 *       <x-sun.listing-card :company="$company" />
 *
 *       (at)if($slot = $feed->keyAfter($loop->iteration))
 *         <x-sun.ad-slot :key="$slot" />
 *
 *       (at)endif
 *
 *     (at)endforelse
 *
 * Der Helper entscheidet nur, OB an dieser Stelle ein Anker steht — nicht, ob
 * dort etwas ausgespielt wird. Das bleibt Sache der Komponente, damit der
 * Editor-Modus (#6) auch die leeren Positionen findet.
 */
final class ListSlotHelper
{
    /**
     * @param  array<int, string>  $anchors  Position im Loop (1-basiert) => Katalog-Schluessel
     * @param  int  $total  Elemente, die auf dieser Seite der Liste stehen
     * @param  int  $minFollowing  Mindestzahl Elemente, die hinter dem Anker
     *                             noch kommen muessen. Sonst steht die Anzeige
     *                             praktisch am Listenende und doppelt den
     *                             Anker ueber der Fusszeile.
     */
    private function __construct(
        private readonly array $anchors,
        private readonly int $total,
        private readonly int $minFollowing,
    ) {}

    /**
     * @param  array<int, string>  $anchors
     */
    public static function make(array $anchors, int $total, int $minFollowing = 2): self
    {
        // Im Editor-Modus (#6) zaehlt die Position, nicht die Laenge dieser
        // Liste: eine Stadt mit drei Betrieben versteckte `city_in_feed_3`
        // sonst auch in der Vorschau, und Enes koennte den Platz nicht
        // anklicken (docs/ad-slots.md 6.1 — alle Anker rendern einen Rahmen,
        // auch die, deren Bedingung sonst nicht greift). Erreicht die Liste
        // die Position gar nicht, bleibt der Anker auch hier aus; die
        // Ankerliste des Panels kommt aus dem Katalog und zeigt ihn trotzdem.
        if (AdEditorMode::active()) {
            $minFollowing = 0;
        }

        $gueltig = [];

        foreach ($anchors as $position => $slotKey) {
            // Ein Schluessel, den der Katalog nicht (mehr) kennt, faellt still
            // weg: die Komponente wuerde ihn in `local` als Fehler melden, und
            // eine Liste ist der falsche Ort dafuer.
            if ((int) $position > 0 && is_string($slotKey) && AdSlotCatalog::exists($slotKey)) {
                $gueltig[(int) $position] = $slotKey;
            }
        }

        return new self($gueltig, max(0, $total), max(0, $minFollowing));
    }

    /**
     * Katalog-Schluessel des Ankers, der hinter dieses Element gehoert; null,
     * wenn hier keiner steht.
     */
    public function keyAfter(int $position): ?string
    {
        $slotKey = $this->anchors[$position] ?? null;

        if ($slotKey === null) {
            return null;
        }

        return $this->total - $position >= $this->minFollowing ? $slotKey : null;
    }

    /** Steht dieser Anker auf dieser Seite der Liste ueberhaupt? */
    public function has(string $slotKey): bool
    {
        foreach ($this->anchors as $position => $key) {
            if ($key === $slotKey) {
                return $this->keyAfter($position) !== null;
            }
        }

        return false;
    }
}
