<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Signierte Vorschau-Adresse eines Artikelentwurfs (#20).
 *
 * Die Vorschau muss den Artikel im echten Frontend-Template des Mandanten
 * zeigen (#17) — sie liegt also auf der Portaldomain, nicht auf der
 * Zentraldomain des Content-Panels. Damit steht die Sitzung des
 * `content`-Guards dort nicht zur Verfuegung: die 24 Portale sind eigene
 * Domains, das Sitzungscookie von /content reicht nicht hinueber.
 *
 * Die Adresse traegt deshalb den pruefenden Redaktions-Account als Parameter
 * `pruefer` mit und ist signiert. Erzeugen kann sie nur, wer im Panel
 * angemeldet ist; die Middleware auf der Portalseite prueft Signatur,
 * Ablauf, Aktivstatus des Accounts und dessen Portalzuordnung erneut.
 * Ohne gueltige Signatur ist die Seite nicht erreichbar.
 */
final class ContentPreviewLink
{
    public const USER_PARAM = 'pruefer';

    /**
     * Gueltigkeit der Adresse in Minuten. Kurz genug, dass ein
     * weitergegebener Link nicht dauerhaft traegt, lang genug fuer eine
     * Pruefsitzung.
     */
    public const TTL_MINUTES = 60;

    public function for(Tenant $tenant, ArticleDraft $draft, ?int $contentUserId = null): ?string
    {
        $domain = trim((string) $tenant->domain);

        if ($domain === '') {
            return null;
        }

        // Bewusst ueber den Guard und nicht ueber filament()->auth(): der
        // Link wird auch ausserhalb einer Panel-Anfrage gebaut (Konsole,
        // Queue), dort gaebe es kein Panel.
        $contentUserId ??= (int) Auth::guard((string) config('content.panel.guard', 'content'))->id();

        URL::forceRootUrl($this->scheme().'://'.$domain);

        try {
            return URL::temporarySignedRoute(
                'ratgeber.preview',
                now()->addMinutes(self::TTL_MINUTES),
                [
                    'draft' => (int) $draft->getKey(),
                    self::USER_PARAM => $contentUserId,
                ],
            );
        } finally {
            // Der erzwungene Wurzelpfad gilt sonst fuer den Rest der Anfrage —
            // und damit fuer jede weitere route()-Ausgabe im Panel.
            URL::forceRootUrl(null);
        }
    }

    private function scheme(): string
    {
        return app()->environment('production') ? 'https' : 'http';
    }
}
