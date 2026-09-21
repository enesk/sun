<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Models\ArticleVersion;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Signierte Vorschau-Adresse einer Artikelfassung (#16, Route guide.preview).
 *
 * Wie ContentPreviewLink (#20): die Vorschau liegt auf der Portaldomain, weil
 * sie im echten Frontend-Template des Portals rendert; die Sitzung des Panels
 * reicht dort nicht hin. Die Adresse traegt deshalb den pruefenden Benutzer
 * (Parameter `pruefer`) und ist zeitlich begrenzt signiert. Die Middleware
 * EnsureContentPreviewAccess prueft Signatur, Panelzugang und Portalzuordnung
 * erneut.
 */
final class GuidePreviewLink
{
    public function for(Tenant $tenant, ArticleVersion $version, ?int $userId = null): ?string
    {
        $domain = trim((string) $tenant->domain);

        if ($domain === '') {
            return null;
        }

        $userId ??= (int) Auth::guard((string) config('content.panel.guard', 'web'))->id();

        URL::forceRootUrl((app()->environment('production') ? 'https' : 'http').'://'.$domain);

        try {
            return URL::temporarySignedRoute(
                'guide.preview',
                now()->addMinutes(ContentPreviewLink::TTL_MINUTES),
                [
                    'version' => (int) $version->getKey(),
                    ContentPreviewLink::USER_PARAM => $userId,
                ],
            );
        } finally {
            // Sonst gilt der Wurzelpfad fuer jede weitere route()-Ausgabe im Panel.
            URL::forceRootUrl(null);
        }
    }
}
