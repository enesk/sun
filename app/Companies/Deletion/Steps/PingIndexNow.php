<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Guide\Publishing\IndexNowClient;
use Throwable;

/**
 * IndexNow-Ping fuer die geloeschte Profil-URL (#8).
 *
 * Erreicht Bing, Yandex und Seznam; die holen die URL ab, bekommen den 410
 * und nehmen sie aus dem Index. Google nimmt IndexNow nicht an und erfaehrt
 * die Entfernung ueber Sitemap und 410.
 *
 * Derselbe Client wie im Ratgebersystem — der Schluessel je Mandant und die
 * Schluesseldatei unter `/<key>.txt` gelten portalweit, nicht pro Inhaltsart.
 * Fuer eine Loeschung bleibt der Ping ein Hinweis, keine Garantie: er wird
 * protokolliert und darf nie einen anderen Schritt aufhalten.
 */
class PingIndexNow implements DeletionStep
{
    public function __construct(private readonly IndexNowClient $client) {}

    public function key(): string
    {
        return 'ping_indexnow';
    }

    public function label(): string
    {
        return 'IndexNow melden';
    }

    public function plan(DeletionContext $context): string
    {
        if (! $this->client->isEnabled()) {
            return 'IndexNow ist abgeschaltet (guide.publishing.indexnow.enabled) — kein Ping.';
        }

        $paths = $context->paths();

        return $paths === []
            ? 'Keine Profil-URL ermittelbar — kein Ping.'
            : 'An api.indexnow.org melden: '.implode(', ', $paths);
    }

    public function run(DeletionContext $context): StepResult
    {
        $paths = $context->paths();

        if ($paths === []) {
            return StepResult::skipped('Keine Profil-URL ermittelbar.');
        }

        try {
            $result = $this->client->submit($context->tenant, $paths);
        } catch (Throwable $exception) {
            return StepResult::failed('IndexNow-Ping fehlgeschlagen: '.$exception->getMessage());
        }

        $payload = [
            'indexnow_status' => $result['status'],
            'http_status' => $result['http_status'],
            'urls' => $result['urls'],
            'pinged_at' => $result['pinged_at'],
        ];

        return match ($result['status']) {
            'ok' => StepResult::ok(sprintf('%d URL(s) an IndexNow gemeldet.', count($result['urls'])), $payload),
            'skipped' => StepResult::skipped((string) ($result['message'] ?? 'IndexNow übersprungen.'), $payload),
            default => StepResult::failed((string) ($result['message'] ?? 'IndexNow-Meldung fehlgeschlagen.'), $payload),
        };
    }
}
