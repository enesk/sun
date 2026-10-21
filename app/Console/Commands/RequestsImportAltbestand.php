<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\ProfileRequests\Actions\BuildChangeSet;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestAttachment;
use App\ProfileRequests\Models\RequestEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Altbestand in die Anfragen-Inbox übernehmen (SUN-RQ #16).
 *
 * Vor dieser Inbox liefen Profil-Anfragen über zwei Tabellen **je Tenant**:
 * `company_edit_suggestions` (Änderungswünsche vom Formular) und
 * `claim_requests` (Übernahmeanträge mit Nachweisdokument). Der
 * Architektur-Freeze (#2) hat nur das Sammelpostfach als Importquelle
 * vorgesehen; diese beiden Tabellen fielen durch, und Enes sah im Panel
 * keinen einzigen seiner offenen Vorgänge.
 *
 * ÜBERNOMMEN WERDEN NUR OFFENE VORGÄNGE
 * -------------------------------------
 * Abgeschlossene Altvorgänge (approved, rejected, cancelled) bleiben, wo sie
 * sind. Sie in die Inbox zu heben, hätte zwei Nachteile und keinen Nutzen:
 * ihr Endstatus wäre nur über die Automaten-Methoden erreichbar, und `apply()`
 * würde einen vor Monaten genehmigten Wunsch ein zweites Mal ins Profil
 * schreiben. Die Historie bleibt im alten Panel lesbar.
 *
 * KEIN MAGIC-LINK IM NACHGANG
 * ---------------------------
 * Die Vorgänge landen auf `classified` und bleiben dort. Diese Übernahme ruft
 * `requestVerification()` **nicht** auf: niemand soll Monate nach seiner
 * Anfrage plötzlich eine Bestätigungsmail bekommen. Im Panel entscheidet ein
 * Mensch, ob er den Vorgang verifizieren lässt oder direkt bearbeitet.
 *
 * EIN DIFF ENTSTEHT NUR FÜR DIE TELEFONNUMMER
 * -------------------------------------------
 * Die Altspalte `field` kennt address, phone, hours, description, other.
 * Übersetzt wird allein `phone` -> `tel`, und auch das nur, wenn der Wert
 * wie eine Rufnummer aussieht ({@see self::wirktWieRufnummer()}).
 *
 * Der Grund steht in den echten Daten. Das Altformular hatte ein Feld pro
 * Thema, benutzt wurde es als Briefkasten an die Redaktion. Unter
 * `description` stand etwa: „Ihre Darstellung meines ehemaligen
 * Antiquitätengeschäftes ist vollkommen falsch […] löschen Sie den
 * irreführenden Eintrag." Als Diff gelesen hätte ein Klick im Panel diesen
 * Beschwerdetext zur Beschreibung des Betriebs gemacht.
 *
 * `address` ist Freitext („Musterstr. 5, 12345 Berlin"), das Profil führt
 * street, house_no, zipcode und city getrennt; `hours` ist Freitext,
 * `opening_hours` eine Struktur je Wochentag. Zerlegen wäre Raten.
 *
 * Alle diese Vorgänge kommen deshalb **ohne Diff** mit dem vollen Wortlaut
 * in der Nachricht. Der Bearbeiter liest den Wunsch und trägt ihn selbst
 * ein — das ist genau der Weg, den diese Vorgänge vorher auch gingen.
 *
 * Aus demselben Grund gibt es keine Erkennung von Löschwünschen: viele
 * Altvorgänge lauten „gibt es nicht mehr", aber ob daraus eine Löschung
 * wird, entscheidet ein Mensch im Panel und nicht ein Textvergleich hier.
 * Der Typ bleibt `change`, der Wortlaut macht die Absicht sichtbar.
 *
 * WIEDERHOLBAR
 * ------------
 * `external_ref` trägt die Herkunft. Bei Übernahmen ist das die nackte
 * `claim_requests.id` — so und nicht anders, weil {@see ApproveClaim} dort
 * mit `ctype_digit()` nach einem vorhandenen Antrag sucht und sonst einen
 * zweiten anlegen würde. Änderungswünsche tragen `altbestand:edit:<id>`.
 * Zusammen mit `tenant_id` und `type` ist das der Schlüssel, an dem ein
 * zweiter Lauf erkennt, was schon drin ist.
 */
class RequestsImportAltbestand extends Command
{
    /** Präfix in `external_ref` für übernommene Änderungswünsche. */
    public const REF_EDIT = 'altbestand:edit:';

    /**
     * Altspalte `field` -> Feld des neuen Diffs. Was hier fehlt, wird
     * bewusst nicht übersetzt (siehe Klassenkommentar).
     */
    private const FELD_ABBILDUNG = [
        'phone' => 'tel',
    ];

    protected $signature = 'requests:import-altbestand
        {--tenant=* : Portale (Domain oder ID); ohne Angabe alle}
        {--trockenlauf : nichts schreiben, nur zeigen, was passieren würde}
        {--limit= : je Portal höchstens so viele Vorgänge je Quelle}';

    protected $description = 'Übernimmt offene Änderungswünsche und Übernahmeanträge aus den Tenant-Tabellen in die Anfragen-Inbox';

    private int $uebernommen = 0;

    private int $uebersprungen = 0;

    private int $fehler = 0;

    public function handle(BuildChangeSet $changeSet): int
    {
        $trocken = (bool) $this->option('trockenlauf');
        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));

        if ($trocken) {
            $this->warn('Trockenlauf — es wird nichts geschrieben.');
        }

        foreach ($this->tenants() as $tenant) {
            $this->line("<info>{$tenant->domain}</info>");

            try {
                $tenant->run(function () use ($tenant, $changeSet, $trocken, $limit): void {
                    $this->aenderungswuensche($tenant, $changeSet, $trocken, $limit);
                    $this->uebernahmeantraege($tenant, $trocken, $limit);
                });
            } catch (Throwable $e) {
                $this->fehler++;
                $this->error("  Portal übersprungen: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d übernommen, %d übersprungen, %d Fehler.',
            $trocken ? 'Trockenlauf' : 'Übernahme',
            $this->uebernommen,
            $this->uebersprungen,
            $this->fehler,
        ));

        return $this->fehler > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return iterable<Tenant> */
    private function tenants(): iterable
    {
        $gewaehlt = (array) $this->option('tenant');

        if ($gewaehlt === []) {
            return Tenant::all();
        }

        return Tenant::query()
            ->where(function ($query) use ($gewaehlt): void {
                foreach ($gewaehlt as $wert) {
                    $query->orWhere('domain', $wert)->orWhere('id', $wert);
                }
            })
            ->get();
    }

    /**
     * Änderungswünsche aus `company_edit_suggestions`.
     *
     * Läuft im Tenant-Kontext; die Anfrage selbst geht in die Central-DB,
     * dafür sorgt die Verbindung am Modell.
     */
    private function aenderungswuensche(Tenant $tenant, BuildChangeSet $changeSet, bool $trocken, ?int $limit): void
    {
        $query = DB::table('company_edit_suggestions')
            ->whereIn('status', ['pending', 'open', 'new'])
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->get() as $alt) {
            $ref = self::REF_EDIT.$alt->id;

            if ($this->schonDa($tenant, RequestType::CHANGE, $ref)) {
                $this->uebersprungen++;
                $this->line("  schon drin: Änderung #{$alt->id}");

                continue;
            }

            $company = Company::find($alt->company_id);

            if (! $company instanceof Company) {
                $this->uebersprungen++;
                $this->line("  Betrieb {$alt->company_id} fehlt — Änderung #{$alt->id} übersprungen");

                continue;
            }

            $feld = self::FELD_ABBILDUNG[$alt->field] ?? null;
            $wert = (string) $alt->suggested_value;
            $diff = [];
            $verworfen = null;

            if ($feld !== null && ! $this->wirktWieRufnummer($wert)) {
                // „Bitte löschen, die Nummer ist falsch" ist keine Nummer.
                $verworfen = 'Wert im Feld „phone“ ist keine Rufnummer — kein Diff erzeugt';
                $feld = null;
            }

            if ($feld !== null) {
                $diff = $changeSet($company, [$feld => $wert]);
            }

            // Ohne übersetzbares Feld — oder wenn der Wunsch dem heutigen
            // Stand entspricht und der Diff leer bleibt — behalten wir den
            // Wortlaut in der Nachricht. Sonst steht im Panel ein Vorgang
            // ohne jede Aussage.
            $nachricht = $this->nachricht($alt, $feld, $diff === []);

            if ($trocken) {
                $this->uebernommen++;
                $this->line(sprintf(
                    '  [trocken] Änderung #%d  %s -> %s  %s',
                    $alt->id,
                    $alt->field,
                    $feld ?? 'Freitext',
                    $diff === [] ? '(ohne Diff)' : '(Diff: '.implode(', ', array_keys($diff)).')',
                ));

                continue;
            }

            $this->anlegen(
                tenant: $tenant,
                company: $company,
                type: RequestType::CHANGE,
                source: RequestSource::FORM,
                ref: $ref,
                requesterName: $alt->reporter_name !== null ? (string) $alt->reporter_name : null,
                requesterEmail: (string) ($alt->reporter_email ?? ''),
                message: $nachricht,
                diff: $diff,
                erstellt: $alt->created_at,
                herkunft: "company_edit_suggestions #{$alt->id} (Feld „{$alt->field}“, Stand {$alt->status})"
                    .($verworfen !== null ? ". {$verworfen}" : ''),
            );

            $this->line("  übernommen: Änderung #{$alt->id}");
        }
    }

    /** Übernahmeanträge aus `claim_requests`, samt Nachweisdokument. */
    private function uebernahmeantraege(Tenant $tenant, bool $trocken, ?int $limit): void
    {
        $query = ClaimRequest::query()
            ->where('status', ClaimRequest::STATUS_PENDING)
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->get() as $alt) {
            $ref = (string) $alt->getKey();

            if ($this->schonDa($tenant, RequestType::CLAIM, $ref)) {
                $this->uebersprungen++;
                $this->line("  schon drin: Übernahme #{$alt->getKey()}");

                continue;
            }

            $company = Company::find($alt->company_id);

            if (! $company instanceof Company) {
                $this->uebersprungen++;
                $this->line("  Betrieb {$alt->company_id} fehlt — Übernahme #{$alt->getKey()} übersprungen");

                continue;
            }

            // Der Antragsteller war angemeldet; seine Adresse steht im
            // Konto in der Central-DB, nicht am Antrag.
            $user = User::find($alt->user_id);

            if ($trocken) {
                $this->uebernommen++;
                $this->line(sprintf(
                    '  [trocken] Übernahme #%d  Konto %s  Nachweis: %s',
                    $alt->getKey(),
                    $user instanceof User ? $user->email : "fehlt ({$alt->user_id})",
                    $alt->getMedia('claim_documents')->count() > 0 ? 'ja' : 'nein',
                ));

                continue;
            }

            $request = $this->anlegen(
                tenant: $tenant,
                company: $company,
                type: RequestType::CLAIM,
                source: RequestSource::CLAIM_FLOW,
                ref: $ref,
                requesterName: $user instanceof User ? (string) $user->name : null,
                requesterEmail: $user instanceof User ? (string) $user->email : '',
                message: $alt->comment !== null ? (string) $alt->comment : null,
                diff: [],
                erstellt: $alt->created_at,
                herkunft: "claim_requests #{$alt->getKey()}, Konto {$alt->user_id}",
            );

            if ($request instanceof ProfileRequest) {
                $this->nachweisKopieren($alt, $request);
            }

            $this->line("  übernommen: Übernahme #{$alt->getKey()}");
        }
    }

    /**
     * Nachweisdokument aus der Media-Collection auf die Anfragen-Platte
     * kopieren — kopieren, nicht verschieben: solange der alte Panel-Weg
     * lebt, muss der Nachweis auch dort sichtbar bleiben.
     *
     * Scheitert die Datei, bleibt die Anfrage bestehen und das Protokoll
     * hält fest, dass der Nachweis fehlt. Eine Übernahme ohne Anfrage wäre
     * das schlechtere Ergebnis.
     */
    private function nachweisKopieren(ClaimRequest $alt, ProfileRequest $request): void
    {
        $verzeichnis = RequestAttachment::directoryFor((int) $request->getKey());
        $aufbewahrung = now()->addDays((int) config('profile-requests.retention_days', 90));

        foreach ($alt->getMedia('claim_documents') as $media) {
            try {
                $endung = Str::lower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION) ?: 'bin');
                $ziel = $verzeichnis.'/'.Str::random(40).'.'.$endung;

                // stream() gibt null zurueck, wenn die Datei hinter dem
                // Media-Eintrag fehlt — lokal der Normalfall, weil der
                // Spiegel nur die Datenbank holt, nicht die Ablage.
                $strom = $media->stream();

                if (! is_resource($strom)) {
                    throw new RuntimeException('Datei zum Media-Eintrag fehlt auf der Ablage');
                }

                Storage::disk(RequestAttachment::DISK)->writeStream($ziel, $strom);

                if (is_resource($strom)) {
                    fclose($strom);
                }

                $request->attachments()->create([
                    'path' => $ziel,
                    'mime' => (string) $media->mime_type,
                    'size' => (int) $media->size,
                    'is_raw_mail' => false,
                    'purge_after' => $aufbewahrung,
                ]);
            } catch (Throwable $e) {
                $request->recordEvent(RequestEventType::NOTE, [
                    'note' => "Nachweisdokument konnte nicht übernommen werden: {$e->getMessage()}",
                ]);

                $this->warn("    Nachweis zu #{$alt->getKey()} nicht kopiert: {$e->getMessage()}");
            }
        }
    }

    /**
     * Anfrage anlegen — Status direkt `classified`, wie beim Formularweg:
     * Portal, Betrieb und Typ liegen strukturiert vor, es gibt nichts zu
     * klassifizieren. Erstellungsdatum des Altvorgangs bleibt erhalten,
     * sonst sieht ein zwei Jahre alter Wunsch im Panel wie ein neuer aus.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $diff
     */
    private function anlegen(
        Tenant $tenant,
        Company $company,
        RequestType $type,
        RequestSource $source,
        string $ref,
        ?string $requesterName,
        string $requesterEmail,
        ?string $message,
        array $diff,
        mixed $erstellt,
        string $herkunft,
    ): ?ProfileRequest {
        try {
            $request = DB::connection((new ProfileRequest)->getConnectionName())->transaction(
                function () use ($tenant, $company, $type, $source, $ref, $requesterName, $requesterEmail, $message, $diff, $erstellt): ProfileRequest {
                    $request = ProfileRequest::create([
                        'tenant_id' => (int) $tenant->getKey(),
                        'company_id' => $company->getKey(),
                        'type' => $type,
                        'source' => $source,
                        'status' => RequestStatus::CLASSIFIED,
                        'requester_name' => $requesterName,
                        'requester_email' => $requesterEmail,
                        'message' => $message,
                        'external_ref' => $ref,
                    ]);

                    foreach ($diff as $field => $werte) {
                        $request->changes()->create([
                            'field' => $field,
                            'old_value' => $werte['old'] ?? null,
                            'new_value' => $werte['new'] ?? null,
                        ]);
                    }

                    if ($erstellt !== null) {
                        $request->forceFill(['created_at' => $erstellt])->save();
                    }

                    return $request;
                }
            );
        } catch (Throwable $e) {
            $this->fehler++;
            $this->error("  {$herkunft}: {$e->getMessage()}");

            return null;
        }

        $request->recordEvent(RequestEventType::NOTE, [
            'note' => "Aus dem Altbestand übernommen: {$herkunft}. Keine Bestätigungsmail verschickt.",
        ], RequestEvent::ACTOR_SYSTEM);

        $this->uebernommen++;

        return $request;
    }

    /**
     * Sieht der Wert wie eine Rufnummer aus?
     *
     * Absichtlich streng: mindestens fünf Ziffern, und ausser Ziffern nur
     * das, was in einer Rufnummer vorkommt (Leerzeichen, Klammern, Schrägstrich,
     * Bindestrich, Plus, Punkt). Ein einziges Wort darin genügt, um den Wert
     * als Freitext zu behandeln — lieber kein Diff als eine Beschwerde als
     * Telefonnummer im Profil.
     */
    private function wirktWieRufnummer(string $wert): bool
    {
        $wert = trim($wert);

        if ($wert === '') {
            return false;
        }

        if (preg_match('/^[0-9+()\/\s.\-]+$/', $wert) !== 1) {
            return false;
        }

        return strlen(preg_replace('/\D/', '', $wert) ?? '') >= 5;
    }

    /**
     * Nachricht für das Panel. Wo kein Diff entstehen konnte, ist der
     * Wortlaut des Wunsches das Einzige, was den Vorgang bearbeitbar macht.
     */
    private function nachricht(object $alt, ?string $feld, bool $ohneDiff): string
    {
        $teile = [];

        if ($ohneDiff) {
            $bezeichnung = match ($alt->field) {
                'address' => 'Anschrift',
                'hours' => 'Öffnungszeiten',
                'description' => 'Beschreibung',
                'phone' => 'Telefon',
                'other' => 'Sonstiges',
                default => (string) $alt->field,
            };

            $teile[] = $feld === null
                ? "Wunsch zu „{$bezeichnung}“: ".$alt->suggested_value
                : "Wunsch zu „{$bezeichnung}“ (entspricht dem heutigen Stand): ".$alt->suggested_value;
        }

        if ($alt->reason !== null && trim((string) $alt->reason) !== '') {
            $teile[] = 'Begründung: '.$alt->reason;
        }

        return implode("\n\n", $teile);
    }

    /** Liegt dieser Altvorgang schon in der Inbox? */
    private function schonDa(Tenant $tenant, RequestType $type, string $ref): bool
    {
        return ProfileRequest::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', $type)
            ->where('external_ref', $ref)
            ->exists();
    }
}
