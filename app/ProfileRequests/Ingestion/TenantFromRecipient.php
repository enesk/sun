<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use App\Models\Tenant;
use Webklex\PHPIMAP\Message;

/**
 * Zielportal einer eingegangenen Mail (SUN-RQ, Ticket #5).
 *
 * Alle Portal-Postfaecher leiten auf ein Sammelpostfach; welches Portal
 * gemeint war, steht danach nur noch in den Empfaengerkopfzeilen. Gelesen
 * werden sie in der Reihenfolge aus config
 * `profile-requests.mailbox.portal_headers` — Delivered-To zuerst, weil der
 * weiterleitende Server sie setzt und sie deshalb die urspruengliche
 * Adresse traegt; To zuletzt, weil ein Absender dort alles hineinschreiben
 * kann.
 *
 * Die Zuordnung geht ueber die Domain der Adresse auf `tenants.domain`.
 * Findet eine Kopfzeile genau ein Portal, ist es das. Findet sie mehrere
 * (Mail an zwei Portale), bleibt tenant_id leer und die Klassifikation (#6)
 * versucht es aus dem Inhalt — raten waere hier schlimmer als nichts
 * eintragen, denn eine Loeschung im falschen Portal ist nicht rueckholbar.
 *
 * Die Tenant-Liste wird je Prozess einmal geladen; ein Pollerlauf mit 50
 * Mails kostet damit eine Abfrage statt 50.
 */
class TenantFromRecipient
{
    /** @var array<string, int>|null  Domain in Kleinschreibung => tenants.id */
    private ?array $byDomain = null;

    /**
     * Portal und die Adresse, aus der es stammt.
     *
     * @return array{tenant_id: int|null, recipient: string|null}
     */
    public function resolve(Message $message): array
    {
        $eigene = $this->ownMailboxAddress();
        $map = $this->recipientMap();

        foreach ($this->headers() as $header) {
            $adressen = MailHeaders::addresses($message, $header);

            if ($adressen === []) {
                continue;
            }

            $treffer = [];
            $ersteFremde = null;

            foreach ($adressen as $adresse) {
                // Die Adresse des Sammelpostfachs selbst sagt nichts ueber
                // das Portal — sie steht in jeder weitergeleiteten Mail.
                if ($eigene !== null && $adresse === $eigene) {
                    continue;
                }

                $ersteFremde ??= $adresse;

                $tenantId = $map[$adresse] ?? $this->tenantIdForDomain($this->domainOf($adresse));

                if ($tenantId !== null) {
                    $treffer[$tenantId] = $adresse;
                }
            }

            if (count($treffer) === 1) {
                $tenantId = (int) array_key_first($treffer);

                return ['tenant_id' => $tenantId, 'recipient' => $treffer[$tenantId]];
            }

            if ($treffer !== []) {
                // Mehrdeutig: Adresse merken, Portal offen lassen.
                return ['tenant_id' => null, 'recipient' => $ersteFremde];
            }
        }

        return ['tenant_id' => null, 'recipient' => $this->fallbackRecipient($message, $eigene)];
    }

    /** @return list<string> */
    private function headers(): array
    {
        /** @var list<string> $header */
        $header = (array) config('profile-requests.mailbox.portal_headers', ['Delivered-To', 'To']);

        return $header;
    }

    private function fallbackRecipient(Message $message, ?string $eigene): ?string
    {
        foreach ($this->headers() as $header) {
            foreach (MailHeaders::addresses($message, $header) as $adresse) {
                if ($adresse !== $eigene) {
                    return $adresse;
                }
            }
        }

        return $eigene;
    }

    private function tenantIdForDomain(?string $domain): ?int
    {
        if ($domain === null) {
            return null;
        }

        return $this->domainIndex()[$domain] ?? null;
    }

    /** @return array<string, int> */
    private function domainIndex(): array
    {
        if ($this->byDomain !== null) {
            return $this->byDomain;
        }

        $index = [];

        foreach (Tenant::query()->whereNotNull('domain')->pluck('domain', 'id') as $id => $rohdomain) {
            $domain = $this->normalizeDomain((string) $rohdomain);

            if ($domain !== null) {
                $index[$domain] = (int) $id;
            }
        }

        return $this->byDomain = $index;
    }

    /**
     * Feste Zuordnung aus der Konfiguration, fuer Weiterleitungen ohne
     * brauchbare Kopfzeile. Werte duerfen tenants.id oder eine Domain sein.
     *
     * @return array<string, int>
     */
    private function recipientMap(): array
    {
        $map = [];

        foreach ((array) config('profile-requests.mailbox.recipient_tenant_map', []) as $adresse => $ziel) {
            $tenantId = is_numeric($ziel)
                ? (int) $ziel
                : $this->tenantIdForDomain($this->normalizeDomain((string) $ziel));

            if ($tenantId !== null) {
                $map[strtolower(trim((string) $adresse))] = $tenantId;
            }
        }

        return $map;
    }

    private function ownMailboxAddress(): ?string
    {
        $adresse = config('profile-requests.mailbox.address');

        return filled($adresse) ? strtolower(trim((string) $adresse)) : null;
    }

    private function domainOf(string $adresse): ?string
    {
        $teile = explode('@', $adresse);

        return $this->normalizeDomain($teile[1] ?? '');
    }

    /** Ohne Schema, ohne www., ohne Pfad und in Kleinschreibung. */
    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^[a-z]+://#', '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = (string) preg_replace('/^www\./', '', $domain);

        return $domain === '' ? null : $domain;
    }
}
