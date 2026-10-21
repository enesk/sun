<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use Illuminate\Support\Str;

/**
 * Diff eines Aenderungsantrags aus dem Self-Service-Formular (SUN-RQ #4).
 *
 * Das Formular ist mit den aktuellen Werten vorbelegt. Kaeme der Vergleich
 * roh, waere fast jedes Absenden eine Aenderung: der Browser liefert
 * `\r\n` statt `\n`, ein Besucher setzt ein Leerzeichen hinter die
 * Telefonnummer, schreibt die Vorwahl in Klammern oder haengt einen
 * Schraegstrich an die Adresse der Website. Solche Scheinaenderungen wuerden
 * in der Inbox (#7) als Arbeit erscheinen und ueber die Whitelist (#9) sogar
 * automatisch uebernommen.
 *
 * Deshalb wird **normalisiert verglichen und unnormalisiert gespeichert**:
 * entschieden wird auf der Normalform ({@see self::normalize()}), in
 * `request_changes` landet der aufgeraeumte Wert, den der Besucher wirklich
 * eingetippt hat — sonst stuende im Panel ein Wert, den niemand geschrieben
 * hat.
 *
 * Die Feldschluessel sind die der Whitelist in
 * `config('profile-requests.auto_apply_fields')` bzw. der Handarbeitsliste
 * aus docs/profile-requests.md 3. Ausnahme `city`: ein Stadtwechsel kommt als
 * Freitext an, weil das Formular keine Stadtsuche hat und die Zuordnung zu
 * `cities.id` ohnehin Handarbeit bleibt (Stadtseiten-Relevanz).
 */
class BuildChangeSet
{
    /** Felder, die als einzeilige Zeichenkette ankommen. */
    public const TEXT_FIELDS = [
        'name',
        'street',
        'house_no',
        'zipcode',
        'city',
        'tel',
        'email',
        'website',
    ];

    /**
     * Diff aufbauen: je Feld eine Zeile, nur wo sich nach der Normalform
     * etwas geaendert hat.
     *
     * `$submitted` enthaelt nur die Felder, die das Formular fuehrt; was
     * fehlt, wird nicht angefasst (ein nicht gerendertes Feld darf keine
     * Loeschung bedeuten).
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function __invoke(Company $company, array $submitted): array
    {
        $current = $this->currentValues($company);
        $diff = [];

        foreach ($submitted as $field => $new) {
            if (! array_key_exists($field, $current)) {
                continue;
            }

            $old = $current[$field];

            if ($this->normalize($field, $old) === $this->normalize($field, $new)) {
                continue;
            }

            $diff[$field] = [
                'old' => $this->clean($field, $old),
                'new' => $this->clean($field, $new),
            ];
        }

        return $diff;
    }

    /**
     * Die aktuellen Werte des Profils in genau der Form, in der das Formular
     * sie vorbelegt — eine Stelle, damit Vorbelegung und Vergleich nicht
     * auseinanderlaufen koennen.
     *
     * @return array<string, mixed>
     */
    public function currentValues(Company $company): array
    {
        return [
            'name' => (string) ($company->name ?? ''),
            'street' => (string) ($company->street ?? ''),
            'house_no' => (string) ($company->house_no ?? ''),
            'zipcode' => (string) ($company->zipcode ?? ''),
            'city' => (string) ($company->city->name ?? ''),
            'tel' => (string) ($company->tel ?? ''),
            'email' => (string) ($company->email ?? ''),
            'website' => (string) ($company->website ?? ''),
            'description' => (string) ($company->description ?? ''),
            'opening_hours' => $this->currentOpeningHours($company),
            'services' => $this->currentServices($company),
        ];
    }

