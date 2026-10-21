<?php

declare(strict_types=1);

namespace App\ProfileRequests\Verification;

use App\Models\Portal\Company;
use Illuminate\Support\Str;

/**
 * Domain-Match der Absenderadresse (SUN-RQ #6; Regel 1 in
 * docs/profile-requests.md 4).
 *
 * Trifft die Absenderadresse die beim Profil hinterlegte Firmen-E-Mail
 * genau, oder teilt sie deren Domain bzw. die Domain der Profil-Website,
 * gilt die Anfrage als vom Betrieb autorisiert — ohne Mail an den Betrieb.
 *
 * Die Einschraenkung, die das Verfahren erst brauchbar macht: auf einer
 * Freemail- oder Sammeldomain beweist das nichts. `info@gmail.com` und
 * `kontakt@gmail.com` haben dieselbe Domain und nichts miteinander zu tun.
 * Die Liste solcher Domains steht in
 * `config('profile-requests.verification.freemail_domains')` und wird um die
 * Pflegeliste in resources/profile-requests/generic-email-domains.txt
 * ergaenzt; nur eine **exakte** Adressgleichheit zaehlt dort auch nicht,
 * denn wer eine Mail faelscht, faelscht zuerst das From-Feld.
 *
 * Subdomains gelten als Treffer in **eine** Richtung: `mail.firma.de` passt
 * zu `firma.de`, aber nicht umgekehrt zu `firma.de.beispiel.com` — verglichen
 * wird auf Label-Grenzen, nie mit `str_contains`.
 */
class SenderDomainMatcher
{
    /** @var list<string>|null */
    private ?array $genericDomains = null;

    /**
     * Passt die Absenderadresse zum Profil? Grund der Entscheidung als
     * zweiter Rueckgabewert, damit er ins Protokoll kann.
     *
     * @return array{matched: bool, reason: string, company_email: string|null, domain: string|null}
     */
    public function check(?string $senderEmail, ?Company $company): array
    {
        $senderDomain = $this->domainOf($senderEmail);
        $companyEmail = $company === null ? null : $this->normalizeEmail($company->email);

        $result = [
            'matched' => false,
            'reason' => 'kein_treffer',
            'company_email' => $companyEmail,
            'domain' => $senderDomain,
        ];

        if ($senderDomain === null) {
            return ['reason' => 'kein_absender'] + $result;
        }

        if ($company === null) {
            return ['reason' => 'kein_betrieb'] + $result;
        }

        if ($this->isGeneric($senderDomain)) {
            return ['reason' => 'freemail_domain'] + $result;
        }

        if ($companyEmail !== null && $this->normalizeEmail($senderEmail) === $companyEmail) {
            return ['matched' => true, 'reason' => 'adresse_identisch'] + $result;
        }

        $companyDomain = $this->domainOf($companyEmail);

        if ($companyDomain !== null && $this->sameDomain($senderDomain, $companyDomain)) {
            return ['matched' => true, 'reason' => 'domain_der_firmen_mail'] + $result;
        }

        $websiteHost = $this->hostOfWebsite($company->website);

        if ($websiteHost !== null && ! $this->isGeneric($websiteHost) && $this->sameDomain($senderDomain, $websiteHost)) {
            return ['matched' => true, 'reason' => 'domain_der_website'] + $result;
        }

        return $result;
    }

    public function matches(?string $senderEmail, ?Company $company): bool
    {
        return $this->check($senderEmail, $company)['matched'];
    }

    /**
     * Domain einer Adresse in Kleinschreibung, ohne fuehrenden Punkt und
     * ohne `www.`.
     */
    public function domainOf(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        return $this->normalizeHost(substr($email, $at + 1));
    }

    /**
     * Steht die Domain auf der Freemail-Liste? Eine Subdomain eines
     * Freemail-Anbieters zaehlt mit (`mail.gmx.de`).
     */
    public function isGeneric(string $domain): bool
    {
        $domain = $this->normalizeHost($domain) ?? '';

        foreach ($this->generic() as $generic) {
            if ($domain === $generic || str_ends_with($domain, '.'.$generic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Freemail-Domains aus Config und Pflegeliste, ohne Dubletten.
     *
     * @return list<string>
     */
    public function generic(): array
    {
        if ($this->genericDomains !== null) {
            return $this->genericDomains;
        }

        $fromConfig = array_map(
            fn ($domain): string => (string) $this->normalizeHost((string) $domain),
            (array) config('profile-requests.verification.freemail_domains', []),
        );

        $domains = array_merge($fromConfig, $this->fromFile());

        $this->genericDomains = array_values(array_unique(array_filter($domains)));

        return $this->genericDomains;
    }

    /**
     * Host einer Website-Adresse. `firma.de` ohne Schema kommt in den
     * Profilen haeufig vor, deshalb der Zusatz.
     */
    public function hostOfWebsite(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        if (! Str::contains($website, '//')) {
            $website = 'https://'.$website;
        }

        $host = parse_url($website, PHP_URL_HOST);

        return is_string($host) ? $this->normalizeHost($host) : null;
    }

    /**
     * Gleiche Domain oder Subdomain davon — auf Label-Grenzen verglichen,
     * und nur in **eine** Richtung.
     *
     * `mail.firma.de` passt zu `firma.de`, weil der Absender damit
     * unterhalb der Domain des Profils liegt. Der umgekehrte Fall ist
     * gestrichen (Security-Review #12, Befund 1): bei einem Profil auf einer
     * Anbieter-Subdomain — `kontakt@kunde123.anbieter.de`, wie es
     * Homepage-Baukaesten vergeben — wuerde sonst jede Adresse der
     * Anbieterdomain als Inhaber des Profils gelten. Die Anbieterdomain
     * selbst steht auf keiner Freemail-Liste, der Treffer fiele also durch
     * jede andere Pruefung.
     */
    private function sameDomain(string $sender, string $company): bool
    {
        if ($sender === $company) {
            return true;
        }

        return str_ends_with($sender, '.'.$company);
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function normalizeHost(string $host): ?string
    {
        $host = Str::lower(trim($host));
        $host = trim($host, ".\t\n\r \0\x0B");
        $host = (string) preg_replace('/^www\./', '', $host);

        return $host === '' ? null : $host;
    }

    /**
     * @return list<string>
     */
    private function fromFile(): array
    {
        $relative = (string) config('profile-requests.verification.generic_domains_file', '');

        if ($relative === '') {
            return [];
        }

        $path = resource_path($relative);

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $domains = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $domain = $this->normalizeHost(ltrim($line, '@'));

            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }
}
