<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ai;

use App\Models\Portal\Company;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Firmensuche **vor** dem KI-Aufruf (SUN-RQ #6).
 *
 * Der Grund ist nicht Sparsamkeit, sondern Verlaesslichkeit: haette die KI
 * keine Liste, muesste sie eine `company_id` nennen, die sie nicht kennen
 * kann — und sie wuerde eine erfinden. Hier wird deshalb serverseitig in der
 * Tenant-DB des erkannten Portals gesucht, und die KI darf nur noch aus den
 * Treffern waehlen oder „keiner davon" sagen.
 *
 * Gesucht wird nach allem, was in einer Mail ueberhaupt verwertbar ist:
 *
 *  * **E-Mail** — Absenderadresse und jede Adresse im Text, exakt und ueber
 *    die Domain. Der staerkste Hinweis.
 *  * **Telefon** — auf Ziffern normalisiert verglichen. In den Profilen steht
 *    „0221 / 12 34 56" neben „+49 221 123456"; ohne Normalisierung findet
 *    man nichts. Verglichen werden die letzten Stellen, weil die Vorwahl
 *    in beiden Schreibweisen unterschiedlich gruppiert ist.
 *  * **Name** — Wortgruppen aus Betreff und erster Textzeile per LIKE, dazu
 *    die Signaturzeilen. Treffer werden mit `similar_text` bewertet, damit
 *    „Fliesen Mueller GmbH" nicht gleich gut passt wie „Fliesen Mueller".
 *
 * Die Bewertung ist absichtlich einfach und nachvollziehbar: sie sortiert nur
 * die Liste und entscheidet nichts. Die Zuordnung trifft die KI, und ob die
 * uebernommen wird, entscheidet `classification.min_confidence`.
 */
class CompanyCandidateFinder
{
    /** Hoechstzahl Zeilen, die je Suchweg aus der Tenant-DB geholt werden. */
    private const PER_STRATEGY_LIMIT = 25;

    /** So viele Endziffern einer Rufnummer muessen uebereinstimmen. */
    private const PHONE_SUFFIX_DIGITS = 7;

    public function __construct(
        private readonly TenantCompanyResolver $resolver,
    ) {}

    /**
     * Kandidaten zu einer Anfrage. Leeres Ergebnis, wenn das Portal unbekannt
     * ist — ohne Portal gibt es keine Datenbank, in der zu suchen waere, und
     * eine Suche ueber 23 Portale haette ohnehin keinen Aussagewert.
     *
     * @return list<array{id: int, name: string, address: string, city: string, tel: string, email: string, website: string, claimed: bool, score: float, matched_on: list<string>}>
     */
    public function forRequest(ProfileRequest $request): array
    {
        if ($request->tenant_id === null) {
            return [];
        }

        $signals = $this->signals($request);

        if ($signals['emails'] === [] && $signals['phones'] === [] && $signals['names'] === []) {
            return [];
        }

        $limit = max(1, (int) config('profile-requests.classification.max_candidates', 5));

        $candidates = $this->resolver->runFor((int) $request->tenant_id, function () use ($signals, $limit): array {
            return $this->search($signals, $limit);
        });

        return $candidates ?? [];
    }

    /**
     * Die Suchsignale einer Anfrage: Adressen, Rufnummern und Namensbrocken
     * aus Absender, Betreff und Text.
     *
     * @return array{emails: list<string>, phones: list<string>, names: list<string>}
     */
    public function signals(ProfileRequest $request): array
    {
        $text = (string) $request->message;

        $emails = $this->emails($text);

        if (filter_var((string) $request->requester_email, FILTER_VALIDATE_EMAIL) !== false) {
            array_unshift($emails, Str::lower((string) $request->requester_email));
        }

        $phones = $this->phones($text);

        if ($request->requester_phone !== null) {
            $digits = $this->digits($request->requester_phone);

            if (strlen($digits) >= self::PHONE_SUFFIX_DIGITS) {
                array_unshift($phones, $digits);
            }
        }

        $names = $this->names($text, $request->requester_name);

        return [
            'emails' => array_values(array_unique($emails)),
            'phones' => array_values(array_unique($phones)),
            'names' => array_values(array_unique($names)),
        ];
    }

    /**
     * Die eigentliche Suche — laeuft im Tenant-Kontext.
     *
     * @param  array{emails: list<string>, phones: list<string>, names: list<string>}  $signals
     * @return list<array<string, mixed>>
     */
    private function search(array $signals, int $limit): array
    {
        /** @var array<int, array{company: Company, score: float, matched_on: list<string>}> $hits */
        $hits = [];

        foreach ($this->byEmail($signals['emails']) as $company) {
            $this->addHit($hits, $company, 1.0, 'email');
        }

        foreach ($this->byPhone($signals['phones']) as $company) {
            $this->addHit($hits, $company, 0.8, 'telefon');
        }

        foreach ($this->byName($signals['names']) as $company) {
            $this->addHit($hits, $company, $this->nameScore($company, $signals['names']), 'name');
        }

        $sorted = collect($hits)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $sorted
            ->map(fn (array $hit): array => $this->present($hit['company'], $hit['score'], $hit['matched_on']))
            ->all();
    }

    /**
     * @param  list<string>  $emails
     * @return Collection<int, Company>
     */
    private function byEmail(array $emails): Collection
    {
        if ($emails === []) {
            return collect();
        }

        $domains = [];

        foreach ($emails as $email) {
            $at = strrpos($email, '@');

            if ($at !== false) {
                $domains[] = substr($email, $at + 1);
            }
        }

        $domains = array_values(array_unique(array_filter($domains)));

        return Company::query()
            ->withTrashed()
            ->where(function (Builder $query) use ($emails, $domains): void {
                $query->whereIn('email', $emails);

                foreach ($domains as $domain) {
                    // Nur das Ende der Adresse, damit der Index-freie LIKE
                    // nicht auch „…@firma.de.example.com" trifft.
                    $query->orWhere('email', 'like', '%@'.$this->escapeLike($domain));
                }
            })
            ->limit(self::PER_STRATEGY_LIMIT)
            ->get();
    }

    /**
     * Rufnummern vergleichen. `companies.tel` ist unnormalisiert, deshalb
     * wird in SQL auf Ziffern reduziert — die Tabelle eines Portals hat rund
     * 20 000 Zeilen, das ist ein Scan, den eine Mail alle paar Minuten
     * vertraegt.
     *
     * @param  list<string>  $phones
     * @return Collection<int, Company>
     */
    private function byPhone(array $phones): Collection
    {
        if ($phones === []) {
            return collect();
        }

        $expression = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(tel, ''), ' ', ''), '/', ''), '-', ''), '(', ''), ')', ''), '+', '')";

        return Company::query()
            ->withTrashed()
            ->whereNotNull('tel')
            ->where('tel', '!=', '')
            ->where(function (Builder $query) use ($phones, $expression): void {
                foreach ($phones as $phone) {
                    $suffix = substr($phone, -self::PHONE_SUFFIX_DIGITS);

                    $query->orWhereRaw("{$expression} LIKE ?", ['%'.$suffix]);
                }
            })
            ->limit(self::PER_STRATEGY_LIMIT)
            ->get();
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Company>
     */
    private function byName(array $names): Collection
    {
        if ($names === []) {
            return collect();
        }

        return Company::query()
            ->withTrashed()
            ->where(function (Builder $query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhere('name', 'like', '%'.$this->escapeLike($name).'%');
                }
            })
            ->limit(self::PER_STRATEGY_LIMIT)
            ->get();
    }

    /**
     * @param  array<int, array{company: Company, score: float, matched_on: list<string>}>  $hits
     */
    private function addHit(array &$hits, Company $company, float $score, string $reason): void
    {
        $id = (int) $company->getKey();

        if (! isset($hits[$id])) {
            $hits[$id] = ['company' => $company, 'score' => 0.0, 'matched_on' => []];
        }

        // Mehrere Treffer stuetzen sich gegenseitig, bleiben aber unter 1.
        $hits[$id]['score'] = min(1.0, round($hits[$id]['score'] + $score * (1 - $hits[$id]['score']), 3));
        $hits[$id]['matched_on'][] = $reason;
        $hits[$id]['matched_on'] = array_values(array_unique($hits[$id]['matched_on']));
    }

    /**
     * Namensaehnlichkeit — der beste Wert aller Namensbrocken.
     *
     * @param  list<string>  $names
     */
    private function nameScore(Company $company, array $names): float
    {
        $companyName = Str::lower((string) $company->name);
        $best = 0.0;

        foreach ($names as $name) {
            similar_text($companyName, Str::lower($name), $percent);
            $best = max($best, $percent / 100);
        }

        // Ein Namenstreffer allein ist nie so stark wie Adresse oder Nummer.
        return round(min(0.7, $best * 0.7), 3);
    }

    /**
     * @param  list<string>  $matchedOn
     * @return array<string, mixed>
     */
    private function present(Company $company, float $score, array $matchedOn): array
    {
        return [
            'id' => (int) $company->getKey(),
            'name' => (string) $company->name,
            'address' => trim((string) $company->street.' '.(string) $company->house_no),
            'city' => trim((string) $company->zipcode.' '.(string) ($company->city->name ?? '')),
            'tel' => (string) ($company->tel ?? ''),
            'email' => (string) ($company->email ?? ''),
            'website' => (string) ($company->website ?? ''),
            'claimed' => $company->user_id !== null,
            'score' => $score,
            'matched_on' => $matchedOn,
        ];
    }

    /**
     * @return list<string>
     */
    private function emails(string $text): array
    {
        preg_match_all('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', $text, $matches);

        return array_values(array_unique(array_map(
            fn (string $email): string => Str::lower(rtrim($email, '.')),
            array_slice($matches[0], 0, 10),
        )));
    }

    /**
     * Rufnummern aus dem Text, auf Ziffern reduziert. Alles unter sieben
     * Ziffern ist keine Nummer, sondern eine Hausnummer oder ein Datum.
     *
     * @return list<string>
     */
    private function phones(string $text): array
    {
        preg_match_all('/(?:\+49|0049|0)[\d\s\/().-]{6,25}\d/u', $text, $matches);

        $phones = [];

        foreach (array_slice($matches[0], 0, 10) as $raw) {
            $digits = $this->digits($raw);

            if (strlen($digits) >= self::PHONE_SUFFIX_DIGITS) {
                $phones[] = $digits;
            }
        }

        return array_values(array_unique($phones));
    }

    /**
     * Namenskandidaten: der Absendername und die kurzen Zeilen des Texts.
     * Signaturen und Briefkoepfe stehen genau so da — eine Zeile, zwei bis
     * fuenf Woerter, oft mit Rechtsform.
     *
     * @return list<string>
     */
    private function names(string $text, ?string $senderName): array
    {
        $names = [];

        if ($senderName !== null && trim($senderName) !== '') {
            $names[] = trim($senderName);
        }

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', (string) $line));

            if ($line === '' || mb_strlen($line) < 4 || mb_strlen($line) > 60) {
                continue;
            }

            // Nur Zeilen, die nach einem Namen aussehen: keine Satzzeichen am
            // Ende, keine Adresse, nicht nur Zahlen.
            if (preg_match('/[.!?:;]$/u', $line) === 1) {
                continue;
            }

            if (preg_match('/^\d/u', $line) === 1 || str_contains($line, '@') || str_contains($line, 'http')) {
                continue;
            }

            $words = preg_split('/\s+/u', $line) ?: [];

            if (count($words) > 6) {
                continue;
            }

            $names[] = $line;
        }

        // Firmennamen stehen auch mitten im Satz („ich bin der Inhaber von
        // Mueller Haustechnik GmbH in Koeln"). Deshalb zusaetzlich jede
        // Gruppe aus zwei bis fuenf gross geschriebenen Woertern — mit
        // Rechtsform reicht schon eines.
        preg_match_all(
            '/\b(?:[A-ZÄÖÜ][\p{L}&.\'-]+(?:\s+(?:[A-ZÄÖÜ][\p{L}&.\'-]+|und|&|für|am|an|der|die|das))*)\b/u',
            $text,
            $gruppen,
        );

        foreach ($gruppen[0] as $gruppe) {
            $gruppe = trim($gruppe);
            $woerter = preg_split('/\s+/u', $gruppe) ?: [];

            if (mb_strlen($gruppe) < 6 || count($woerter) > 5) {
                continue;
            }

            $hatRechtsform = preg_match('/\b(GmbH|AG|KG|OHG|UG|e\.?K\.?|mbH|GbR|SE|Co)\b/u', $gruppe) === 1;

            if (count($woerter) >= 2 || $hatRechtsform) {
                $names[] = $gruppe;
            }
        }

        // Mehr als ein paar Brocken bringen nichts und kosten LIKE-Abfragen.
        // Die laengsten zuerst: sie sind die spezifischsten.
        $names = array_values(array_unique($names));

        usort($names, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($names, 0, 6);
    }

    private function digits(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
