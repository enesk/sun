<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\View\Components\Sun\AdSlot;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Die Einwilligungs-Cookies muessen serverseitig lesbar sein (#21).
 *
 * Geschrieben werden sie im Browser mit `document.cookie`, also im Klartext.
 * Ohne Ausnahme in EncryptCookies setzt Laravel jeden Wert, den es nicht
 * entschluesseln kann, im Cookie-Beutel auf null — und `null` sieht aus wie
 * „nicht eingewilligt". Der Fehler tarnt sich damit als korrektes Verhalten:
 * Werbung bleibt aus, niemand sucht nach einer Ursache, und bei einer
 * Umstellung auf „nur mit Einwilligung ausspielen" waere nie etwas
 * ausgespielt worden.
 *
 * Deshalb pruefen diese Tests beides: die Ausnahme selbst und dass kein
 * zweiter Leseweg (`$_COOKIE`) den Fehler wieder verdeckt.
 */
class ConsentCookieTest extends TestCase
{
    /** Die Cookies, die der Hinweis im Browser setzt. */
    private const COOKIES = [
        'cookie_consent_given',
        'cookie_consent_statistics',
        'cookie_consent_marketing',
    ];

    public function test_die_drei_einwilligungs_cookies_sind_von_der_verschluesselung_ausgenommen(): void
    {
        $middleware = app(EncryptCookies::class);
        $istAusgenommen = new ReflectionMethod(EncryptCookies::class, 'isDisabled');
        $istAusgenommen->setAccessible(true);

        foreach (self::COOKIES as $cookie) {
            $this->assertTrue(
                $istAusgenommen->invoke($middleware, $cookie),
                "{$cookie} wird verschluesselt — request()->cookie() liefert dann immer null.",
            );
        }
    }

    public function test_andere_cookies_bleiben_verschluesselt(): void
    {
        // Die Ausnahme ist eine Ausnahme: Sitzung und Erinnerungs-Cookie
        // duerfen nicht mit herausfallen.
        $middleware = app(EncryptCookies::class);
        $istAusgenommen = new ReflectionMethod(EncryptCookies::class, 'isDisabled');
        $istAusgenommen->setAccessible(true);

        foreach ([config('session.cookie'), 'remember_web', 'XSRF-TOKEN'] as $cookie) {
            $this->assertFalse(
                $istAusgenommen->invoke($middleware, (string) $cookie),
                "{$cookie} darf nicht unverschluesselt bleiben.",
            );
        }
    }

    public function test_die_werbeanker_lesen_den_klartextwert_der_anfrage(): void
    {
        $this->assertTrue($this->consentFor('1'), 'Mit cookie_consent_marketing=1 gilt die Einwilligung.');
        $this->assertFalse($this->consentFor('0'), 'Mit 0 nicht.');
        $this->assertFalse($this->consentFor(null), 'Ohne Cookie nicht.');
    }

    public function test_es_gibt_keinen_zweiten_leseweg_mehr(): void
    {
        // Der Rueckfall auf $_COOKIE war die Notloesung, solange die Ausnahme
        // fehlte. Mit zwei Lesewegen faellt ein kuenftiger Fehler in der
        // Middleware nicht mehr auf — deshalb darf es ihn nicht geben.
        foreach ([
            app_path('View/Components/Sun/AdSlot.php'),
            app_path('View/Components/Sun/AutoAds.php'),
        ] as $datei) {
            // Gesucht wird der Zugriff mit Klammer, nicht das Wort: ein
            // Hinweis im Kommentar soll den Test nicht rot machen.
            $this->assertStringNotContainsString(
                '$_COOKIE[',
                (string) file_get_contents($datei),
                basename($datei).': Rueckfall auf das Rohfeld ist nicht mehr noetig und verdeckt Fehler.',
            );
        }
    }

    public function test_die_ausnahmeliste_steht_in_bootstrap_app(): void
    {
        // Sie gehoert dorthin, weil sie fuer ALLE Routen gelten muss. Eine
        // Liste in einem Panel-Provider wuerde nur dessen Routen betreffen
        // und genau die Portal-Seiten verfehlen, um die es hier geht.
        $this->assertStringContainsString(
            'encryptCookies(except:',
            (string) file_get_contents(base_path('bootstrap/app.php')),
        );
    }

    private function consentFor(?string $wert): bool
    {
        $request = Request::create('https://elektrikerportal.com/', 'GET', [], $wert === null ? [] : [
            AdSlot::CONSENT_COOKIE => $wert,
        ]);

        $this->app->instance('request', $request);

        // consentGranted() ist privat und soll es bleiben — die Methode ist
        // ein Detail der Komponente, nicht ihre Schnittstelle.
        $methode = new ReflectionMethod(AdSlot::class, 'consentGranted');
        $methode->setAccessible(true);

        return (bool) $methode->invoke($this->leereKomponente());
    }

    private function leereKomponente(): AdSlot
    {
        // Ein echter Katalogschluessel: ein unbekannter laesst den Konstruktor
        // in der Testumgebung absichtlich werfen (failOnUnknownKey).
        return new AdSlot(key: 'home_after_hero');
    }
}
