<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Preview\PreviewTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Einstieg und Ausstieg der Vorschau (SUN-PV-001, Ticket #2).
 *
 * Beide Wege leiten auf die Startseite, damit der Token nicht in der
 * Adresszeile stehen bleibt. Ein ungueltiger Token bekommt bewusst keinen
 * Hinweis — die Antwort sieht aus wie bei jedem anderen Besucher.
 */
class PreviewController extends Controller
{
    public function __construct(
        private PreviewTokenService $previews,
    ) {}

    public function enter(Request $request, string $token): RedirectResponse
    {
        $response = redirect('/', 302)->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);

        $tenant = tenant();
        $link = $tenant ? $this->previews->findForTenant($token, $tenant) : null;

        if (! $link) {
            return $response;
        }

        $this->previews->recordUse($link);

        return $response->withCookie($this->previews->makeCookie($request, $link, $token));
    }

    public function leave(Request $request): RedirectResponse
    {
        return redirect('/', 302)
            ->withHeaders(['Cache-Control' => 'private, no-store'])
            ->withCookie($this->previews->forgetCookie($request));
    }
}
