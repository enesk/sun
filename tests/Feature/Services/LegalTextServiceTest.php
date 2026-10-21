<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LegalTextKey;
use App\Models\LegalText;
use App\Services\LegalTextService;
use Tests\Feature\FeatureTest;

/**
 * Zentrale Rechtstexte (Ticket #17).
 *
 * Der Test sichert vor allem die Zusage ab, die im Panel steht: Solange eine
 * Textart zentral leer ist, gilt weiter der Text des Portals. Daran haengt,
 * dass das Einspielen dieser Version allein noch nichts an der Auslieferung
 * aendert.
 */
class LegalTextServiceTest extends FeatureTest
{
    private function service(): LegalTextService
    {
        LegalText::query()->delete();

        return app()->make(LegalTextService::class);
    }

    public function test_leerer_zustand_liefert_null_und_meldet_alle_arten_als_fehlend(): void
    {
        $service = $this->service();

        foreach (LegalTextKey::all() as $key) {
            $this->assertNull($service->get($key), "Ohne Pflege muss {$key->value} null sein.");
        }

        $this->assertFalse($service->isComplete());
        $this->assertCount(count(LegalTextKey::all()), $service->missing());
    }

    public function test_gespeicherter_text_wird_zurueckgegeben(): void
    {
        $service = $this->service();

        $service->set(LegalTextKey::IMPRESSUM, '<p>Angaben gemäß § 5 DDG</p>', 42);

        $this->assertSame('<p>Angaben gemäß § 5 DDG</p>', $service->get(LegalTextKey::IMPRESSUM));
        $this->assertSame(42, LegalText::query()->where('key', 'impressum')->value('updated_by'));

        // Die anderen Arten bleiben unberuehrt — sonst wuerde das Speichern
        // einer Art die Auslieferung der anderen umstellen.
        $this->assertNull($service->get(LegalTextKey::DATENSCHUTZ));
        $this->assertFalse($service->isComplete());
    }

    public function test_zweites_speichern_ersetzt_statt_eine_zweite_zeile_anzulegen(): void
    {
        $service = $this->service();

        $service->set(LegalTextKey::DATENSCHUTZ, '<p>alt</p>');
        $service->set(LegalTextKey::DATENSCHUTZ, '<p>neu</p>');

        $this->assertSame('<p>neu</p>', $service->get(LegalTextKey::DATENSCHUTZ));
        $this->assertSame(1, LegalText::query()->where('key', 'datenschutz')->count());
    }

    public function test_leerer_text_zaehlt_als_nicht_gepflegt(): void
    {
        $service = $this->service();

        // Ein Editor hinterlaesst beim Leeren gern Leerzeichen oder einen
        // leeren Absatz. Beides darf die Umstellung nicht ausloesen, sonst
        // steht auf allen Portalen eine leere Rechtstextseite.
        foreach (['', '   ', "\n\n"] as $leer) {
            $service->set(LegalTextKey::IMPRESSUM, $leer);

            $this->assertNull(
                $service->get(LegalTextKey::IMPRESSUM),
                'Ein Text aus Leerraum darf nicht als gepflegt gelten.',
            );
        }
    }

    public function test_all_liefert_jede_art_als_schluessel(): void
    {
        $service = $this->service();

        $service->set(LegalTextKey::EDITORIAL_PRINCIPLES, '<p>So arbeiten wir</p>');

        $alle = $service->all();

        foreach (LegalTextKey::all() as $key) {
            $this->assertArrayHasKey($key->value, $alle);
        }

        $this->assertSame('<p>So arbeiten wir</p>', $alle['editorial_principles']);
        $this->assertNull($alle['impressum']);
    }

    public function test_platzhalter_bleiben_im_gespeicherten_text_stehen(): void
    {
        $service = $this->service();

        // Der ganze Sinn der zentralen Fassung: Die Platzhalter duerfen erst
        // beim Ausliefern je Portal ersetzt werden, nicht beim Speichern.
        $vorlage = '<p>[BETREIBER_STRASSE], [BETREIBER_PLZ] [BETREIBER_ORT] — [BETREIBER_EMAIL]</p>';

        $service->set(LegalTextKey::IMPRESSUM, $vorlage);

        $this->assertSame($vorlage, $service->get(LegalTextKey::IMPRESSUM));
    }
}
