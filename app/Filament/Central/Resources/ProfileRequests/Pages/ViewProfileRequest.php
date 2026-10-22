<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\ProfileRequests\Pages;

use App\Companies\Deletion\DeleteCompanyAction;
use App\Filament\Central\Resources\ProfileRequestResource;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\ProfileRequests\Actions\ApplyChanges;
use App\ProfileRequests\Actions\ApproveClaim;
use App\ProfileRequests\Actions\AssignCompany;
use App\ProfileRequests\Actions\RejectRequest;
use App\ProfileRequests\Actions\RevertChanges;
use App\ProfileRequests\Actions\SendReply;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Enums\VerificationMethod;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use App\ProfileRequests\Verification\RequestVerifier;
use App\Support\Tenancy\TenantMailBranding;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Detailansicht eines Vorgangs (SUN-RQ #7).
 *
 * Links die Anfrage (Originaltext, Anhaenge, KI-Zusammenfassung,
 * Verifikation, Protokoll), rechts das Profil im Portal als Vorschau mit
 * Link auf die Live-Seite, darunter der Diff als eigene Livewire-Komponente
 * ({@see \App\Livewire\Central\RequestDiffTable}, #11).
 *
 * **Geschrieben wird nur ueber die Actions** in
 * `App\ProfileRequests\Actions`. Diese Seite laeuft im Central-Kontext und
 * fasst kein Tenant-Model an; jede Action oeffnet den Portal-Kontext selbst
 * und schliesst ihn wieder.
 *
 * Tastaturkuerzel (Ticket): `mod+enter` übernehmen, `mod+shift+x` ablehnen,
 * `mod+shift+n` nächste Anfrage.
 */
class ViewProfileRequest extends ViewRecord
{
    protected static string $resource = ProfileRequestResource::class;

    protected string $view = 'filament.central.profile-request-view';

    /**
     * Der Reiter der Liste, aus dem dieser Vorgang geoeffnet wurde
     * ({@see ListProfileRequests::getTabs()}). Steht in der Adresse, damit
     * „Weiter" dieselbe Auswahl weiterblaettert und ueber die Kette aller
     * Vorgaenge erhalten bleibt.
     */
    #[Url]
    public ?string $tab = null;

    public function getTitle(): string|Htmlable
    {
        return 'REQ-'.$this->getRecord()->getKey();
    }

    public function getSubheading(): ?string
    {
        $record = $this->profileRequest();

        return sprintf(
            '%s · %s · %s',
            $record->type->label(),
            $record->source->label(),
            $record->status->label(),
        );
    }

    /**
     * Eigener Seitenkopf: Überschrift oben, Knöpfe in einer Zeile darunter.
     *
     * Der Standardkopf setzt beides nebeneinander. Bei sieben Aktionen bleibt
     * für „REQ-127" und die Unterzeile eine schmale Spalte übrig, in der
     * jedes Wort einzeln umbricht.
     */
    public function getHeader(): ?View
    {
        return view('filament.central.profile-request-header', [
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->applyAction(),
            $this->deleteProfileAction(),
            $this->rejectAction(),
            $this->assignAction(),
            $this->requestVerificationAction(),
            $this->manualVerificationAction(),
            $this->replyAction(),
            $this->revertAction(),
            // Derselbe Knopf wie in der Übersicht (#23) — ein Vorgang, den
            // Enes gerade offen hat, soll nicht zurück in die Liste nötigen.
            ProfileRequestResource::reclassifyAction()
                ->record($this->profileRequest())
                ->after(fn () => $this->refreshRequest()),
            $this->nextAction(),
        ];
    }

    /** Keine Infoliste — der Inhalt steht vollstaendig im eigenen Blade. */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    // ------------------------------------------------------------------
    // Aktionen
    // ------------------------------------------------------------------

    private function applyAction(): Action
    {
        return Action::make('uebernehmen')
            ->label(fn (): string => $this->profileRequest()->type === RequestType::CLAIM
                ? __('Übernahme freigeben')
                : __('Übernehmen'))
            ->icon('heroicon-m-check')
            ->color('success')
            ->keyBindings(['mod+enter'])
            ->visible(fn (): bool => $this->profileRequest()->isOpen()
                && $this->profileRequest()->type !== RequestType::DELETE)
            ->disabled(fn (): bool => ! $this->hasCompany())
            ->tooltip(fn (): ?string => $this->hasCompany() ? null : __('Erst einen Betrieb zuordnen.'))
            // Ohne Rueckfrage: der Diff mit seinen Haken steht sichtbar auf
            // derselben Seite, und das Abnahmekriterium sind drei Klicks von
            // der Liste bis zur Uebernahme. Rueckfragen gibt es dort, wo
            // sich etwas nicht zurueckdrehen laesst — bei der Loeschung.
            ->action(function (ApplyChanges $applyChanges, ApproveClaim $approveClaim): void {
                $request = $this->profileRequest();

                // Eine Uebernahme hat keine Diff-Zeilen: sie setzt den
                // Inhaber, nicht einzelne Felder. Ueber ApplyChanges lief sie
                // darum immer in „keine Zeile angehakt" (Enes, 22.10.2026).
                // Der Weg ist derselbe wie bei der Automatik (#6):
                // Betrieb an das Konto des Antragstellers, Rolle
                // `company_owner`, Freigabemail — nur mit Enes als Pruefer.
                $result = $request->type === RequestType::CLAIM
                    ? $this->approveClaim($request, $applyChanges, $approveClaim)
                    : $applyChanges($request, $this->actor());

                $this->notifyResult($result);
                $this->refreshRequest();
            });
    }

    /**
     * Uebernahme freigeben.
     *
     * Erst verifizieren (der Zustandsautomat laesst `applied` nur aus
     * `verified` zu — wer im Panel freigibt, hat den Nachweis gesehen),
     * dann {@see ApproveClaim}. Der Grund eines Fehlschlags steht als Notiz
     * im Protokoll des Vorgangs, deshalb verweist die Meldung dorthin.
     *
     * @return array{ok: bool, message: string}
     */
    private function approveClaim(ProfileRequest $request, ApplyChanges $applyChanges, ApproveClaim $approveClaim): array
    {
        if (! $applyChanges->ensureVerified($request, $this->actor())) {
            return ['ok' => false, 'message' => __('Der Vorgang ließ sich nicht bestätigen — Übernahme nicht freigegeben.')];
        }

        if (! $approveClaim($request, $this->actor())) {
            return ['ok' => false, 'message' => __('Übernahme nicht freigegeben — der Grund steht im Protokoll.')];
        }

        return ['ok' => true, 'message' => __('Übernahme freigegeben — der Betrieb gehört jetzt dem Antragsteller.')];
    }

    private function deleteProfileAction(): Action
    {
        return Action::make('loeschung')
            ->label(__('Löschung ausführen'))
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->visible(fn (): bool => $this->profileRequest()->isOpen()
                && $this->profileRequest()->type === RequestType::DELETE)
            ->disabled(fn (): bool => ! $this->hasCompany())
            ->tooltip(fn (): ?string => $this->hasCompany() ? null : __('Erst einen Betrieb zuordnen.'))
            ->requiresConfirmation()
            ->modalHeading(__('Profil endgültig löschen?'))
            ->modalDescription(__('Die Löschpipeline entfernt Profil, Bilder, Bewertungen und Statistiken, anonymisiert Leads und setzt die URL auf 410. Das lässt sich nicht zurückdrehen.'))
            ->modalSubmitActionLabel(__('Löschung starten'))
            ->action(function (DeleteCompanyAction $deleteCompany, ApplyChanges $applyChanges): void {
                $request = $this->profileRequest();

                // Der Zustandsautomat laesst `applied` nur aus `verified` zu;
                // die Pipeline schliesst den Vorgang selbst ab.
                $applyChanges->ensureVerified($request, $this->actor());

                try {
                    $result = $deleteCompany->forRequest($request);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title(__('Löschung fehlgeschlagen'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($result->ok() ? __('Profil gelöscht') : __('Löschung mit offenen Schritten'))
                    ->body(__('Protokoll steht im Vorgang und in deletion_log.'))
                    ->status($result->ok() ? 'success' : 'warning')
                    ->send();

                $this->refreshRequest();
            });
    }

    private function rejectAction(): Action
    {
        return Action::make('ablehnen')
            ->label(__('Ablehnen'))
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->keyBindings(['mod+shift+x'])
            ->visible(fn (): bool => $this->profileRequest()->isOpen())
            ->schema([
                Textarea::make('reason')
                    ->label(__('Grund'))
                    ->helperText(__('Steht im Protokoll und auf der Status-Seite des Anfragenden.'))
                    ->rows(4)
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (array $data, RejectRequest $reject): void {
                $result = $reject($this->profileRequest(), (string) $data['reason'], $this->actor());

                $this->notifyResult($result);
                $this->refreshRequest();
            });
    }

    private function assignAction(): Action
    {
        return Action::make('betrieb_zuordnen')
            ->label(__('Betrieb zuordnen'))
            ->icon('heroicon-m-link')
            ->color('gray')
            ->modalWidth(Width::TwoExtraLarge)
            ->visible(fn (): bool => $this->profileRequest()->isOpen())
            ->fillForm(fn (): array => [
                'tenant_id' => $this->profileRequest()->tenant_id,
                'company_id' => $this->profileRequest()->company_id,
            ])
            ->schema([
                Select::make('tenant_id')
                    ->label('Portal')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    // Ein Portalwechsel macht die bisherige Firmen-ID
                    // ungueltig — sie zeigt dann in eine fremde Datenbank.
                    ->afterStateUpdated(fn (callable $set) => $set('company_id', null)),
                Select::make('company_id')
                    ->label(__('Betrieb'))
                    ->helperText(__('Vorschläge stehen oben in der Anfrage. Hier nach Name oder E-Mail suchen.'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => filled($get('tenant_id'))
                        ? app(AssignCompany::class)->search((int) $get('tenant_id'), $search)
                        : [])
                    ->getOptionLabelUsing(fn (mixed $value, callable $get): ?string => filled($value) && filled($get('tenant_id'))
                        ? app(AssignCompany::class)->option((int) $get('tenant_id'), (int) $value)
                        : null)
                    ->nullable(),
            ])
            ->action(function (array $data, AssignCompany $assign): void {
                $result = $assign(
                    $this->profileRequest(),
                    (int) $data['tenant_id'],
                    filled($data['company_id'] ?? null) ? (int) $data['company_id'] : null,
                    $this->actor(),
                );

                $this->notifyResult($result);
                $this->refreshRequest();
            });
    }

    private function requestVerificationAction(): Action
    {
        return Action::make('verifikation_anfordern')
            ->label(__('Verifikation anfordern'))
            ->icon('heroicon-m-envelope')
            ->color('gray')
            ->visible(fn (): bool => $this->profileRequest()->isOpen() && ! $this->profileRequest()->isVerified())
            ->requiresConfirmation()
            ->modalHeading(__('Bestätigungslink an den Betrieb senden?'))
            ->modalDescription(__('Der Link geht an die beim Profil hinterlegte Firmen-E-Mail, nie an den Absender der Anfrage.'))
            ->action(function (RequestVerifier $verifier, ApplyChanges $applyChanges): void {
                $request = $this->profileRequest();

                if ($request->status === RequestStatus::NEW) {
                    $request->markClassified([], RequestEvent::ACTOR_USER, $this->actorId());
                }

                $ergebnis = $request->status === RequestStatus::AWAITING_VERIFICATION
                    ? ($verifier->resendMagicLink($request) ? VerificationMethod::MAGIC_LINK : null)
                    : $verifier->handle($request);

                Notification::make()
                    ->title($ergebnis === null
                        ? __('Kein Bestätigungslink möglich')
                        : __('Weg: :method', ['method' => $ergebnis->label()]))
                    ->body($ergebnis === null
                        ? __('Keine Firmen-E-Mail, Tageslimit erreicht oder der Vorgang passt nicht. Manuell verifizieren bleibt möglich.')
                        : __('Der Vorgang steht im Protokoll.'))
                    ->status($ergebnis === null ? 'warning' : 'success')
                    ->send();

                $this->refreshRequest();
            });
    }

    private function manualVerificationAction(): Action
    {
        return Action::make('manuell_verifizieren')
            ->label(__('Manuell verifizieren'))
            ->icon('heroicon-m-hand-thumb-up')
            ->color('gray')
            ->visible(fn (): bool => $this->profileRequest()->isOpen() && ! $this->profileRequest()->isVerified())
            ->requiresConfirmation()
            ->modalHeading(__('Anfrage von Hand bestätigen?'))
            ->modalDescription(__('Gilt als Autorisierung durch den Betrieb — zum Beispiel nach einem Nachweis im Anhang. Handgeprüfte Vorgänge laufen nie in die Auto-Regeln.'))
            // Ueber ApplyChanges::ensureVerified() und nicht ueber
            // RequestVerifier::markVerified(): nur dieser Weg traegt die
            // Nutzer-ID ins Protokoll. Die Freigabe einer Uebernahme, die
            // sonst am Verifier haengt, wird darum hier angestossen.
            ->action(function (ApplyChanges $applyChanges, ApproveClaim $approveClaim): void {
                $request = $this->profileRequest();
                $ok = $applyChanges->ensureVerified($request, $this->actor());

                if ($ok && $request->type === RequestType::CLAIM) {
                    $approveClaim($request, $this->actor());
                }

                Notification::make()
                    ->title($ok ? __('Als bestätigt vermerkt') : __('Nicht möglich'))
                    ->status($ok ? 'success' : 'warning')
                    ->send();

                $this->refreshRequest();
            });
    }

    private function replyAction(): Action
    {
        return Action::make('antwort_senden')
            ->label(__('Antwort senden'))
            ->icon('heroicon-m-paper-airplane')
            ->color('gray')
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (SendReply $sendReply): array => $sendReply->draft($this->profileRequest()))
            ->schema([
                TextInput::make('betreff')
                    ->label(__('Betreff'))
                    ->helperText(__('Die Marke [REQ-…] bleibt stehen — nur damit landet die Antwort wieder in diesem Vorgang.'))
                    ->required()
                    ->maxLength(200),
                Textarea::make('nachricht')
                    ->label(__('Nachricht'))
                    ->helperText(__('Entwurf der KI, frei bearbeitbar. Verschickt wird nur, was hier steht.'))
                    ->rows(14)
                    ->required(),
            ])
            ->action(function (array $data, SendReply $sendReply): void {
                $result = $sendReply(
                    $this->profileRequest(),
                    (string) $data['betreff'],
                    (string) $data['nachricht'],
                    $this->actor(),
                );

                $this->notifyResult($result);
                $this->refreshRequest();
            });
    }

    /**
     * „Rueckgaengig" fuer eine automatisch uebernommene Aenderung (#9).
     *
     * Nur dort sichtbar: was Enes selbst uebernommen hat, war eine
     * Entscheidung. Die Altwerte stehen in `request_changes.old_value`;
     * {@see RevertChanges} schreibt sie zurueck und haengt eine
     * Protokollzeile an, ohne den Status zu bewegen — `applied` ist ein
     * Endzustand des Automaten.
     */
    private function revertAction(): Action
    {
        return Action::make('rueckgaengig')
            ->label(__('Rückgängig'))
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (): bool => app(RevertChanges::class)->isRevertable($this->profileRequest()))
            ->requiresConfirmation()
            ->modalHeading(__('Automatische Übernahme zurückdrehen?'))
            ->modalDescription(__('Die Felder werden auf die Werte gesetzt, die vor der Übernahme im Profil standen. Bilder bleiben, wie sie sind.'))
            ->modalSubmitActionLabel(__('Zurückdrehen'))
            ->action(function (RevertChanges $revert): void {
                $result = $revert($this->profileRequest(), $this->actor());

                $this->notifyResult($result);
                $this->refreshRequest();
            });
    }

    /**
     * Der naechste offene Vorgang — aelteste zuerst, wie in der Liste. Ohne
     * Umweg ueber die Inbox, damit das Abarbeiten ein Tastendruck bleibt.
     */
    private function nextAction(): Action
    {
        return Action::make('naechste')
            ->label(__('Nächste'))
            ->icon('heroicon-m-arrow-right')
            ->color('gray')
            ->keyBindings(['mod+shift+n'])
            ->action(function () {
                $next = $this->nextRequest();

                if ($next === null) {
                    Notification::make()
                        ->title(__('Keine weitere Anfrage in dieser Auswahl'))
                        ->success()
                        ->send();

                    return null;
                }

                return redirect(self::getUrl(
                    filled($this->tab)
                        ? ['record' => $next, 'tab' => $this->tab]
                        : ['record' => $next],
                ));
            });
    }

    /**
     * Der naechste Vorgang **in derselben Auswahl und in derselben
     * Richtung** wie die Liste (`created_at` absteigend, dann `id`).
     *
     * Bis zum 22.10.2026 nahm der Knopf schlicht den neuesten offenen
     * Vorgang ausser dem aktuellen. Damit pendelte er zwischen den beiden
     * obersten hin und her und kam nie weiter. Jetzt wird strikt
     * **hinter** dem aktuellen Vorgang weitergesucht — und nur innerhalb des
     * Reiters, aus dem er geoeffnet wurde: wer die Uebernahmen abarbeitet,
     * bekommt die naechste Uebernahme.
     */
    private function nextRequest(): ?ProfileRequest
    {
        $current = $this->profileRequest();

        $query = ProfileRequest::query();

        $this->scopeToTab($query, $current);

        return $query
            // Der Cursor der Liste: alles, was unter dieser Zeile stand.
            ->where(function (Builder $builder) use ($current): void {
                $builder
                    ->where('created_at', '<', $current->created_at)
                    ->orWhere(function (Builder $gleich) use ($current): void {
                        $gleich
                            ->where('created_at', '=', $current->created_at)
                            ->where('id', '<', $current->getKey());
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Die Auswahl des Reiters nachbilden. Ohne bekannten Reiter — etwa bei
     * einem Link aus einer Mail — gilt „offen vom selben Typ": das ist die
     * Reihe, die Enes gerade abarbeitet, und nie der ganze Stapel.
     *
     * @param  Builder<ProfileRequest>  $query
     */
    private function scopeToTab(Builder $query, ProfileRequest $current): void
    {
        $offen = RequestStatus::openValues();

        match ($this->tab) {
            'aenderung' => $query->whereIn('status', $offen)->where('type', RequestType::CHANGE->value),
            'loeschung' => $query->whereIn('status', $offen)->where('type', RequestType::DELETE->value),
            'uebernahme' => $query->whereIn('status', $offen)->where('type', RequestType::CLAIM->value),
            'sonstiges' => $query->whereIn('status', $offen)->where('type', RequestType::OTHER->value),
            'offen' => $query->whereIn('status', $offen),
            'bestaetigt' => $query->where('status', RequestStatus::VERIFIED->value),
            'erledigt' => $query->whereNotIn('status', $offen),
            'alle' => $query,
            default => $query->whereIn('status', $offen)->where('type', $current->type->value),
        };
    }

    // ------------------------------------------------------------------
    // Daten fuer das Blade
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $request = $this->profileRequest();
        $company = $request->company(withTrashed: true);

        return [
            'profileRequest' => $request,
            'company' => $company,
            'profile' => $this->profileSnapshot($company),
            'liveUrl' => $this->liveUrl($company),
            'candidates' => $request->company_id === null ? app(AssignCompany::class)->candidates($request) : [],
            'events' => $request->events()->get(),
            'documents' => $request->attachments()
                ->where('is_raw_mail', false)
                ->get()
                ->reject(fn ($attachment): bool => $attachment->isImage())
                ->values(),
        ];
    }

    /**
     * Die Profilvorschau. Gelesen wird im Portal-Kontext und sofort in ein
     * Array ueberfuehrt — ein Tenant-Model, das im Blade weiterlebt, wuerde
     * beim naechsten Zugriff auf eine Relation gegen die Central-DB laufen.
     *
     * @return array<string, string|null>|null
     */
    private function profileSnapshot(?Company $company): ?array
    {
        if ($company === null) {
            return null;
        }

        return $this->profileRequest()->runInTenantContext(fn (): array => [
            'name' => (string) $company->name,
            'adresse' => trim((string) $company->street.' '.(string) $company->house_no),
            'ort' => trim((string) $company->zipcode.' '.(string) ($company->city->name ?? '')),
            'tel' => $company->tel,
            'email' => $company->email,
            'website' => $company->website,
            'beschreibung' => $company->description,
            'inhaber' => $company->user_id === null ? null : 'Konto #'.$company->user_id,
            'zustand' => $company->trashed()
                ? 'gelöscht'
                : ($company->is_active ? 'aktiv' : 'inaktiv'),
            'logo' => $company->logo_thumb_url ?? $company->logo_url,
        ]);
    }

    /**
     * Adresse des Profils auf **seiner** Portal-Domain.
     *
     * Nicht `$company->portal_url`: das geht über `route()`, und `route()`
     * baut den Host aus `config('app.url')`. Im Panel ist das die zentrale
     * Domain — der Link führte damit auf sanitaerfinden.com, wo es dieses
     * Profil nicht gibt, und endete im 404.
     *
     * Deshalb hier nur der **Pfad** aus dem Tenant-Kontext (`portal_path`
     * kennt beide URL-Muster, mit und ohne Stadt-Segment) und davor die
     * Domain des Portals aus dem Tenant.
     */
    private function liveUrl(?Company $company): ?string
    {
        if ($company === null || $company->trashed()) {
            return null;
        }

        $request = $this->profileRequest();

        $pfad = $request->runInTenantContext(fn (): string => (string) $company->portal_path);

        if (! is_string($pfad) || $pfad === '') {
            return null;
        }

        $tenant = $request->tenant_id === null
            ? null
            : app(TenantCompanyResolver::class)->tenant((int) $request->tenant_id);

        return TenantMailBranding::for($tenant)->url($pfad);
    }

    // ------------------------------------------------------------------
    // Kleinkram
    // ------------------------------------------------------------------

    private function profileRequest(): ProfileRequest
    {
        /** @var ProfileRequest $record */
        $record = $this->getRecord();

        return $record;
    }

    private function hasCompany(): bool
    {
        $request = $this->profileRequest();

        return $request->tenant_id !== null && $request->company_id !== null;
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function actorId(): ?int
    {
        $key = $this->actor()?->getKey();

        return $key === null ? null : (int) $key;
    }

    /** @param  array{ok: bool, message: string}  $result */
    private function notifyResult(array $result): void
    {
        Notification::make()
            ->title($result['message'])
            ->status($result['ok'] ? 'success' : 'warning')
            ->send();
    }

    /**
     * Nach jeder Aktion neu laden: Status, Protokoll und Profilvorschau
     * haben sich geaendert, und die Diff-Komponente haengt an denselben
     * Zeilen.
     */
    private function refreshRequest(): void
    {
        $this->record = $this->getRecord()->fresh();
        $this->profileRequest()->forgetCompany();

        // Der Menue-Zaehler haengt an einem 60-Sekunden-Cache; nach einer
        // Entscheidung soll er sofort stimmen.
        Cache::forget('profile-requests.open_count');
    }
}
