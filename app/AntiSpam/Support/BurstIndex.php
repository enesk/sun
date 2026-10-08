<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\AntiSpam\DisposableDomainList;
use DateTimeInterface;

/**
 * Zaehlt Datensaetze je Herkunft und Zeitfenster (#10, R9).
 *
 * Wird je Portal einmal aus den geladenen Zeilen gebaut; die Regel fragt
 * danach nur noch ab. Gezaehlt wird in festen Eimern (`window_minutes` fuer
 * IP-Hash und Mail-Domain, eine Minute fuer die Welle), nicht in einem
 * gleitenden Fenster: feste Eimer sind reproduzierbar — zwei Laeufe ueber
 * denselben Bestand ergeben dieselben Zahlen, und ein Treffer laesst sich im
 * Bericht nachrechnen.
 *
 * Die Kante eines Eimers kann eine Serie teilen (drei Anmeldungen um 09:59,
 * zwei um 10:01). Das ist der Preis und in Kauf genommen: die Regel ist
 * Vorsorge, und die Schwelle wird ohnehin nur mit einer zweiten Regel
 * erreicht.
 */
final class BurstIndex
{
    /** @var array<string, int> */
    private array $byIp = [];

    /** @var array<string, int> */
    private array $byDomain = [];

    /** @var array<string, int> */
    private array $byMinute = [];

    private function __construct(
        private readonly int $windowSeconds,
    ) {}

    /**
     * @param  iterable<array{created_at: DateTimeInterface|null, ip_hash: string|null, email: string|null}>  $rows
     */
    public static function build(iterable $rows): self
    {
        $index = new self(60 * max(1, AntiSpamConfig::int('suspected_bots.burst.window_minutes', 60)));

        foreach ($rows as $row) {
            $index->add($row['created_at'], $row['ip_hash'], $row['email']);
        }

        return $index;
    }

    public function countsFor(?DateTimeInterface $at, ?string $ipHash, ?string $email): BurstCounts
    {
        if ($at === null) {
            return new BurstCounts;
        }

        $domain = DisposableDomainList::domainOf($email);

        return new BurstCounts(
            sameIp: $ipHash === null ? 1 : ($this->byIp[$this->key($ipHash, $at, $this->windowSeconds)] ?? 1),
            sameMinute: $this->byMinute[$this->key('', $at, 60)] ?? 1,
            sameDomain: $domain === null ? 1 : ($this->byDomain[$this->key($domain, $at, $this->windowSeconds)] ?? 1),
        );
    }

    private function add(?DateTimeInterface $at, ?string $ipHash, ?string $email): void
    {
        if ($at === null) {
            return;
        }

        $minute = $this->key('', $at, 60);
        $this->byMinute[$minute] = ($this->byMinute[$minute] ?? 0) + 1;

        if ($ipHash !== null && $ipHash !== '') {
            $key = $this->key($ipHash, $at, $this->windowSeconds);
            $this->byIp[$key] = ($this->byIp[$key] ?? 0) + 1;
        }

        $domain = DisposableDomainList::domainOf($email);

        if ($domain !== null) {
            $key = $this->key($domain, $at, $this->windowSeconds);
            $this->byDomain[$key] = ($this->byDomain[$key] ?? 0) + 1;
        }
    }

    private function key(string $prefix, DateTimeInterface $at, int $seconds): string
    {
        return $prefix.'|'.intdiv($at->getTimestamp(), $seconds);
    }
}
