<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Ads\EditorToken;
use App\Ads\Http\Middleware\AdEditorMode as AdEditorModeMiddleware;
use App\Ads\Support\AdEditorMode as EditorFlag;
use App\View\Components\AdSlot as LegacyAdSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Die Middleware des Editor-Modus (#6).
 *
 * Drei Zustaende, und nur einer schaltet um: kein Token (normale Seite),
 * fauler Token (ebenfalls normale Seite — die Adresse steht im Verlauf) und
 * gueltiger Token (Rahmen, noindex, kein Altsystem).
 */
class AdEditorModeMiddlewareTest extends TestCase
{
    public function test_ohne_token_bleibt_alles_wie_es_ist(): void
    {
        $antwort = $this->durchlauf(Request::create('https://fliesenleger.io/'));

        $this->assertFalse(EditorFlag::active());
        $this->assertFalse(LegacyAdSlot::isSuppressed());
        $this->assertNull($antwort->headers->get('X-Robots-Tag'));
    }

    public function test_gueltiger_token_schaltet_den_editor_modus_ein(): void
    {
        $antwort = $this->durchlauf($this->mitToken('/firmen/muster-gmbh'));

        $this->assertTrue(EditorFlag::active());
        // Das Altsystem liefert in der Vorschau nichts aus — sonst stuende
        // echtes AdSense im Quelltext und Google zaehlt eine Impression.
        $this->assertTrue(LegacyAdSlot::isSuppressed());
        $this->assertSame('noindex, nofollow', $antwort->headers->get('X-Robots-Tag'));
    }

    public function test_abgelaufener_token_ergibt_die_normale_seite(): void
    {
        $anfrage = $this->mitToken('/');

        Carbon::setTestNow(Carbon::now()->addMinutes(EditorToken::minutes() + 1));

        $antwort = $this->durchlauf($anfrage);

        Carbon::setTestNow();

        $this->assertFalse(EditorFlag::active());
        $this->assertNull($antwort->headers->get('X-Robots-Tag'));
        $this->assertSame(200, $antwort->getStatusCode());
    }

    public function test_manipulierter_token_ergibt_die_normale_seite(): void
    {
        $anfrage = Request::create('https://fliesenleger.io/?'.http_build_query([
            EditorToken::EXPIRES_PARAM => Carbon::now()->addMinutes(10)->getTimestamp(),
            EditorToken::SIGNATURE_PARAM => str_repeat('b', 64),
        ]));

        $antwort = $this->durchlauf($anfrage);

        $this->assertFalse(EditorFlag::active());
        $this->assertSame(200, $antwort->getStatusCode());
    }

    public function test_schreibender_zugriff_mit_token_wird_abgewiesen(): void
    {
        $anfrage = $this->mitToken('/', method: 'POST');

        try {
            $this->durchlauf($anfrage);
            $this->fail('Ein POST mit Editor-Token muss abgewiesen werden.');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
        }

        $this->assertFalse(EditorFlag::active());
    }

    private function durchlauf(Request $anfrage): Response
    {
        EditorFlag::disable();

        return app(AdEditorModeMiddleware::class)->handle(
            $anfrage,
            fn (): Response => new Response('ok'),
        );
    }

    private function mitToken(string $pfad, string $method = 'GET'): Request
    {
        $host = 'fliesenleger.io';
        $expires = Carbon::now()->addMinutes(EditorToken::minutes())->getTimestamp();
        $parameter = [
            EditorToken::EXPIRES_PARAM => $expires,
            EditorToken::SIGNATURE_PARAM => hash_hmac(
                'sha256',
                $host.'|'.($pfad === '/' ? '/' : rtrim($pfad, '/')).'|'.$expires,
                (string) config('app.key'),
            ),
        ];

        return Request::create('https://'.$host.$pfad.'?'.http_build_query($parameter), $method);
    }
}
