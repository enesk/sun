<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\CompanyService;
use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\VerificationMethod;
use App\ProfileRequests\Mail\ChangesAppliedMail;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestAttachment;
use App\ProfileRequests\Models\RequestChange;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use App\Services\Seo\StructuredDataService;
use App\Support\Tenancy\TenantMailBranding;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Angehakte Diff-Zeilen ins Profil schreiben (SUN-RQ #7).
 *
 * Die Aktion kapselt den Kontextwechsel: aufgerufen wird sie aus dem
 * Central-Panel, geschrieben wird ausschliesslich innerhalb von
 * `Tenant::run()` ({@see TenantCompanyResolver}). Ein Tenant-Model im
 * Central-Kontext zu bearbeiten waere ein Schreibvorgang auf der falschen
 * Datenbank — deshalb passiert hier nichts ausserhalb von
 * {@see self::writeInTenant()}.
 *
 * Geschrieben werden **nur** die Zeilen mit `request_changes.accepted = true`
 * (der Haken aus #11). Jede uebrige Zeile bleibt unberuehrt und steht mit
 * ihrem Grund im Protokoll — „alles oder nichts" gibt es nur bei der
 * Auto-Regel (#9), im Panel entscheidet Enes je Zeile.
 *
 * Drei Felder werden bewusst nicht aus dem Diff geschrieben:
 *  * `categories` — eine Kategorie ist eine ID, kein Name; die Zuordnung
 *    steuert Suchergebnisse und bleibt Handarbeit im Portal-Panel.
 *  * `logo`/`images` — Bilder kommen nicht aus dem Diff, sondern aus den
 *    Anhaengen mit Rolle `logo`/`gallery` (#11).
 *  * `user_id` — ein Inhaberwechsel ist eine Uebernahme (Typ `claim`).
 *
 * Nach dem Schreiben werden die Caches **im Portal** geleert. Ein
 * `Cache::forget` aus dem Central-Panel traefe den Portal-Cache nicht
 * (docs/profile-requests.md 6, „Cache-Falle").
 */
class ApplyChanges
{
    /** Spalten von `companies`, die eine Diff-Zeile direkt setzen darf. */
    private const DIRECT_COLUMNS = [
        'name',
        'street',
        'house_no',
        'zipcode',
        'tel',
        'email',
        'website',
        'description',
        'social_facebook',
        'social_instagram',
        'social_linkedin',
        'social_youtube',
        'video_url',
    ];

    /** Felder, die ueber die Anhaenge laufen, nicht ueber den Diff. */
    private const ATTACHMENT_FIELDS = ['logo', 'images', 'gallery'];

    public function __construct(
        private readonly TenantCompanyResolver $resolver,
    ) {}

    /**
     * Uebernahme ausfuehren.
     *
     * @return array{ok: bool, applied: list<string>, skipped: array<string, string>, images: int, message: string}
     */
    public function __invoke(ProfileRequest $request, ?User $actor = null, bool $notify = true): array
    {
        if ($request->tenant_id === null || $request->company_id === null) {
            return $this->fail('Ohne zugeordneten Betrieb lässt sich nichts übernehmen.');
        }

        if (! $request->isOpen()) {
            return $this->fail('Dieser Vorgang ist bereits abgeschlossen.');
        }

        $accepted = $request->changes()->accepted()->orderBy('field')->get();
        $images = $request->attachments()->images()->get()
            ->filter(fn (RequestAttachment $a): bool => in_array($a->image_role, [RequestAttachment::ROLE_LOGO, RequestAttachment::ROLE_GALLERY], true))
            ->values();

        if ($accepted->isEmpty() && $images->isEmpty()) {
            return $this->fail('Es ist keine Zeile angehakt — es gibt nichts zu übernehmen.');
        }

        // Die Bilddateien **vor** dem Kontextwechsel in temporaere Dateien
        // holen: innerhalb von Tenant::run() zeigt die Platte `local` auf
        // das Verzeichnis des Portals, und der Anhang laege dort nicht
        // (App\Listeners\Tenant\SetTenantStorageUrl).
        $dateien = $this->stage($images);

        try {
            $result = $this->resolver->runFor((int) $request->tenant_id, function () use ($request, $accepted, $dateien): array {
                return $this->writeInTenant($request, $accepted, $dateien);
            });
        } finally {
            foreach ($dateien as $datei) {
                if (is_file($datei['tmp'])) {
                    @unlink($datei['tmp']);
                }
            }
        }

        if ($result === null) {
            return $this->fail('Das Portal ist nicht erreichbar — nichts wurde geschrieben.');
        }

        if ($result['applied'] === [] && $result['images'] === 0) {
            $request->recordEvent(RequestEventType::NOTE, ['note' => 'Übernahme ohne Wirkung', 'skipped' => $result['skipped']]);

            return ['skipped' => $result['skipped']] + $this->fail('Keine der angehakten Zeilen ließ sich schreiben.');
        }

        $this->ensureVerified($request, $actor);

        $request->apply($actor, [
            'action' => 'apply_changes',
            'fields' => $result['applied'],
            'skipped' => $result['skipped'],
            'images' => $result['images'],
        ]);

        if ($notify) {
            $this->notify($request, $result);
        }

        return [
            'ok' => true,
            'applied' => $result['applied'],
            'skipped' => $result['skipped'],
            'images' => $result['images'],
            'message' => sprintf(
                '%d Feld%s übernommen%s.',
                count($result['applied']),
                count($result['applied']) === 1 ? '' : 'er',
                $result['images'] > 0 ? sprintf(' und %d Bild%s', $result['images'], $result['images'] === 1 ? '' : 'er') : '',
            ),
        ];
    }

    /**
     * Den Vorgang auf `verified` bringen, bevor er erledigt wird.
     *
     * Der Zustandsautomat laesst `applied` nur aus `verified` zu (#3). Wer im
     * Panel uebernimmt, hat die Anfrage gesehen und freigegeben — das ist
     * genau die Handpruefung aus docs/profile-requests.md 4.3, und sie wird
     * mit `VerificationMethod::MANUAL` und Enes als Akteur protokolliert.
     *
     * Oeffentlich, weil die Loeschung denselben Schritt braucht: auch
     * {@see \App\Companies\Deletion\DeleteCompanyAction} schliesst den
     * Vorgang mit `apply()` ab.
     */
    public function ensureVerified(ProfileRequest $request, ?User $actor = null): bool
    {
        if ($request->isVerified() && $request->status === RequestStatus::VERIFIED) {
            return true;
        }

        $actorId = $actor?->getKey() === null ? null : (int) $actor->getKey();

        if ($request->status === RequestStatus::NEW) {
            $request->markClassified([], RequestEvent::ACTOR_USER, $actorId);
        }

        if ($request->status === RequestStatus::VERIFIED) {
            return true;
        }

        return $request->verify(
            VerificationMethod::MANUAL,
            $request->verified_email ?? $request->requester_email,
            RequestEvent::ACTOR_USER,
            $actorId,
        );
    }

    /**
     * Feste Werte in das Profil schreiben — ohne Diff, ohne Haken, ohne
     * Statuswechsel. Der Weg fuer „Rueckgaengig"
     * ({@see RevertChanges}, #9), das die Altwerte aus `request_changes`
     * zurueckschreibt.
     *
     * Nutzt dieselbe Feldlogik wie die Uebernahme ({@see self::writeField()}),
     * damit ein Feld nicht an zwei Stellen unterschiedlich geschrieben wird,
     * und raeumt hinterher dieselben Caches.
     *
     * @param  array<string, mixed>  $values  Feldname => Wert
     * @return array{applied: list<string>, skipped: array<string, string>}
     */
    public function writeValues(ProfileRequest $request, array $values): array
    {
        if ($request->tenant_id === null || $request->company_id === null || $values === []) {
            return ['applied' => [], 'skipped' => []];
        }

        $result = $this->resolver->runFor((int) $request->tenant_id, function () use ($request, $values): array {
            $company = Company::find($request->company_id);

            if ($company === null) {
                return ['applied' => [], 'skipped' => ['*' => 'Betrieb im Portal nicht gefunden']];
            }

            $applied = [];
            $skipped = [];
            $dirty = [];

            foreach ($values as $field => $value) {
                $field = (string) $field;

                if (in_array($field, self::ATTACHMENT_FIELDS, true)) {
                    // Bilder lassen sich nicht zurueckschreiben: die alte
                    // Datei ist mit der Uebernahme ersetzt worden.
                    $skipped[$field] = 'Bilder werden nicht zurückgeschrieben';

                    continue;
                }

                try {
                    $written = $this->writeField($company, $field, $value, $dirty);
                } catch (Throwable $exception) {
                    Log::warning('profile-requests: Feld nicht geschrieben', [
                        'request_id' => $request->getKey(),
                        'field' => $field,
                        'exception' => $exception->getMessage(),
                    ]);

                    $skipped[$field] = 'Fehler beim Schreiben';

                    continue;
                }

                if ($written === true) {
                    $applied[] = $field;

                    continue;
                }

                $skipped[$field] = is_string($written) ? $written : 'übersprungen';
            }

            if ($dirty !== []) {
                $company->fill($dirty)->save();
            }

            if ($applied !== []) {
                $this->forgetCaches($company);
            }

            return ['applied' => $applied, 'skipped' => $skipped];
        });

        return $result ?? ['applied' => [], 'skipped' => ['*' => 'Portal nicht erreichbar']];
    }

    // ------------------------------------------------------------------
    // Im Portal
    // ------------------------------------------------------------------

    /**
     * Der schreibende Teil — laeuft ausschliesslich im Tenant-Kontext.
     *
     * Mitgegeben werden auch Firmenname und Profilpfad: beide braucht die
     * Bestaetigungsmail, und beide sind nur hier zu holen. Der Pfad kommt aus
     * `portal_path` (nicht `portal_url`) — `route()` baut den Host aus
     * `config('app.url')`, und das ist hier die zentrale Domain; die Domain
     * setzt {@see TenantMailBranding} davor (#26).
     *
     * @param  \Illuminate\Support\Collection<int, RequestChange>  $accepted
     * @param  list<array{tmp: string, name: string, collection: string}>  $dateien
     * @return array{applied: list<string>, skipped: array<string, string>, images: int, company_name: string, portal_path: string}
     */
    private function writeInTenant(ProfileRequest $request, $accepted, array $dateien): array
    {
        $company = Company::find($request->company_id);

        if ($company === null) {
            return ['applied' => [], 'skipped' => ['*' => 'Betrieb im Portal nicht gefunden'], 'images' => 0, 'company_name' => '', 'portal_path' => ''];
        }

        $applied = [];
        $skipped = [];
        $dirty = [];

        foreach ($accepted as $change) {
            $field = (string) $change->field;

            if (in_array($field, self::ATTACHMENT_FIELDS, true)) {
                // Bilder laufen ueber die Anhaenge, siehe unten.
                continue;
            }

            try {
                $written = $this->writeField($company, $field, $change->new_value, $dirty);
            } catch (Throwable $exception) {
                Log::warning('profile-requests: Feld nicht übernommen', [
                    'request_id' => $request->getKey(),
                    'field' => $field,
                    'exception' => $exception->getMessage(),
                ]);

                $skipped[$field] = 'Fehler beim Schreiben';

                continue;
            }

            if ($written === true) {
                $applied[] = $field;

                continue;
            }

            $skipped[$field] = is_string($written) ? $written : 'übersprungen';
        }

        if ($dirty !== []) {
            $company->fill($dirty)->save();
        }

        $written = $this->writeImages($company, $dateien);

        if ($applied !== [] || $written > 0) {
            $this->forgetCaches($company);
        }

        // Nach dem Speichern gelesen: war der Firmenname selbst Teil der
        // Uebernahme, nennt die Mail den neuen, nicht den alten.
        return [
            'applied' => $applied,
            'skipped' => $skipped,
            'images' => $written,
            'company_name' => (string) $company->name,
            'portal_path' => (string) $company->portal_path,
        ];
    }

    /**
     * Eine Diff-Zeile schreiben.
     *
     * Rueckgabe `true` = geschrieben, ein String = uebersprungen mit Grund.
     * Spaltenwerte landen in `$dirty` und werden am Ende **einmal**
     * gespeichert — sonst feuerte jedes Feld die `saved`-Hooks des Models
     * (JSON-LD, Slug) erneut.
     *
     * @param  array<string, mixed>  $dirty
     */
    private function writeField(Company $company, string $field, mixed $value, array &$dirty): bool|string
    {
        if (in_array($field, self::DIRECT_COLUMNS, true)) {
            $text = is_scalar($value) ? trim((string) $value) : '';

            $dirty[$field] = $text === '' ? null : $text;

            return true;
        }

        return match ($field) {
            'city', 'city_id' => $this->writeCity($field, $value, $dirty),
            'opening_hours' => $this->writeOpeningHours($company, $value),
            'services' => $this->writeServices($company, $value),
            'categories' => 'Kategorien werden im Portal zugeordnet, nicht aus dem Diff',
            'user_id' => 'Ein Inhaberwechsel ist eine Übernahme, keine Änderung',
            default => 'Unbekanntes Feld',
        };
    }

    /**
     * Stadtwechsel. Aus dem Formular (#4) kommt der Ortsname als Freitext,
     * weil es dort keine Stadtsuche gibt. Ohne Treffer in `cities` wird
     * **nicht** geraten: eine falsche Stadt verschiebt das Profil auf eine
     * fremde Stadtseite.
     *
     * @param  array<string, mixed>  $dirty
     */
    private function writeCity(string $field, mixed $value, array &$dirty): bool|string
    {
        if ($field === 'city_id') {
            $id = is_numeric($value) ? (int) $value : 0;

            if ($id <= 0 || City::find($id) === null) {
                return 'Stadt nicht gefunden';
            }

            $dirty['city_id'] = $id;

            return true;
        }

        $name = is_scalar($value) ? Str::squish((string) $value) : '';

        if ($name === '') {
            return 'Kein Ortsname angegeben';
        }

        $matches = City::query()->where('name', $name)->limit(2)->get();

        if ($matches->count() !== 1) {
            return $matches->isEmpty()
                ? "Ort „{$name}“ gibt es in diesem Portal nicht"
                : "Ort „{$name}“ ist mehrdeutig — im Portal zuordnen";
        }

        $dirty['city_id'] = (int) $matches->first()->getKey();

        return true;
    }

    /**
     * Oeffnungszeiten ersetzen. Zwei Eingangsformen, weil der Diff aus dem
     * Formular (#4: day/closed/opens/closes) oder aus der KI (#6:
     * day_of_week/opens_at/closes_at/is_closed) stammen kann.
     */
    private function writeOpeningHours(Company $company, mixed $value): bool|string
    {
        if (! is_array($value)) {
            return 'Öffnungszeiten in unbekannter Form';
        }

        $rows = [];

        foreach ($value as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $day = (int) ($entry['day'] ?? $entry['day_of_week'] ?? (is_numeric($key) ? $key : -1));

            if (! array_key_exists($day, CompanyOpeningHour::DAYS)) {
                continue;
            }

            $closed = (bool) ($entry['closed'] ?? $entry['is_closed'] ?? false);
            $opens = $this->time($entry['opens'] ?? $entry['opens_at'] ?? null);
            $closes = $this->time($entry['closes'] ?? $entry['closes_at'] ?? null);

            // Ein Tag ohne Haken und ohne Zeiten heisst „keine Angabe" und
            // bekommt keine Zeile — sonst stuende dort „00:00 – 00:00".
            if (! $closed && ($opens === null || $closes === null)) {
                continue;
            }

            $rows[$day] = [
                'company_id' => $company->getKey(),
                'day_of_week' => $day,
                'is_closed' => $closed,
                'opens_at' => $closed ? null : $opens,
                'closes_at' => $closed ? null : $closes,
            ];
        }

        $company->openingHours()->delete();

        foreach ($rows as $row) {
            CompanyOpeningHour::create($row);
        }

        $company->unsetRelation('openingHours');

        return true;
    }

    /**
     * Leistungen ersetzen. Preis kommt als Euro-Zeichenkette aus dem Formular
     * oder als Cent-Betrag aus der KI; gespeichert wird immer in Cent.
     */
    private function writeServices(Company $company, mixed $value): bool|string
    {
        if (! is_array($value)) {
            return 'Leistungen in unbekannter Form';
        }

        $rows = [];

        foreach ($value as $entry) {
            if (is_scalar($entry)) {
                $entry = ['name' => (string) $entry];
            }

            if (! is_array($entry)) {
                continue;
            }

            $name = Str::squish((string) ($entry['name'] ?? $entry['title'] ?? ''));

            if ($name === '') {
                continue;
            }

            $rows[] = [
                'company_id' => $company->getKey(),
                'name' => $name,
                'price_from' => $this->cents($entry),
                'description' => trim((string) ($entry['description'] ?? '')) ?: null,
                'sort_order' => count($rows),
            ];
        }

        $company->services()->delete();

        foreach ($rows as $row) {
            CompanyService::create($row);
        }

        $company->unsetRelation('services');

        return true;
    }

    /**
     * Kopien der Bild-Anhaenge im Systemtemp — angelegt im Central-Kontext,
     * verbraucht im Portal. Das Original in `request_attachments` bleibt
     * liegen: ohne die Datei waere spaeter nicht mehr feststellbar, was
     * uebernommen wurde.
     *
     * @param  \Illuminate\Support\Collection<int, RequestAttachment>  $images
     * @return list<array{tmp: string, name: string, collection: string}>
     */
    private function stage($images): array
    {
        $dateien = [];

        foreach ($images as $attachment) {
            try {
                $inhalt = Storage::disk(RequestAttachment::DISK)->get($attachment->path);

                if ($inhalt === null) {
                    continue;
                }

                $tmp = tempnam(sys_get_temp_dir(), 'req-media-');

                if ($tmp === false) {
                    continue;
                }

                file_put_contents($tmp, $inhalt);

                $dateien[] = [
                    'tmp' => $tmp,
                    'name' => $attachment->fileName(),
                    'collection' => $attachment->image_role === RequestAttachment::ROLE_LOGO ? 'logo' : 'gallery',
                ];
            } catch (Throwable $exception) {
                Log::warning('profile-requests: Bild-Anhang nicht lesbar', [
                    'attachment_id' => $attachment->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $dateien;
    }

    /**
     * Bilder ins Profil haengen (#7, Abnahmekriterium 3). Die Rolle steht in
     * `request_attachments.image_role` und wird in der Diff-Komponente (#11)
     * gesetzt; `logo` ist eine Einzeldatei-Collection und ersetzt damit das
     * bisherige Logo, `gallery` kommt dazu.
     *
     * @param  list<array{tmp: string, name: string, collection: string}>  $dateien
     */
    private function writeImages(Company $company, array $dateien): int
    {
        $written = 0;

        foreach ($dateien as $datei) {
            try {
                $company
                    ->addMedia($datei['tmp'])
                    ->usingFileName($datei['name'])
                    ->toMediaCollection($datei['collection']);

                $written++;
            } catch (Throwable $exception) {
                Log::warning('profile-requests: Bild nicht übernommen', [
                    'datei' => $datei['name'],
                    'collection' => $datei['collection'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $written;
    }

    /**
     * Caches, in denen die geaenderten Daten stecken — im Portal, nicht
     * zentral. Das JSON-LD des Profils raeumt der `saved`-Hook des Models
     * bereits weg; die Firmenlisten tun das nicht.
     */
    private function forgetCaches(Company $company): void
    {
        try {
            StructuredDataService::forget((int) $company->getKey());
            TenantCache::forget('portal.featured_companies');
            TenantCache::forget('portal.random_companies');
            TenantCache::forget("sun-v2.profile.nearby.{$company->city_id}.{$company->getKey()}");
        } catch (Throwable $exception) {
            Log::warning('profile-requests: Caches nach Übernahme nicht geleert', [
                'company_id' => $company->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Kleinkram
    // ------------------------------------------------------------------

    private function time(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || preg_match('/^\d{1,2}:\d{2}/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 5).':00';
    }

    /** @param  array<string, mixed>  $entry */
    private function cents(array $entry): ?int
    {
        if (isset($entry['price_from']) && is_numeric($entry['price_from'])) {
            return (int) $entry['price_from'];
        }

        $price = (string) ($entry['price'] ?? '');
        $price = (string) preg_replace('/[^0-9,.]/', '', $price);

        if ($price === '') {
            return null;
        }

        return (int) round(((float) str_replace(',', '.', str_replace('.', '', $price))) * 100);
    }

    /**
     * Bestaetigung an den Anfragenden.
     *
     * Die Mail bekommt **Feldschluessel**, nicht Beschriftungen: sie setzt
     * die Labels selbst ({@see ChangesAppliedMail::payload()}). Genau hier
     * lag der Fehler bis zum 22.10.2026 — uebergeben wurden die fertigen
     * Labels an der Stelle des Firmennamens, der Aufruf warf einen
     * TypeError, und weil er in diesem try/catch steckt, lief die Uebernahme
     * sauber weiter und niemand bekam eine Mail (nur eine Logzeile).
     *
     * Bilder stehen als eigene Zeile in der Liste: ein neues Logo ist keine
     * Diff-Zeile, waere sonst in der Mail unsichtbar und der Betrieb wuesste
     * nicht, dass es im Profil steht.
     *
     * `pendingFields` bleibt leer. Nicht angehakte Zeilen sind entschieden,
     * nicht offen — der Vorgang ist mit dieser Uebernahme geschlossen
     * (Status `applied`), es schreibt niemand mehr nach. Die Mail wuerde
     * sonst etwas zusagen, was nicht passiert.
     *
     * @param  array{applied: list<string>, skipped: array<string, string>, images: int, company_name?: string, portal_path?: string}  $result
     */
    private function notify(ProfileRequest $request, array $result): void
    {
        if (trim((string) $request->requester_email) === '') {
            return;
        }

        $felder = $result['applied'];

        if ($result['images'] > 0) {
            $felder[] = 'images';
        }

        $tenant = $this->resolver->tenant((int) $request->tenant_id);
        $pfad = trim((string) ($result['portal_path'] ?? ''));

        try {
            Mail::to($request->requester_email)->send(new ChangesAppliedMail(
                $request,
                $tenant,
                (string) ($result['company_name'] ?? ''),
                $felder,
                [],
                $pfad === '' ? null : TenantMailBranding::for($tenant)->url($pfad),
            ));

            $request->recordEvent(RequestEventType::MAIL_SENT, [
                'mail' => 'changes_applied',
                'to' => $request->requester_email,
            ]);
        } catch (Throwable $exception) {
            Log::warning('profile-requests: Bestätigungsmail nach Übernahme nicht versendet', [
                'request_id' => $request->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /** @return array{ok: bool, applied: list<string>, skipped: array<string, string>, images: int, message: string} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'applied' => [], 'skipped' => [], 'images' => 0, 'message' => $message];
    }
}
