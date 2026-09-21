<?php

declare(strict_types=1);

namespace App\Guide\Http\Controllers;

use App\Guide\Support\ReviewShortcuts;
use Illuminate\Http\RedirectResponse;

/**
 * Schalter "Einzeltasten" im Nutzermenue des Content-Panels (#33, WCAG
 * 2.1.4): kehrt die Wahl um und fuehrt zur vorigen Seite zurueck.
 */
class ToggleReviewShortcuts
{
    public function __invoke(): RedirectResponse
    {
        $value = ReviewShortcuts::enabled() ? ReviewShortcuts::OFF : 'on';

        return redirect()
            ->back()
            ->withCookie(cookie()->forever(ReviewShortcuts::COOKIE, $value));
    }
}
