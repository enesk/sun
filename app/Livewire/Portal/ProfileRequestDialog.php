<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Support\RateLimitGuard;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\ProfileRequests\Actions\BuildChangeSet;
use App\ProfileRequests\Actions\CreateRequest;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Support\MaskedEmail;
use App\ProfileRequests\Support\PilotGate;
use App\ProfileRequests\Verification\RequestVerifier;
use App\Turnstile\Concerns\InteractsWithTurnstile;
use App\Turnstile\Enums\TurnstileAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Self-Service am Firmenprofil (SUN-RQ #4): "Sie sind Inhaber? Eintrag
 * ändern oder löschen".
 *
 * Zwei Wege in einem Dialog, weil es derselbe Entschluss ist — am Eintrag
 * stimmt etwas nicht — und der Besucher erst beim Lesen merkt, welcher der
 * beiden es ist.
 *
 * **Aendern** ist mit den aktuellen Werten vorbelegt. Genau das ist der
 * Zeitgewinn des Vorhabens: aus "bitte Telefon korrigieren" wird ein Diff
 * ueber {@see BuildChangeSet}, den das Panel (#7) mit einem Klick uebernehmen
 * kann. Gespeichert werden nur die geaenderten Felder; der Vergleich laeuft
 * normalisiert, damit ein Leerzeichen hinter der Nummer keine Arbeit macht.
 *
 * **Loeschen** legt nur einen Antrag an. Hier wird nichts geloescht — das tut
 * nach Verifikation und Karenzfrist die Pipeline aus #8/#9.
 *
 * Keiner der beiden Wege setzt ein Konto voraus: 88 % der Anfragen kommen von
 * Profilen ohne Konto (docs/profile-requests.md 2.3). Autorisiert wird die
 * Anfrage deshalb nicht hier, sondern ueber den Magic-Link an die beim Profil
 * hinterlegte Firmen-E-Mail (#6). Turnstile ist Spamabwehr am Formular und
 * ausdruecklich **kein** Verifikationsweg.
 *
 * Die Felder werden erst beim Oeffnen geladen ({@see self::open()}) und nicht
 * in mount(): die Komponente steht auf jeder Profilseite, und Oeffnungszeiten
 * und Leistungen sind zwei Abfragen, die der Besucher in 99 von 100 Faellen
 * nicht braucht.
 */
class ProfileRequestDialog extends Component
{
    use InteractsWithAntiSpam;
    use InteractsWithTurnstile;
    use WithFileUploads;

    /** Gruende fuer einen Loeschantrag; der Wert geht in die Nachricht. */
    public const DELETE_REASONS = ['closed', 'moved', 'duplicate', 'never_existed', 'privacy', 'other'];

    public Company $company;

    /** '' = zu, sonst 'change' oder 'delete'. */
    public string $mode = '';

    public bool $submitted = false;

    /** Nummer der angelegten Anfrage — Bezug fuer die Rueckfrage. */
    public ?int $requestId = null;

    // ── Aenderbare Felder, vorbelegt in open() ──────────────────────────

    public string $name = '';

    public string $street = '';

    public string $house_no = '';

    public string $zipcode = '';

    public string $city = '';

    public string $tel = '';

    public string $email = '';

    public string $website = '';

    public string $description = '';

    /** @var list<array{day: int, closed: bool, opens: string, closes: string}> */
    public array $hours = [];

    /** @var list<array{name: string, price: string, description: string}> */
    public array $services = [];

    public mixed $logo = null;

    /** @var array<int, mixed> */
    public array $images = [];

    // ── Loeschweg ───────────────────────────────────────────────────────

    public string $deleteReason = '';

    public string $deleteNote = '';

    public bool $deleteConfirmed = false;

    // ── Anfragender ─────────────────────────────────────────────────────

    public string $requesterName = '';

    public string $requesterEmail = '';

    public string $requesterPhone = '';

    public function mount(Company $company): void
    {
        $this->company = $company;
    }

    /**
     * Dialog oeffnen und — beim Aenderungsweg — mit den aktuellen Werten
     * vorbelegen.
     */
    public function open(string $mode): void
    {
        $this->mode = $mode === 'delete' ? 'delete' : 'change';
        $this->submitted = false;
        $this->resetValidation();

        if ($this->mode === 'change' && $this->hours === []) {
            $this->prefill();
        }
    }

    public function close(): void
    {
        $this->mode = '';
        $this->resetValidation();
    }

    private function prefill(): void
    {
        $current = app(BuildChangeSet::class)->currentValues($this->company);

        $this->name = (string) $current['name'];
        $this->street = (string) $current['street'];
        $this->house_no = (string) $current['house_no'];
        $this->zipcode = (string) $current['zipcode'];
        $this->city = (string) $current['city'];
        $this->tel = (string) $current['tel'];
        $this->email = (string) $current['email'];
        $this->website = (string) $current['website'];
        $this->description = (string) $current['description'];
        $this->hours = $current['opening_hours'];
        $this->services = $current['services'];
    }

    // ── Editor-Hilfen ───────────────────────────────────────────────────

    public function addService(): void
    {
        $this->services[] = ['name' => '', 'price' => '', 'description' => ''];
    }

    public function removeService(int $index): void
    {
        unset($this->services[$index]);

        // Neu indizieren: Livewire bindet ueber den Index, eine Luecke wuerde
        // beim naechsten Umlauf die falsche Zeile treffen.
        $this->services = array_values($this->services);
    }

    public function removeImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    /** Tag auf "geschlossen" stellt die Zeiten der Zeile mit zurueck. */
    public function updatedHours(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, null);

        if ($field !== 'closed' || ! isset($this->hours[(int) $index])) {
            return;
        }

        if ((bool) $value) {
            $this->hours[(int) $index]['opens'] = '';
            $this->hours[(int) $index]['closes'] = '';
        }
    }

    // ── Diff ────────────────────────────────────────────────────────────

    /**
     * Der Diff zum aktuellen Stand — serverseitig, bei jedem Umlauf. Der
     * Dialog zeigt daraus die Zusammenfassung "Sie ändern: …", und derselbe
     * Aufruf entscheidet beim Absenden, was gespeichert wird.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changeSet(): array
    {
        $diff = app(BuildChangeSet::class)($this->company, [
            'name' => $this->name,
            'street' => $this->street,
            'house_no' => $this->house_no,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'tel' => $this->tel,
            'email' => $this->email,
            'website' => $this->website,
            'description' => $this->description,
            'opening_hours' => $this->hours,
            'services' => $this->services,
        ]);

        // Bilder haben keinen Textwert, den man vergleichen koennte: hier
        // zaehlt, dass eine Datei anliegt. Das Logo wird als Feld `logo`
        // gefuehrt (Whitelist, #9), weitere Bilder als `images` — nicht auf
        // der Whitelist, weil erst Enes entscheidet, wohin ein Foto gehoert.
        if ($this->logo instanceof TemporaryUploadedFile) {
            $diff['logo'] = [
                'old' => $this->company->getFirstMediaUrl('logo', 'medium') ?: null,
                'new' => ['upload' => $this->logo->getClientOriginalName()],
            ];
        }

        $images = array_filter($this->images, fn (mixed $file): bool => $file instanceof TemporaryUploadedFile);

        if ($images !== []) {
            $diff['images'] = [
                'old' => null,
                'new' => ['uploads' => array_values(array_map(
                    fn (TemporaryUploadedFile $file): string => $file->getClientOriginalName(),
                    $images,
                ))],
            ];
        }

        return $diff;
    }

    /**
     * Beschriftungen der geaenderten Felder fuer die Zusammenfassung.
     *
     * @return list<string>
     */
    public function changedLabels(): array
    {
        return app(BuildChangeSet::class)->labels($this->changeSet());
    }

    // ── Absenden ────────────────────────────────────────────────────────

    public function submit(): void
    {
        if ($this->mode === '') {
            return;
        }

        // Reihenfolge der Schutzschicht: Rate-Limit, Honigtopf/Zeit,
        // Feldpruefung, Turnstile zuletzt — Cloudflare loest jedes Token nur
        // einmal ein, es darf also nicht an einem Tippfehler verbrannt
        // werden.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::PROFILE_REQUEST, ['email' => $this->requesterEmail ?: null]);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::PROFILE_REQUEST, TurnstileAction::ProfileRequest, $this->requesterEmail ?: null);
            $this->addError('requesterEmail', $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        // Honigtopf und Mindest-Ausfuellzeit: still abweisen, Erfolg
        // vorspielen. Es entsteht keine Anfrage; der Treffer steht in
        // turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::ProfileRequest, $this->requesterEmail ?: null)) {
            $this->submitted = true;

            return;
        }

        $this->validate($this->rules(), $this->messages());

        $diff = [];

        if ($this->mode === 'change') {
            $diff = $this->changeSet();

            if ($diff === []) {
                $this->addError('mode', __('portal.profile_request.change.nothing_changed'));

                return;
            }
        }

        $this->validateTurnstile(TurnstileAction::ProfileRequest, 'requesterEmail');

        $request = app(CreateRequest::class)(
            company: $this->company,
            type: $this->mode === 'delete' ? RequestType::DELETE : RequestType::CHANGE,
            requester: [
                'name' => $this->requesterName,
                'email' => $this->requesterEmail,
                'phone' => $this->requesterPhone ?: null,
            ],
            diff: $diff,
            files: $this->uploads(),
            message: $this->mode === 'delete' ? $this->deleteMessage() : ($this->deleteNote ?: null),
        );

        // Erst nach dem Anlegen zaehlen: eine fehlgeschlagene Pruefung soll
        // keinen Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::PROFILE_REQUEST, ['email' => $this->requesterEmail ?: null]);

        // Verifikation anstossen (#6): Magic-Link an die beim Profil
        // hinterlegte Firmen-E-Mail — nie an die hier eingetippte Adresse.
        // Hat das Profil keine E-Mail, geht der Vorgang mit Kennzeichen
        // `manual` in die Inbox. Ein Fehler darf dem Absender nicht die
        // Bestaetigung wegnehmen: die Anfrage steht schon.
        try {
            app(RequestVerifier::class)->handle($request);
        } catch (\Throwable $e) {
            Log::warning('profile-requests: Verifikation nach Formular fehlgeschlagen', [
                'request_id' => $request->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }

        $this->requestId = (int) $request->getKey();
        $this->submitted = true;

        // Dateien aus dem temporaeren Verzeichnis nehmen; sie liegen jetzt
        // unter requests/{id}/.
        $this->logo = null;
        $this->images = [];
    }

    /**
     * Die Uploads als flache Liste — Logo zuerst, damit das Panel die
     * Reihenfolge der Anhaenge lesen kann.
     *
     * @return array<int, TemporaryUploadedFile>
     */
    private function uploads(): array
    {
        $files = [];

        if ($this->logo instanceof TemporaryUploadedFile) {
            $files[] = $this->logo;
        }

        foreach ($this->images as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function deleteMessage(): string
    {
        $reason = (string) __('portal.profile_request.delete.reasons.'.$this->deleteReason);

        return trim($reason.($this->deleteNote !== '' ? "\n\n".$this->deleteNote : ''));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $common = [
            'requesterName' => ['required', 'string', 'max:100'],
            'requesterEmail' => ['required', 'email', 'max:255'],
            'requesterPhone' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->mode === 'delete') {
            return $common + [
                'deleteReason' => ['required', 'string', 'in:'.implode(',', self::DELETE_REASONS)],
                'deleteNote' => [$this->deleteReason === 'other' ? 'required' : 'nullable', 'string', 'max:1000'],
                'deleteConfirmed' => ['accepted'],
            ];
        }

        return $common + [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_no' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'tel' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hours' => ['array', 'max:7'],
            'hours.*.closed' => ['boolean'],
            'hours.*.opens' => ['nullable', 'date_format:H:i'],
            'hours.*.closes' => ['nullable', 'date_format:H:i'],
            'services' => ['array', 'max:30'],
            'services.*.name' => ['nullable', 'string', 'max:150'],
            'services.*.price' => ['nullable', 'string', 'max:20'],
            'services.*.description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:5120'],
            'deleteNote' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'requesterName.required' => __('portal.profile_request.errors.name'),
            'requesterEmail.required' => __('portal.profile_request.errors.email'),
            'requesterEmail.email' => __('portal.profile_request.errors.email_invalid'),
            'name.required' => __('portal.profile_request.errors.company_name'),
            'zipcode.regex' => __('portal.profile_request.errors.zipcode'),
            'email.email' => __('portal.profile_request.errors.email_invalid'),
            'hours.*.opens.date_format' => __('portal.profile_request.errors.time'),
            'hours.*.closes.date_format' => __('portal.profile_request.errors.time'),
            'logo.image' => __('portal.profile_request.errors.image'),
            'logo.max' => __('portal.profile_request.errors.image_size'),
            'images.*.image' => __('portal.profile_request.errors.image'),
            'images.*.max' => __('portal.profile_request.errors.image_size'),
            'images.max' => __('portal.profile_request.errors.image_count'),
            'deleteReason.required' => __('portal.profile_request.errors.delete_reason'),
            'deleteNote.required' => __('portal.profile_request.errors.delete_note'),
            'deleteConfirmed.accepted' => __('portal.profile_request.errors.delete_confirm'),
        ];
    }

    // ── Anzeige ─────────────────────────────────────────────────────────

    /**
     * Die Adresse, an die der Bestaetigungslink geht: die beim Profil
     * hinterlegte Firmen-E-Mail (#6, docs/profile-requests.md 4), **nie** die
     * eingegebene. Fehlt sie, bleibt nur die Handpruefung.
     */
    public function verificationTarget(): string
    {
        return MaskedEmail::mask($this->company->email);
    }

    public function hasCompanyEmail(): bool
    {
        return MaskedEmail::mask($this->company->email) !== '';
    }

    /**
     * Link zur Status-Seite fuer Anfragende (#10). Die Route entsteht dort;
     * solange sie fehlt, zeigt der Dialog nur die Vorgangsnummer, statt in
     * einen 404 zu fuehren.
     */
    public function statusUrl(): ?string
    {
        if ($this->requestId === null || ! Route::has('portal.requests.status')) {
            return null;
        }

        return URL::signedRoute('portal.requests.status', ['request' => $this->requestId]);
    }

    /** @return array<int, string> */
    public function dayNames(): array
    {
        return CompanyOpeningHour::DAYS;
    }

    public function render()
    {
        // Rollout in zwei Stufen (#12): im Pilot steht der Dialog nur auf
        // den Portalen aus REQUESTS_PILOT_SELF_SERVICE_TENANTS. Die
        // Komponente bleibt auf jeder Profilseite eingebunden, sie rendert
        // dann nur nichts — so ist das Umschalten eine .env-Zeile und kein
        // Theme-Eingriff.
        if (! PilotGate::selfServiceEnabled()) {
            return <<<'HTML'
            <div></div>
            HTML;
        }

        return view('livewire.portal.profile-request-dialog');
    }
}
