<?php

declare(strict_types=1);

namespace App\Content\Providers;

use RuntimeException;

/**
 * Fehlerantwort der AdSense Management API v2 (Ticket #55).
 *
 * Bis #55 warf der {@see AdSenseClient} fuer jede Fehlerantwort eine nackte
 * RuntimeException mit Status und Rumpfausschnitt im Text. Fuer den Collector
 * genuegt das — er zaehlt den Fehlschlag und laesst die Werte null. Die
 * Gegenprobe des Zugangs (`ads:adsense-konto`) muss dagegen *unterscheiden*
 * koennen, denn drei verschiedene Handgriffe fuehren zum selben HTTP 403:
 *
 *   - die API ist im Cloud-Projekt des Dienstkontos nicht freigeschaltet,
 *   - das Dienstkonto ist im AdSense-Konto kein Nutzer,
 *   - der Bereich (Scope) des Tokens reicht nicht.
 *
 * Deshalb bleiben Status und Rumpf hier als Daten erhalten, statt nur im
 * Meldungstext zu stehen. Als Unterklasse von RuntimeException aendert das
 * nichts fuer bestehende Aufrufer, die `Throwable` fangen.
 */
final class AdSenseApiException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Ist die API im Cloud-Projekt des Dienstkontos gar nicht freigeschaltet?
     * Google antwortet darauf mit 403 und einem festen Satzanfang
     * ("AdSense Management API has not been used in project … before or it is
     * disabled."), der Projektnummer und Aktivierungs-Link enthaelt.
     */
    public function apiDisabled(): bool
    {
        return $this->status === 403
            && (str_contains($this->body, 'has not been used in project')
                || str_contains($this->body, 'SERVICE_DISABLED'));
    }

    /**
     * 403 ohne den Projekt-Hinweis: Token ist gueltig, das Dienstkonto darf
     * dieses Konto aber nicht lesen — es fehlt als Nutzer im AdSense-Konto.
     */
    public function accessDenied(): bool
    {
        return in_array($this->status, [401, 403], true) && ! $this->apiDisabled();
    }
}
