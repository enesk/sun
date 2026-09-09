<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Constants\TenantConfigConstants;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Response;

/**
 * Auskunftsseite des Recherche-Bots (#26, Crawler-Etikette).
 *
 * Der User-Agent der Quell-Connectoren traegt eine Adresse mit
 * ('SUN-ContentBot/1.0 (+https://<domain>/bot)'). Genau diese Adresse ruft
 * ein Betreiber auf, dessen Logs den Bot zeigen — sie muss erklaeren, wer
 * da abruft, warum, und wie man ihn los wird. Sie war bisher ein 404.
 *
 * Bewusst text/plain: die Seite richtet sich an Betreiber und Bots, nicht
 * an Leser des Portals, und soll ohne Theme und ohne Sitzung ausliefern.
 */
class BotInfoController extends Controller
{
    public function __invoke(): Response
    {
        /** @var ?Tenant $tenant */
        $tenant = tenant();

        $host = request()->getHost();
        $agent = str_replace(
            ':bot_url',
            'https://'.$host.'/'.trim((string) config('content.sources.http.bot_path', 'bot'), '/'),
            (string) config('content.sources.http.user_agent_template', 'SUN-ContentBot/1.0 (+:bot_url)'),
        );

        $contact = $tenant?->getAttribute(TenantConfigConstants::CONTACT_EMAIL);
        $portal = (string) ($tenant?->name ?? $host);

        $lines = [
            "SUN-ContentBot — Recherche-Bot von {$portal}",
            str_repeat('=', 60),
            '',
            'User-Agent: '.$agent,
            'Betreiber:  https://'.$host,
        ];

        if (is_string($contact) && trim($contact) !== '') {
            $lines[] = 'Kontakt:    '.trim($contact);
        }

        $lines = array_merge($lines, [
            '',
            'Wozu',
            '----',
            'Der Bot sammelt Signale für die Ratgeber-Redaktion dieses Portals:',
            'öffentliche Feeds, Förderdatenbanken, amtliche Statistiken und die',
            'Gliederung frei zugänglicher Ratgeberseiten zum selben Thema. Er',
            'kopiert keine Inhalte in das Portal und legt keine Volltexte an.',
            '',
            'Verhalten',
            '---------',
            '* robots.txt wird vor jedem Abruf je Host geprüft und beachtet.',
            '* Abrufe laufen einzeln und mit Pause, nie parallel gegen einen Host.',
            '* Timeout '.(int) config('content.sources.http.timeout', 15).' Sekunden, keine Wiederholung im selben Lauf.',
            '* Keine Formulare, keine Anmeldung, keine kostenpflichtigen Inhalte.',
            '',
            'Ausschließen',
            '------------',
            'In Ihre robots.txt eintragen:',
            '',
            '    User-agent: SUN-ContentBot',
            '    Disallow: /',
            '',
            'Die Sperre greift beim nächsten Lauf, spätestens nach '
                .(int) round(((int) config('content.sources.http.robots_cache_seconds', 21600)) / 3600).' Stunden.',
            '',
        ]);

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }
}
