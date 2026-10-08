<?php

namespace Tests\Unit\Turnstile;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Jede Livewire-Komponente, die ein Turnstile-Token validiert, muss die
 * zugehoerige Property besitzen (#52).
 *
 * Livewire loest den Feldnamen in `$this->validate([...])` gegen die
 * Properties auf und wirft sonst "No property found for validation" — eine
 * Ausnahme VOR der Regel, also ein 500 unabhaengig davon, ob der Bot-Schutz
 * eines Portals scharf ist. Genau so war die Firmeneintragung auf allen
 * Portalen unbenutzbar. Der Test prueft das fuer alle Komponenten auf einmal,
 * damit das Paar Ansicht/Komponente nicht an einer neuen Stelle wieder
 * auseinanderlaeuft.
 */
class TurnstileComponentPropertiesTest extends TestCase
{
    public function test_every_validated_turnstile_field_has_a_public_property(): void
    {
        $gepruefte = 0;

        foreach ($this->livewireKomponenten() as $klasse => $quelle) {
            foreach ($this->validierteFelder($quelle) as $feld) {
                $reflection = new ReflectionClass($klasse);

                $this->assertTrue(
                    $reflection->hasProperty($feld),
                    "{$klasse} validiert '{$feld}', hat die Property aber nicht — Livewire wirft dabei \"No property found for validation\"."
                );

                $property = $reflection->getProperty($feld);

                $this->assertTrue($property->isPublic(), "{$klasse}::\${$feld} muss public sein, sonst sieht Livewire sie nicht.");
                $this->assertSame('string', (string) $property->getType(), "{$klasse}::\${$feld} muss string sein.");

                $gepruefte++;
            }
        }

        // Sicherung gegen einen stillen Leerlauf, falls sich die Schreibweise
        // der Regel einmal aendert und das Muster unten nichts mehr findet.
        $this->assertGreaterThanOrEqual(7, $gepruefte, 'Es wurden kaum Turnstile-Pruefungen gefunden — das Suchmuster passt nicht mehr.');
    }

    /**
     * Feldnamen, die eine Komponente gegen Turnstile validiert: der Trait-Weg
     * (validateTurnstile) und die Rule direkt in einem validate()-Array.
     *
     * @return list<string>
     */
    private function validierteFelder(string $quelle): array
    {
        $felder = [];

        if (str_contains($quelle, '$this->validateTurnstile(')) {
            $felder[] = 'turnstileToken';
        }

        preg_match_all("/'([A-Za-z_][A-Za-z0-9_]*)'\s*=>\s*\[[^\]]*new TurnstileRule\(/", $quelle, $treffer);

        return array_values(array_unique([...$felder, ...$treffer[1]]));
    }

    /**
     * @return array<class-string, string> Klassenname => Quelltext
     */
    private function livewireKomponenten(): array
    {
        $komponenten = [];

        foreach (Finder::create()->files()->in(__DIR__.'/../../../app/Livewire')->name('*.php') as $datei) {
            $quelle = $datei->getContents();

            if (! preg_match('/^namespace\s+(.+);$/m', $quelle, $namespace)) {
                continue;
            }

            $klasse = $namespace[1].'\\'.$datei->getBasename('.php');

            if (class_exists($klasse)) {
                $komponenten[$klasse] = $quelle;
            }
        }

        return $komponenten;
    }
}
