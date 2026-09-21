<?php

declare(strict_types=1);

namespace App\Guide\Http\Middleware;

use App\Guide\Support\ContentPreviewLink;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zugang zur Artikelvorschau (#20).
 *
 * Die Vorschau liegt auf der Portaldomain, das Content-Panel auf der
 * Zentraldomain — eine gemeinsame Sitzung gibt es nicht. Deshalb gilt hier:
 *
 * 1. Die Adresse muss gueltig signiert und darf nicht abgelaufen sein
 *    (`signed`-Middleware, davor in der Route).
 * 2. Der in der Adresse mitgefuehrte Benutzer muss existieren und
 *    Administrator sein (users.is_admin).
 *
 * Ist auf dieser Domain bereits ein Administrator angemeldet, gilt dessen
 * Sitzung und der Parameter wird nicht gebraucht. Ein gewoehnlicher
 * Portalbenutzer reicht nicht. Ohne beides ist die Seite nicht erreichbar.
 *
 * Zusaetzlich setzt die Middleware `X-Robots-Tag: noindex, nofollow` — eine
 * Vorschau darf unter keinen Umstaenden in den Index geraten.
 */
class EnsureContentPreviewAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if ($user === null) {
            abort(403, __('Die Vorschau ist nur für angemeldete Administratoren erreichbar.'));
        }

        $tenantId = tenant() !== null ? (int) tenant()->getKey() : null;

        if ($tenantId !== null && ! $user->canAccessContentTenant($tenantId)) {
            abort(403, __('Für dieses Portal fehlt die Berechtigung.'));
        }

        $request->attributes->set('content_preview_user', $user);

        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function resolveUser(Request $request): ?User
    {
        $guard = Auth::guard((string) config('content.panel.guard', 'web'));

        $current = $guard->user();

        // Auf der Portaldomain ist am 'web'-Guard auch ein gewoehnlicher
        // Portalbenutzer denkbar — deshalb zaehlt hier nur ein Administrator.
        if ($current instanceof User && $current->canAccessContentPanel()) {
            return $current;
        }

        $id = (int) $request->query(ContentPreviewLink::USER_PARAM, 0);

        if ($id <= 0) {
            return null;
        }

        $user = User::query()->find($id);

        return $user instanceof User && $user->canAccessContentPanel() ? $user : null;
    }
}
