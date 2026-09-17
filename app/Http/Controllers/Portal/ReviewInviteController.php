<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Premium\ReviewInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bewertungslink /bewerten/{slug} (#12): leitet auf das Profil mit geoeffnetem
 * Bewertungsformular weiter; ref (z. B. qr) wird durchgereicht.
 */
class ReviewInviteController extends Controller
{
    public function __invoke(Request $request, string $companySlug, ReviewInviteService $invites): RedirectResponse
    {
        $company = $invites->resolveCompany($companySlug);

        abort_if($company === null, 404);

        $ref = $request->query('ref');
        $ref = is_string($ref) && preg_match('/^[a-z0-9_-]{1,32}$/', $ref) === 1 ? $ref : null;

        return redirect()->to($invites->formUrl($company, $ref));
    }
}
