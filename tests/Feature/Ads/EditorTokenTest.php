<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Ads\EditorToken;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Der signierte Token des Editor-Modus (#6).
 *
 * Gebunden ist er an Host, Pfad und Ablauf. Alles andere — ein fremdes
 * Portal, eine andere Seite, eine abgelaufene oder von Hand veraenderte
 * Adresse — muss als „kein Editor-Modus" enden, nicht als Fehlerseite: die
 * Adresse landet im Verlauf des Browsers.
 */
class EditorTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ad-slots.editor_token_minutes', 30);
    }

    public function test_frischer_token_gilt(): void
    {
        $this->assertTrue(EditorToken::valid($this->anfrage('fliesenleger.io', '/firmen/muster-gmbh')));
    }

    public function test_token_eines_anderen_portals_gilt_nicht(): void
    {
        $anfrage = $this->anfrage('fliesenleger.io', '/', host: 'malerfinder.de');

        $this->assertFalse(EditorToken::valid($anfrage));
    }

    public function test_token_einer_anderen_seite_gilt_nicht(): void
    {
        $anfrage = $this->anfrage('fliesenleger.io', '/firmen/muster-gmbh', pfad: '/firmen/andere-gmbh');

        $this->assertFalse(EditorToken::valid($anfrage));
    }

    public function test_abgelaufener_token_gilt_nicht(): void
    {
        $anfrage = $this->anfrage('fliesenleger.io', '/');

        Carbon::setTestNow(Carbon::now()->addMinutes(31));

        $this->assertFalse(EditorToken::valid($anfrage));

        Carbon::setTestNow();
    }

    public function test_veraenderte_signatur_gilt_nicht(): void
    {
        $anfrage = $this->anfrage('fliesenleger.io', '/', signatur: str_repeat('a', 64));

        $this->assertFalse(EditorToken::valid($anfrage));
    }

    public function test_ohne_token_ist_nichts_vorhanden(): void
    {
        $anfrage = Request::create('https://fliesenleger.io/');

        $this->assertFalse(EditorToken::present($anfrage));
        $this->assertFalse(EditorToken::valid($anfrage));
    }

    /**
     * Zusaetzliche Parameter der Seite — Suche, Seitenzahl — duerfen die
     * Signatur nicht brechen: die Vorschau der Suchseite filtert.
     */
    public function test_weitere_parameter_brechen_die_signatur_nicht(): void
    {
        $anfrage = $this->anfrage('fliesenleger.io', '/firmen', zusatz: ['q' => 'bad', 'seite' => '2']);

        $this->assertTrue(EditorToken::valid($anfrage));
    }

    public function test_schlusszeichen_im_pfad_aendert_nichts(): void
    {
        $anfrage = $this->anfrage('fliesenleger.io', '/firmen', pfad: '/firmen/');

        $this->assertTrue(EditorToken::valid($anfrage));
    }

    public function test_herkunft_wird_auf_schema_host_und_port_gekuerzt(): void
    {
        config()->set('ad-slots.editor_origin', 'https://panel.example.test/admin/werbeplaetze');

        $this->assertSame('https://panel.example.test', EditorToken::origin());

        config()->set('ad-slots.editor_origin', 'http://localhost:8000');

        $this->assertSame('http://localhost:8000', EditorToken::origin());
    }

    public function test_unbrauchbare_herkunft_ergibt_leeren_text(): void
    {
        // Leer heisst fuer das Modul im Portal: Bruecke laeuft nicht an. Eine
        // Vorschau ohne Gegenseite ist harmlos, eine ohne Herkunftspruefung
        // nicht.
        config()->set('ad-slots.editor_origin', 'panel.example.test');

        $this->assertSame('', EditorToken::origin());
    }

    /**
     * Baut eine Anfrage mit einem Token fuer ($signierterHost, $signierterPfad)
     * und ruft sie unter ($host, $pfad) ab — beide standardmaessig gleich.
     *
     * @param  array<string, string>  $zusatz
     */
    private function anfrage(
        string $signierterHost,
        string $signierterPfad,
        ?string $host = null,
        ?string $pfad = null,
        ?string $signatur = null,
        array $zusatz = [],
    ): Request {
        $expires = Carbon::now()->addMinutes(EditorToken::minutes())->getTimestamp();

        $parameter = $zusatz + [
            EditorToken::EXPIRES_PARAM => $expires,
            EditorToken::SIGNATURE_PARAM => $signatur ?? $this->signatur($signierterHost, $signierterPfad, $expires),
        ];

        return Request::create(
            'https://'.($host ?? $signierterHost).($pfad ?? $signierterPfad).'?'.http_build_query($parameter)
        );
    }

    private function signatur(string $host, string $pfad, int $expires): string
    {
        return hash_hmac('sha256', $host.'|'.$pfad.'|'.$expires, (string) config('app.key'));
    }
}
