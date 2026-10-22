<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Support\RateLimitGuard;
use App\Models\Portal\Company;
use App\ProfileRequests\Actions\BuildChangeSet;
use App\ProfileRequests\Actions\CreateRequest;
use App\ProfileRequests\Enums\RequestType;
use App\Turnstile\Concerns\InteractsWithTurnstile;
use App\Turnstile\Enums\TurnstileAction;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * "Änderung vorschlagen" auf der Profilseite — der Weg für jemanden, der
 * nicht zum Betrieb gehört (sun-v2, /firma/{slug}/aenderung-vorschlagen).
 *
 * Je Kategorie ein eigenes Eingabefeld, das erst beim Anklicken aufklappt;
 * mehrere Kategorien gleichzeitig (#20). Vorher gab es acht Pillen zur
 * Einfachauswahl und EIN Textfeld für alles — wer drei Dinge korrigieren
 * wollte, schrieb sie in einen Block, und die Bearbeitung musste ihn
 * auseinanderpflücken.
 *
 * Gespeichert wird über {@see CreateRequest} in die zentrale Inbox (#1), also
 * denselben Weg wie der Inhaber-Dialog. {@see BuildChangeSet} baut daraus je
 * geändertem Feld **eine** Zeile in `request_changes`; die Diff-Komponente
 * (#11) zeigt sie als Feld / aktuell / neu mit Haken je Zeile.
 *
 * Vier Kategorien haben kein Zielfeld im Diff — Öffnungszeiten (hier bewusst
 * Freitext), "existiert nicht mehr", doppelter Eintrag und Sonstiges. Sie
 * gehen als `message` an die Anfrage, nicht als Änderungszeile.
 * "Existiert nicht mehr" macht daraus eine Löschanfrage.
 *
 * Reihenfolge der Schutzschicht unverändert (docs/turnstile.md §2):
 * Honigtopf/Mindestzeit still → Feldregeln → Rate-Limit → Turnstile zuletzt.
 */
class SuggestEditForm extends Component
{
    // Honigtopf und Mindest-Ausfuellzeit (#22); die Properties kommen aus dem
    // Trait, gebunden von <x-antispam-fields wire /> in der Ansicht. Der
    // frueher hier handgeschriebene Honigtopf `website_url` ist damit weg —
    // sein Feldname war fest und seine Treffer landeten in keinem Log.
    use InteractsWithAntiSpam;
    use InteractsWithTurnstile;

    public Company $company;

    /**
     * Kategorien, die der Melder aufgeklappt hat — mehrere gleichzeitig.
     * Die Schlüssel sind dieselben wie in `portal.suggest_edit.choices`.
     *
     * @var list<string>
     */
    public array $kategorien = [];

    /** Adresse: vier getrennte Felder, Zielfelder street/house_no/zipcode/city. */
    public string $street = '';

    public string $houseNo = '';

    public string $zipcode = '';

    public string $city = '';

    public string $tel = '';

    public string $website = '';

    /** Leistungen; Zielfeld `description`. */
    public string $description = '';

    /** Öffnungszeiten als Freitext — geht in die Anmerkung, nicht in den Diff. */
    public string $openingHours = '';

    /** Adresse des doppelten Eintrags; Anmerkung. */
    public string $duplicateOf = '';

    /** Sonstiges; Anmerkung. */
    public string $note = '';

    public string $reporterName = '';

    public string $reporterEmail = '';

    public bool $submitted = false;

    /** Kategorien in der Reihenfolge, in der das Formular sie anbietet. */
    public const KATEGORIEN = [
        'address',
        'phone',
        'website',
        'services',
        'hours',
        'duplicate',
        'closed',
        'other',
    ];

    /** Kategorie => Felder, die sie in den Diff schreibt. */
    private const DIFF_FELDER = [
        'address' => ['street' => 'street', 'houseNo' => 'house_no', 'zipcode' => 'zipcode', 'city' => 'city'],
        'phone' => ['tel' => 'tel'],
        'website' => ['website' => 'website'],
        'services' => ['description' => 'description'],
    ];

    /**
     * Vorbelegung mit dem aktuellen Stand: der Melder sieht, was dasteht, und
     * ändert nur, was falsch ist. Quelle ist {@see BuildChangeSet::currentValues()}
     * — dieselbe Stelle, gegen die später verglichen wird, damit Vorbelegung
     * und Diff nicht auseinanderlaufen.
     */
    public function mount(): void
    {
        $aktuell = app(BuildChangeSet::class)->currentValues($this->company);

        $this->street = (string) $aktuell['street'];
        $this->houseNo = (string) $aktuell['house_no'];
        $this->zipcode = (string) $aktuell['zipcode'];
        $this->city = (string) $aktuell['city'];
        $this->tel = (string) $aktuell['tel'];
        $this->website = (string) $aktuell['website'];
        $this->description = (string) $aktuell['description'];
    }

    /** Hat der Melder diese Kategorie aufgeklappt? */
    public function offen(string $kategorie): bool
    {
        return in_array($kategorie, $this->kategorien, true);
    }

    /**
     * Pflicht ist ein Feld nur, wenn seine Kategorie aufgeklappt ist — eine
     * nicht gewaehlte Kategorie darf nichts verlangen und nichts aendern.
     */
    protected function rules(): array
    {
        $wenn = fn (string $kategorie): object => Rule::requiredIf($this->offen($kategorie));

        return [
            'kategorien' => ['required', 'array', 'min:1'],
            'kategorien.*' => ['string', Rule::in(self::KATEGORIEN)],

            'street' => [$wenn('address'), 'string', 'max:120'],
            'houseNo' => ['nullable', 'string', 'max:20'],
            'zipcode' => [$wenn('address'), 'string', 'max:10'],
            'city' => [$wenn('address'), 'string', 'max:100'],

            'tel' => [$wenn('phone'), 'string', 'max:50'],
            'website' => [$wenn('website'), 'string', 'url', 'max:255'],
            'description' => [$wenn('services'), 'string', 'max:2000'],
            'openingHours' => [$wenn('hours'), 'string', 'max:2000'],
            'duplicateOf' => [$wenn('duplicate'), 'string', 'max:500'],
            'note' => [$wenn('other'), 'string', 'max:2000'],

            'reporterName' => ['nullable', 'string', 'max:100'],
            'reporterEmail' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return (array) __('portal.suggest_edit.attributes');
    }

    protected function messages(): array
    {
        return [
            'kategorien.required' => __('portal.suggest_edit.errors.no_category'),
            'kategorien.min' => __('portal.suggest_edit.errors.no_category'),
            'reporterEmail.email' => __('portal.suggest_edit.errors.email'),
        ];
    }

    public function submit(): void
    {
        // Honigtopf und Mindest-Ausfuellzeit (#22): still abweisen. Der
        // Besucher sieht dieselbe Erfolgsmeldung, es entsteht aber kein
        // Vorschlag. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::Contact, $this->reporterEmail ?: null)) {
            $this->submitted = true;

            return;
        }

        $this->validate();

        $diff = $this->diff();
        $anmerkung = $this->anmerkung();

        // Nichts Neues eingetragen: die Vorbelegung unverändert abzuschicken
        // legt sonst eine Anfrage ohne eine einzige Änderungszeile an.
        if ($diff === [] && $anmerkung === null) {
            $this->addError('kategorien', __('portal.suggest_edit.errors.nothing_changed'));

            return;
        }

        // Rate-Limit je IP-Hash und Portal (#22), Grenze in
        // config/antispam.php. Ersetzt den handgeschriebenen Zaehler, der auf
        // der Klartext-IP und portaluebergreifend lief.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::SUGGEST_EDIT);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::SUGGEST_EDIT, TurnstileAction::Contact, $this->reporterEmail ?: null);
            $this->addError('kategorien', $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        // Turnstile zuletzt: erst wenn alle Felder stimmen, wird ein Token
        // eingeloest — Cloudflare nimmt jedes Token nur einmal an.
        $this->validateTurnstile(TurnstileAction::Contact, 'reporterEmail');

        // In die zentrale Inbox, nicht mehr in CompanyEditSuggestion (#20):
        // gearbeitet wird in der Inbox, und nur dort entsteht je Feld eine
        // eigene Zeile mit Haken. Das Altsystem bleibt lesbar, wird aber nicht
        // mehr beschrieben — ein Vorgang an zwei Stellen waere doppelte Arbeit.
        app(CreateRequest::class)(
            company: $this->company,
            type: $this->offen('closed') ? RequestType::DELETE : RequestType::CHANGE,
            requester: [
                'name' => $this->reporterName ?: null,
                'email' => $this->reporterEmail,
                'phone' => null,
            ],
            diff: $diff,
            message: $anmerkung,
        );

        // Erst jetzt zaehlen: ein Tippfehler in der Validierung soll keinen
        // Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::SUGGEST_EDIT);

        $this->submitted = true;
    }

    /**
     * Diff über {@see BuildChangeSet}: je geändertem Feld eine Zeile, Felder
     * ohne Änderung fallen weg. Eingereicht werden nur die Felder der
     * aufgeklappten Kategorien — eine zugeklappte Kategorie darf nichts
     * aendern, auch nicht auf leer.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function diff(): array
    {
        $eingereicht = [];

        foreach (self::DIFF_FELDER as $kategorie => $felder) {
            if (! $this->offen($kategorie)) {
                continue;
            }

            foreach ($felder as $property => $feld) {
                $eingereicht[$feld] = trim((string) $this->{$property});
            }
        }

        return $eingereicht === [] ? [] : app(BuildChangeSet::class)($this->company, $eingereicht);
    }

    /**
     * Die vier Kategorien ohne Zielfeld im Diff als Anmerkung an der Anfrage.
     * Jede als eigene Zeile mit ihrer Beschriftung davor, damit in der Inbox
     * erkennbar bleibt, worum es geht.
     */
    private function anmerkung(): ?string
    {
        $zeilen = [];

        if ($this->offen('closed')) {
            $zeilen[] = __('portal.suggest_edit.notes.closed');
        }

        foreach (['hours' => $this->openingHours, 'duplicate' => $this->duplicateOf, 'other' => $this->note] as $kategorie => $wert) {
            $wert = trim($wert);

            if (! $this->offen($kategorie) || $wert === '') {
                continue;
            }

            $zeilen[] = __('portal.suggest_edit.choices.'.$kategorie).': '.$wert;
        }

        return $zeilen === [] ? null : implode("\n\n", $zeilen);
    }

    public function render()
    {
        return view('livewire.portal.suggest-edit-form');
    }
}
