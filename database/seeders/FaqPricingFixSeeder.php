<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\FAQ;
use Illuminate\Database\Seeder;

/**
 * Korrigiert zwei FAQ-Antworten aus dem alten FaqSeeder, die auf mehreren
 * Portalen falsche Konditionen nennen: 9,90 EUR im Monat bzw. 99 EUR im Jahr
 * und eine 30-taegige kostenlose Testphase. Tatsaechlich gelten die Preise aus
 * config('premium.reference_prices_cents') netto, mit 12 Monaten Laufzeit und
 * ohne Testphase (premium.trial_days = 0).
 *
 * Laeuft je Portal, aendert nur vorhandene Eintraege:
 *   php artisan tenants:run db:seed --option=class=FaqPricingFixSeeder --option=force=1
 */
class FaqPricingFixSeeder extends Seeder
{
    public function run(): void
    {
        $changed = 0;

        $changed += $this->replace(
            'kostet ein Firmeneintrag',
            'Der Basiseintrag ist dauerhaft kostenlos und ohne Laufzeit: Name, Adresse, Kontaktdaten, Öffnungszeiten, Beschreibung und Logo. Darüber hinaus gibt es kostenpflichtige Pakete mit mehr Funktionen – unter anderem werbefreies Profil, Fotogalerie, Antworten auf Bewertungen, Statistiken und Kundenanfragen direkt im Betriebsbereich. Die aktuellen Leistungen und Preise stehen auf der Seite für Betriebe.',
        );

        $changed += $this->replace(
            'Premium erst einmal kostenlos',
            'Eine kostenlose Testphase gibt es nicht. Dafür ist der Basiseintrag dauerhaft kostenlos und ohne Laufzeit – Sie können Ihr Profil also in Ruhe pflegen und Bewertungen sammeln, bevor Sie sich für ein Paket entscheiden. Die Konditionen der kostenpflichtigen Pakete stehen auf der Seite für Betriebe.',
        );

        $this->command?->info($changed.' FAQ-Antworten korrigiert.');
    }

    private function replace(string $needle, string $answer): int
    {
        return FAQ::where('question', 'like', "%{$needle}%")->update(['answer' => $answer]);
    }
}
