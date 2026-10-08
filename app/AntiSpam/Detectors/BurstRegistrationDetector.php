<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\DisposableDomainList;
use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\BotCandidate;

/**
 * R9: Serie aus derselben Herkunft im selben Zeitfenster (#10).
 *
 * Drei Zaehlungen, eine genuegt (Grenzen in
 * `antispam.suspected_bots.burst`):
 *
 *   - `min_same_ip`     Datensaetze mit demselben IP-Hash im Fenster
 *   - `min_same_domain` Datensaetze mit derselben Mail-Domain im Fenster
 *   - `min_same_minute` Datensaetze derselben Minute, Herkunft egal
 *
 * Verglichen wird immer der HMAC-Hash der IP, nie die Adresse — die steht
 * nirgends im Bestand (docs/turnstile.md §1.4). Fuer alles, was vor #8 angelegt
 * wurde, ist der Hash leer; dort tragen nur die beiden anderen Zaehlungen.
 * Freie Mail-Domains sind ausgenommen, sonst waere gmail.com mit 62 Konten
 * eine Serie.
 */
final class BurstRegistrationDetector implements Detector
{
    /**
     * Domains, bei denen eine Haeufung nichts bedeutet.
     *
     * @var list<string>
     */
    private const FREE_DOMAINS = [
        'gmail.com', 'googlemail.com', 'gmx.de', 'gmx.net', 'gmx.at', 'gmx.ch',
        'web.de', 't-online.de', 'icloud.com', 'me.com', 'outlook.com',
        'outlook.de', 'hotmail.com', 'hotmail.de', 'live.de', 'yahoo.com',
        'yahoo.de', 'freenet.de', 'aol.com', 'posteo.de', 'mailbox.org',
        'mail.ru', 'protonmail.com', 'proton.me',
    ];

    public function code(): string
    {
        return 'burst_registration';
    }

    public function label(): string
    {
        return __('Serie aus derselben Herkunft');
    }

    public function supports(BotCandidate $candidate): bool
    {
        return true;
    }

    public function matches(BotCandidate $candidate): bool
    {
        $burst = $candidate->burst;

        if (filled($candidate->ipHash) && $burst->sameIp >= $this->limit('min_same_ip', 5)) {
            return true;
        }

        if ($burst->sameMinute >= $this->limit('min_same_minute', 3)) {
            return true;
        }

        return ! $this->isFreeDomain($candidate->email)
            && $burst->sameDomain >= $this->limit('min_same_domain', 5);
    }

    private function isFreeDomain(?string $email): bool
    {
        $domain = DisposableDomainList::domainOf($email);

        return $domain === null || in_array($domain, self::FREE_DOMAINS, true);
    }

    private function limit(string $key, int $default): int
    {
        return max(2, AntiSpamConfig::int("suspected_bots.burst.{$key}", $default));
    }
}