    /**
     * Oeffnungszeiten als sieben Zeilen, Montag (0) bis Sonntag (6) — auch
     * die Tage ohne Datensatz, damit der Editor nie Luecken hat und ein neu
     * gesetzter Tag als Aenderung an `opening_hours` sichtbar wird.
     *
     * @return list<array{day: int, closed: bool, opens: string, closes: string}>
     */
    public function currentOpeningHours(Company $company): array
    {
        $byDay = $company->openingHours->keyBy('day_of_week');
        $rows = [];

        foreach (array_keys(CompanyOpeningHour::DAYS) as $day) {
            $hour = $byDay->get($day);

            $rows[] = [
                'day' => (int) $day,
                // Kein Datensatz heisst nicht "geschlossen", sondern "keine
                // Angabe" — im Editor dieselbe Zeile, aber ohne Zeiten.
                'closed' => (bool) ($hour->is_closed ?? false),
                'opens' => $this->time($hour->opens_at ?? null),
                'closes' => $this->time($hour->closes_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Leistungen als Liste in der Reihenfolge des Profils. Der Preis kommt
     * als Euro-Zeichenkette ins Formular, weil `company_services.price_from`
     * in Cent gefuehrt wird und niemand Cent eintippt.
     *
     * @return list<array{name: string, price: string, description: string}>
     */
    public function currentServices(Company $company): array
    {
        return $company->services
            ->map(fn ($service): array => [
                'name' => (string) $service->name,
                'price' => $service->price_from === null
                    ? ''
                    : number_format($service->price_from / 100, 2, ',', ''),
                'description' => (string) ($service->description ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Vergleichsform eines Werts. Hier und nur hier wird entschieden, was als
     * "gleich" gilt.
     */
    public function normalize(string $field, mixed $value): mixed
    {
        if (in_array($field, self::TEXT_FIELDS, true)) {
            $text = $this->squish((string) $value);

            return match ($field) {
                // Nur Ziffern und ein fuehrendes Plus: "0221 / 12 34 56",
                // "(0221) 123456" und "+49 221 123456" sind dieselbe Nummer,
                // soweit sich das ohne Laenderlogik sagen laesst.
                'tel' => $this->phone($text),
                'email' => Str::lower($text),
                'website' => $this->url($text),
                default => Str::lower($text),
            };
        }

        if ($field === 'description') {
            return Str::lower($this->multiline((string) $value));
        }

        if ($field === 'opening_hours') {
            return $this->normalizeOpeningHours(is_array($value) ? $value : []);
        }

        if ($field === 'services') {
            return $this->normalizeServices(is_array($value) ? $value : []);
        }

        // logo/images: der Vergleich laeuft nicht ueber den Inhalt, sondern
        // ueber "liegt eine neue Datei an?" — das entscheidet die Komponente.
        return $value;
    }

    /**
     * Speicherform eines Werts: aufgeraeumt, aber in der Schreibweise des
     * Besuchers.
     */
    public function clean(string $field, mixed $value): mixed
    {
        if (in_array($field, self::TEXT_FIELDS, true)) {
            return $this->squish((string) $value);
        }

        if ($field === 'description') {
            return $this->multiline((string) $value);
        }

        if ($field === 'opening_hours') {
            return $this->cleanOpeningHours(is_array($value) ? $value : []);
        }

        if ($field === 'services') {
            return $this->cleanServices(is_array($value) ? $value : []);
        }

        return $value;
    }

    /**
     * Zusammenfassung fuer den Dialog ("Sie ändern: Telefon, Öffnungszeiten").
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $diff
     * @return list<string>
     */
    public function labels(array $diff): array
    {
        return array_values(array_map(
            fn (string $field): string => (string) __('portal.profile_request.fields.'.$field),
            array_keys($diff),
        ));
    }

    // ------------------------------------------------------------------
    // Normalisierung der Einzelteile
    // ------------------------------------------------------------------

    private function squish(string $value): string
    {
        return Str::squish($value);
    }

    /**
     * Mehrzeiliger Text: Zeilenenden vereinheitlichen, je Zeile trimmen,
     * mehr als eine Leerzeile zu einer zusammenziehen.
     */
    private function multiline(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = array_map(static fn (string $line): string => trim($line), explode("\n", $value));
        $value = implode("\n", $lines);
        $value = (string) preg_replace("/\n{3,}/", "\n\n", $value);

        return trim($value);
    }

    private function phone(string $value): string
    {
        $plus = str_starts_with($value, '+');
        $digits = (string) preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return '';
        }

        return ($plus ? '+' : '').$digits;
    }

    /**
     * Adresse einer Website: Schema und Host klein, fehlendes Schema als
     * https ergaenzt, Schraegstrich am Ende weg. "FIRMA.de/",
     * "www.firma.de" und "https://firma.de" bleiben verschieden, sobald sich
     * der Host unterscheidet — nur Schreibweise wird eingeebnet.
     */
    private function url(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (! Str::contains($value, '//')) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['host'])) {
            return Str::lower(rtrim($value, '/'));
        }

        $host = Str::lower($parts['host']);
        $path = rtrim($parts['path'] ?? '', '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return Str::lower($parts['scheme'] ?? 'https').'://'.$host.$path.$query;
    }

    private function time(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return substr($value, 0, 5);
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array{day: int, closed: bool, opens: string, closes: string}>
     */
    private function cleanOpeningHours(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $closed = (bool) ($row['closed'] ?? false);

            $clean[] = [
                'day' => (int) ($row['day'] ?? 0),
                'closed' => $closed,
                'opens' => $closed ? '' : $this->time($row['opens'] ?? ''),
                'closes' => $closed ? '' : $this->time($row['closes'] ?? ''),
            ];
        }

        usort($clean, static fn (array $a, array $b): int => $a['day'] <=> $b['day']);

        return $clean;
    }

    /**
     * Vergleichsform der Oeffnungszeiten: ein Tag ohne Zeiten und ohne Haken
     * ist "keine Angabe" und faellt heraus, damit die sieben Leerzeilen des
     * Editors nicht als Aenderung gelten.
     *
     * @param  array<int|string, mixed>  $rows
     * @return array<int, string>
     */
    private function normalizeOpeningHours(array $rows): array
    {
        $normal = [];

        foreach ($this->cleanOpeningHours($rows) as $row) {
            if ($row['closed']) {
                $normal[$row['day']] = 'closed';

                continue;
            }

            if ($row['opens'] === '' && $row['closes'] === '') {
                continue;
            }

            $normal[$row['day']] = $row['opens'].'-'.$row['closes'];
        }

        ksort($normal);

        return $normal;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array{name: string, price: string, description: string}>
     */
    private function cleanServices(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = $this->squish((string) ($row['name'] ?? ''));

            // Eine Zeile ohne Bezeichnung ist eine leere Zeile des Editors.
            if ($name === '') {
                continue;
            }

            $clean[] = [
                'name' => $name,
                'price' => $this->price((string) ($row['price'] ?? '')),
                'description' => $this->multiline((string) ($row['description'] ?? '')),
            ];
        }

        return $clean;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<string>
     */
    private function normalizeServices(array $rows): array
    {
        return array_map(
            fn (array $row): string => Str::lower($row['name']).'|'.$row['price'].'|'.Str::lower($row['description']),
            $this->cleanServices($rows),
        );
    }

    /**
     * Preisangabe auf "1234,50" bringen; leer bleibt leer (Preis auf
     * Anfrage). "89 €", "89.00" und "89,-" sind derselbe Preis.
     */
    private function price(string $value): string
    {
        $value = (string) preg_replace('/[^0-9,.]/', '', $value);

        if ($value === '') {
            return '';
        }

        // Letztes Trennzeichen ist das Dezimaltrennzeichen, alles davor sind
        // Tausenderpunkte.
        $value = str_replace('.', ',', $value);
        $parts = explode(',', $value);
        $decimals = count($parts) > 1 ? array_pop($parts) : '';
        $euros = implode('', $parts);

        if ($euros === '') {
            $euros = '0';
        }

        $decimals = substr($decimals.'00', 0, 2);

        return ltrim($euros, '0') === '' ? '0,'.$decimals : ltrim($euros, '0').','.$decimals;
    }
}
