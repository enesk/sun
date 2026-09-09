<?php

declare(strict_types=1);

namespace App\Content\Http\Middleware;

use App\Content\Models\Central\ContentUser;
use App\Content\Services\ContentPreviewLink;
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
 * 2. Der in der Adresse mitgefuehrte Redaktions-Account muss existieren,
 *    aktiv sein und dieses Portal sehen duerfen.
 *
 * Ist der `content`-Guard auf dieser Domain doch angemeldet (etwa weil Panel
 * und Portal dieselbe Domain teilen), gilt dessen Benutzer und der Parameter
 * wird nicht gebraucht. Ohne beides ist die Seite nicht erreichbar.
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
            abort(403, __('Die Vorschau ist nur für angemeldete Redaktions-Accounts erreichbar.'));
        }

        $tenantId = tenant() !== null ? (int) tenant()->getKey() : null;

        if ($tenantId !== null && ! $user->canAccessTenant($tenantId)) {
            abort(403, __('Für dieses Portal fehlt die Berechtigung.'));
        }

        $request->attributes->set('content_preview_user', $user);

        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function resolveUser(Request $request): ?ContentUser
    {
        $guard = Auth::guard((string) config('content.panel.guard', 'content'));

        $current = $guard->user();

        if ($current instanceof ContentUser && $current->is_active) {
            return $current;
        }

        $id = (int) $request->query(ContentPreviewLink::USER_PARAM, 0);

        if ($id <= 0) {
            return null;
        }

        return ContentUser::query()->active()->find($id);
    }
}
