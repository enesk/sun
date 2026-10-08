# Security-Review des Bot-Schutzes

Status: **abgeschlossen, ohne offene Punkte im Modulumfang**
Ticket: #13
Prüfdatum: 2026-10-08, Nachprüfung 2026-10-08 (§12), Stand Arbeitskopie (das Modul ist
noch nicht eingecheckt, siehe `docs/turnstile-golive.md` §0, B2)
Umfang: `app/Turnstile/**`, `app/AntiSpam/**`, `config/turnstile.php`,
`config/antispam.php`, `config/csp.php`, `app/Http/Middleware/ContentSecurityPolicy.php`,
alle öffentlichen Wege zur Kontoanlage und zur Firmeneintragung, die Filament-Seiten
*Bot-Schutz*, der Datenschutzabschnitt in `app/Constants/TenantLegalDefaults.php`.
Architektur und Begründungen: `docs/turnstile.md`. Durchführung: `docs/turnstile-golive.md`.

Bewertung je Feld: **ok** / **Lücke** (Schweregrad niedrig / mittel / hoch).
Ein Punkt ist erst geschlossen, wenn er in §11 auf „behoben“ mit Commit steht.

---

## 1. Secret-Handling — ok

* `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` und die Paare `_B`/`_C` stehen in
  `config/turnstile.php:78-79, 107-138` per `env()` **ohne Vorgabewert**. Ein Literal als
  zweiter Parameter wäre ein eingecheckter Schlüssel und würde von
  `scripts/secret-scan.php` in Pre-Commit-Hook und CI abgewiesen.
* Der Secret verlässt den Server nur im Rumpf des Siteverify-POSTs
  (`TurnstileVerifier::post()`). Er steht in keiner Log-Zeile und in keiner Ausnahme:
  `TurnstileVerifier::report()` loggt ausschließlich `ResolvedTurnstileConfig::toLogContext()`
  und den Klassennamen der Ausnahme.
* Im Frontend liegt nur der **Sitekey** (`data-sitekey` in
  `resources/views/components/turnstile.blade.php`), und der ist öffentlich.
* Portaleigene Schlüssel: `tenant_turnstile_settings.secret_key` hat den Cast `encrypted`
  und steht in `$hidden` (`TenantTurnstileSetting.php:58, :64`) — also nicht in Logs,
  nicht in Telescope, nicht in JSON.
* Filament (#9): das Feld ist `->password()->revealable(false)->autocomplete(false)`, wird
  beim Formularaufbau **nicht** vorbelegt (`BotProtectionSettings.php:411`), ein leeres
  Feld lässt den bisherigen Wert stehen, entfernt wird nur über einen eigenen Haken
  (`BotProtectionSettings.php:288-292`). Angezeigt wird „gesetzt / nicht gesetzt“
  (`TenantTurnstileSetting::secretState()`).
* Der Cloudflare-API-Token (Widgets anlegen) liegt in `/root/sun-zugang.txt` (chmod 600),
  **nicht** in einer `.env` und nicht im Repo. Die Anwendung braucht ihn nie.
* Kein halbes Schlüsselpaar: hat eine Gruppe nur einen der beiden Werte, gilt sie als
  nicht konfiguriert (`TurnstileConfigResolver::groupKeys()`); dasselbe für ein Portal mit
  eigenem Widget (`resolve()`, Zweig `hasOwnSecret()`).

**Bewusste Entscheidung, kein Befund:** `.env.example:163-164` führt die Cloudflare-
Testschlüssel als Vorgabe, damit eine frische Installation läuft, ohne ein Formular zu
blockieren. In Produktion sind sie keine Lücke, sondern ein lautes Scheitern: der Resolver
wirft `TurnstileNotConfiguredException` („es ist noch ein Cloudflare-Testschlüssel
hinterlegt“), `turnstile:keys:check` gibt Exit 1, und `scripts/turnstile-schluessel-eintragen.sh`
weist sie bei der Eingabe ab. Abgenommen wird das in `docs/turnstile-golive.md` §1.2.

## 2. Hostname- und Action-Check — ok

Beides ist Pflicht und läuft **nach** `success` in der einzigen Enforcement-Stelle
(`TurnstileRule::checkHostname()`, `checkAction()`).

* Ein Widget trägt bis zu zehn Hostnames, gilt also für mehrere Portale. Ohne den
  Hostname-Vergleich wäre ein auf `fahrschulefinder.de` gelöstes Token auf
  `elektrikerportal.com` einreichbar. Erlaubt sind nur `tenants.domain` des laufenden
  Portals, der Host der Anfrage und die `www`-Variante beider, kleingeschrieben und ohne
  Port (`HostnameMatcher`). Ein leerer gemeldeter Hostname oder eine leere Erlaubnisliste
  gelten als **nicht** passend (`matches()` gibt `false`).
* Der Action-Vergleich verhindert dasselbe zwischen zwei Formularen eines Portals: ein
  Token aus dem Eintragsformular besteht die Registrierung nicht.
* Beide Prüfungen sind nur über `TURNSTILE_VERIFY_HOSTNAME` / `TURNSTILE_VERIFY_ACTION`
  abschaltbar — ausdrücklich als Mittel für einen Zwischenfall, Vorgabe `true`
  (`config/turnstile.php:209-210`). Der Go-Live prüft in §2 der Checkliste, dass beide
  Gegenproben (`hostname-mismatch`, `action-mismatch`) in Produktion tatsächlich greifen.
* Ausnahme `skipsOriginChecks()`: mit den Testschlüsseln antwortet Siteverify immer
  `hostname: example.com` ohne `action`, ein Vergleich würde auf local/staging jedes
  Formular blockieren und nichts belegen. **In Produktion kann der Zweig nicht greifen**,
  weil der Resolver bei einem Testschlüssel vorher wirft (§1) — die Ausnahme hängt also
  nicht an einem abschaltbaren Schalter, sondern an einem Zustand, den Produktion nicht
  annehmen kann.
* Siteverify wird nur an einer Stelle aufgerufen; `TurnstileVerifier::guardCaller()` setzt
  das zur Laufzeit durch (erlaubt sind `TurnstileRule` und `turnstile:verify`) und wirft
  sonst `LogicException`. Die Architekturregel steht damit nicht nur in der Doku.

**Restrisiko, akzeptiert (niedrig):** `HostnameMatcher::allowedFor()` nimmt den Host der
Anfrage auf. Der stammt aus dem `Host`-Header, eine Allowlist über
`TrustAnyHost`/`TrustedHosts` ist nicht gesetzt. Ein Angreifer kann damit kein fremdes
Token einlösen — das Token muss gegen **unseren** Secret bestehen, der gemeldete Hostname
ist also immer einer unserer eigenen Portal-Hostnames, und nginx routet dieselbe Anfrage
ohnehin anhand desselben Headers. Der Rest ist Token-Wiederverwendung zwischen zwei
eigenen Portalen, und ein Token ist bei Cloudflare nur einmal einlösbar.

## 3. Fail-Mode open — bewusste Entscheidung, kein Befund

`open` ist gesetzt (`config/turnstile.php:165`) und wird in keiner Rollout-Stufe auf
`closed` gestellt. Begründung in `docs/turnstile.md` §5: ein Ausfall bei Cloudflare darf
nicht alle Registrierungen und Eintragungen aller 23 Portale stilllegen.

Was dabei **nicht** fail-open ist, und das ist der sicherheitsrelevante Teil:

* Ein fehlendes oder leeres Token fällt immer durch. `TurnstileRule::$implicit = true`
  sorgt dafür, dass Laravel die Rule auch bei leerem Wert aufruft — ohne diese Zeile
  würde die Rule nur schützen, wenn der Angreifer irgendetwas mitsendet.
* Ein abgewiesenes Token (`success: false`, Hostname- oder Action-Fehler) fällt immer
  durch; fail-open greift ausschließlich bei `VerificationOutcome::Error`, also wenn
  Cloudflare gar nicht geantwortet hat.
* Jeder Fall wird geloggt und löst `SiteverifyUnreachable` (SUN-TS-011) aus; Honeypot,
  Mindest-Ausfüllzeit und Rate-Limits greifen unabhängig weiter.
* Der Circuit Breaker spart im Ausfall die Wartezeit (`timeout_seconds`), er ändert das
  Urteil nicht: offen heißt `Error`, also Fail-Mode — nicht „bestanden“.

**Betriebsfolge, in der Checkliste abgedeckt:** fehlen die Produktionsschlüssel, ist das
für die Rule derselbe Zustand wie ein Ausfall — die Formulare laufen ungeschützt weiter.
Deshalb stehen in `docs/turnstile-golive.md` §1.1 **alle drei** Schlüsselpaare vor dem
Deploy und §1.3 schaltet das Modul während Migration und Seeder global aus.

## 4. Wege zur Kontoanlage — ok

Jeder Weg, auf dem ein `users`-Datensatz entsteht, ist geprüft:

| Weg | Schutz | Stelle |
|---|---|---|
| `POST /register` | TurnstileRule über den Validator | `TurnstileRegisterValidator` |
| OTP-Registrierung (Livewire) | dito, Token in `$userFields` | `OneTimePasswordRegistration.php:56` |
| Checkout, vier Formulare | dito, Token in `$fields` | `CheckoutForm.php:148, 243` |
| Profil übernehmen (`ClaimModal::register()`) | TurnstileRule direkt, zuletzt | `ClaimModal.php:143` |
| Firmeneintragung mit Konto (`CompanySignup`) | TurnstileRule direkt | `CompanySignup.php:252` |
| OAuth-Callback | Kontoanlage **gesperrt** | `OAuthController.php:56` |
| Einladung, API, Filament | legen niemanden öffentlich an | — |

Tragend ist die Container-Bindung
`RegisterValidator → TurnstileRegisterValidator` (`TurnstileServiceProvider::register()`):
SaaSykit validiert jede Kontoanlage über diesen Validator, ein **neuer** Registrierungsweg
auf demselben Validator ist damit von selbst geschützt. Kein Aufrufer kann die Rule
vergessen, und der Feldname ist immer der Hidden-Input von Cloudflare (`Turnstile::FIELD`).

Der OAuth-Weg kann kein Token tragen (der Callback kommt vom Anbieter, nicht aus einem
Formular). Statt ihn ungeprüft zu öffnen, ist die Kontoanlage dort gesperrt: bestehende
Konten melden sich weiter an, eine unbekannte Mailadresse landet auf `/register` und damit
am geschützten Formular (`turnstile.oauth_registration`, Vorgabe `false`).

Firmeneintragung als Gast und als eingeloggter Nutzer: beide Wege
(`CompanySignup`, `CompanyRegistrationWizard`) hängen an `TurnstileAction::CompanyListing`;
neue Einträge sind bis zur Freischaltung nicht öffentlich (`companies.is_active = 0`).

## 5. Tiefenverteidigung und Rate-Limits — ok, S1 behoben (#26)

Ok:

* Vier Mittel, zwei Arten der Abweisung: Honeypot und Mindest-Ausfüllzeit weisen
  **stillschweigend** ab (ein Bot soll nicht lernen, woran er scheitert), Sperrliste und
  Rate-Limit weisen **sichtbar** mit Grund ab — sie können einen echten Menschen treffen.
* Der Honeypot-Feldname ist je Session zufällig aus plausiblen Namen und liegt in der
  Session, damit er zwischen Rendern und Abschicken stabil bleibt (`HoneypotField`).
* Der Zeitstempel der Ausfüllzeit ist `Crypt::encryptString`, also nicht fälschbar; ein
  Token aus der Zukunft gilt als manipuliert, ein abgelaufener Token (Tab stand über Nacht
  offen) **nicht** als Treffer (`TimingToken`).
* Rate-Limits zählen je Portal: der Schlüssel trägt immer den Portal-Präfix, die IP steht
  nie im Klartext darin, sondern als HMAC aus `TurnstileVerification::hashIp()` — derselbe
  Hash wie im Verifikations-Log, also in #9 wiederzufinden.
* Bewusst **keine** `throttle:`-Middleware: `ThrottleRequests` steht in Laravels
  `$middlewarePriority` vor der Tenancy-Middleware, im Limiter wäre das Portal noch nicht
  initialisiert und die Portal-Grenzen aus `tenant_turnstile_settings` würden nicht
  gelesen. Geprüft wird deshalb in der Aktion (`RateLimitGuard::enforce()` in
  `RegisterController`, `LoginController`) bzw. über `exceeded()`/`hit()` in Livewire, wo
  ein 429 den Besucher vor einem toten Formular stehen lassen würde.
* `0` und Unsinn in einer Portalgrenze heißen „nicht gesetzt“, nicht „alles gesperrt“
  (`TenantTurnstileSetting::positiveIntOrNull()`).
* Die Wegwerf-Sperrliste wird beim Einlesen validiert: eine kaputte Quelle kann die Datei
  nicht verunstalten, jede Zeile muss dem Domainmuster entsprechen
  (`AntispamRefreshDomains::domainsFrom()`), `*` steht für genau ein Label.

**S1 — behoben in #26.** `ClaimModal` (und die eingebettete Fassung `ClaimForm`,
Theme sun-v2) hing an einer eigenen, älteren Tiefenverteidigung: Honeypot mit fest
verdrahtetem Feldnamen `website_url`, eigener Zähler `'claim-register:'.$ip` mit der IP im
Klartext im Cache-Schlüssel, ohne Portal-Präfix und ohne Grenze je Mailadresse, keine
Mindest-Ausfüllzeit, Kontoanlage ohne `NotDisposableEmailRule`.

Jetzt nutzt die Komponente dieselbe Schicht wie alle übrigen Formulare:

* `InteractsWithAntiSpam` statt eigenem Honeypot; `<x-antispam-fields wire />` in
  `resources/views/livewire/portal/claim-modal.blade.php` und
  `resources/views/themes/sun-v2/views/livewire/portal/claim-form.blade.php`. Feldname je
  Session zufällig, Mindest-Ausfüllzeit über `TimingToken`.
* `RateLimitGuard` statt eigener `RateLimiter`-Blöcke — HMAC der IP, Portal-Präfix,
  Livewire-Variante (Restzeit am Feld statt 429). Die drei Eintrittspunkte haben eigene
  Limiter, damit sie sich nicht gegenseitig sperren: `claim_registration` (Reiter
  Registrieren, IP und Mailadresse), `claim_login` (Reiter Anmelden, IP + Mailadresse) und
  `claim` (Übernahme-Antrag des angemeldeten Wegs, IP und Nutzerkennung); der Einspruch
  zählt auf `claim_dispute`. Grenzen in `config/antispam.php`.
* `NotDisposableEmailRule(TurnstileAction::Registration)` an der `email`-Regel der
  Kontoanlage.

Die Unterscheidung aus `docs/turnstile.md` §13 bleibt: Honeypot und Ausfüllzeit weisen
still ab (`claimSuccess = true`, kein Datensatz), Sperrliste und Rate-Limit sichtbar mit
Grund.

## 6. Content-Security-Policy — ok mit getragenem Restrisiko (R4)

* Eine Policy für alle Portale (`config/csp.php`), gesetzt in
  `App\Http\Middleware\ContentSecurityPolicy`, angehängt an die `web`-Gruppe
  (`bootstrap/app.php`).
* Die Cloudflare-Quellen sind **nicht** als Literal gepflegt, sondern aus
  `turnstile.script_url` und `turnstile.siteverify_url` abgeleitet und nur an `script-src`,
  `frame-src` und `connect-src` gehängt. Eine Adressänderung bei Cloudflare wirkt an einer
  Stelle. Steht das Modul auf `enabled => false`, fehlen die Quellen ganz.
* `'unsafe-inline'` steht **nur** in `style-src`, nicht in `script-src`. Das Widget und
  `resources/js/turnstile.js` hängen ausschließlich an `data`-Attributen, es gibt keinen
  Inline-Handler in der Komponente.
* `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` sind gesetzt.
* Angefasst wird nur HTML, und nur wenn die Antwort keinen eigenen CSP-Header trägt — das
  Bewertungs-Widget setzt sich sein `frame-ancestors` selbst und wird nicht überschrieben.

**R4 — geschlossen (#21, nachgemessen in #28/#29).** Die Themes liefern keine
Inline-Skripte mehr aus; die Logik hängt an `data`-Attributen in `resources/js/` und
`resources/views/themes/sun-v2/js/`, fremdes HTML aus der Verwaltung (`ad_slots`,
`config('app.tracking_scripts')`) bekommt das Nonce aus
`App\Services\Security\CspNonce`. Die Vorgabe in `config/csp.php:41` ist seit #21
`CSP_MODE=enforce`. Im Browser nachgemessen: `docs/messungen/csp-browser-messung-2026-10-08.md`
mit Rohdaten `csp-browser-report-…`, `csp-browser-enforce-…`, `csp-browser-lazy-nonce-…`
(Chrome 154) und `csp-webkit-nonce-2026-10-08.json` (Safari/WebKit, #29). Jede Seite hat
eine Gegenprobe (Zahl der Turnstile-Skripte, `adsbygoogle.loaded`, `dataLayer`-Länge,
`typeof Livewire`/`Alpine`), ein Lauf ohne Verstöße ist also kein Lauf ohne Inhalt.
`'unsafe-eval'` bleibt gesetzt, weil Livewire-Alpine jeden `x-`/`wire:`-Ausdruck mit
`new Function()` auswertet — dokumentiert in `docs/turnstile.md` §14, kein Befund.
Der Go-Live setzt `CSP_MODE=enforce` (`docs/turnstile-golive.md` §1.5).

## 7. Verifikations-Log und Datenschutz — ok

* Eine Zeile je Prüfversuch, auch für die übersprungene (`TurnstileLogger::record()`).
* **Kein Token, keine Klartext-IP, keine Klartext-Mailadresse** — nur HMAC-SHA256
  (`hashIp()`, `hashEmail()`); der User-Agent wird gekürzt. Die Mailadresse kommt über
  `DataAwareRule` aus den mitvalidierten Feldern und dient nur der Korrelation in #8.
* Die Tabelle liegt in der Portal-Datenbank. Jeder Schreibfehler wird geschluckt und
  geloggt: ein Log-Problem darf keine Registrierung kippen.
* Aufbewahrung 90 Tage, `turnstile:prune` räumt nächtlich in Runden von 1000 Zeilen ab
  (ein großes `DELETE` würde die Tabelle zu lange sperren).
* Im Admin wird die Portal-Tabelle über `TurnstileLogConnection` gelesen (Verbindung
  `tenant` umbiegen), nicht über `tenancy()->initialize()` — das Panel bleibt zentral.
* Datenschutzerklärung (#11): `TenantLegalDefaults` §3.1 benennt Cloudflare Germany GmbH
  und Cloudflare, Inc., die übermittelten Daten einschließlich IP-Adresse und
  Interaktionsdaten, das kurzlebige Prüf-Token, Zweck, Rechtsgrundlage (Art. 6 Abs. 1
  lit. f DSGVO) und den Hinweis, dass Formularinhalte nicht übermittelt werden. Am Formular
  steht der Transparenzhinweis mit Link auf diese Seite (`turnstile.notice`), auch im Modus
  `invisible`.
* Zugang zu Einstellungen, Log und Widget: Administrator **und** Recht `update settings`
  (`BotProtectionAccess`); Redaktionsrollen kommen nicht heran.

## 8. Quarantäne-Logik — ok

* Eine Stelle für Markieren, Freigeben und Ablaufen (`BotQuarantine`), benutzt von
  `antispam:scan`, der Sichtung in Filament und `antispam:expire-quarantine`. Damit dreht
  „Freigeben“ genau das zurück, was „Markieren“ angerichtet hat.
* Der Zustand vor der Markierung steht in `suspected_bot_reasons_json.previous`. Ein
  Eintrag, der vorher nie öffentlich war, wird durch eine Freigabe **nicht** veröffentlicht.
* Markieren heißt: Konto `is_blocked = 1`, Eintrag `is_active = 0` — also sofort aus Suche,
  Stadtseiten und Sitemap heraus, denn die Sichtbarkeit hängt allein an `is_active`.
* Geschrieben wird mit `saveQuietly()`, damit der `CompanyActivationObserver` keine
  Freischaltmail an den Inhaber schickt; das eine gewollte Nebenwirkung — das JSON-LD des
  Profils verwerfen — löst die Klasse selbst aus.
* **Hart gelöscht wird nie**: `softDelete()` setzt `deleted_at`. Eine Freigabe ist
  endgültig, ein freigegebener Datensatz wird nie wieder markiert. Die 14-Tage-Frist
  startet ein zweiter Lauf nicht neu (`suspected_bot_at ?? now()`).
* `antispam:scan` steht **nicht** im Scheduler: markiert wird nur nach Sichtung, von Hand.
  Im Scheduler läuft nur `antispam:expire-quarantine` (03:20), und der Produktivlauf auf
  der Produktion ist ein eigenes Ticket mit Sicherung, Trockenlauf und Freigabe (#23).
* `antispam:backup` sichert vorher die zentrale `users` und die `companies` jedes Portals
  als NDJSON.

## 9. Monitoring und Alarme — ok mit getragenem Restrisiko (R2)

* Eine Aggregation für Widget, Alarme und Tagesbericht (`TurnstileStats::portal()`) — das
  Panel und die Mail können nicht auseinanderlaufen.
* Vier Alarme mit Mindestmengen gegen Rauschen: Fehlerquote (ab 10 Prüfungen),
  Fehlerzahl, Blockierungsquote zu niedrig (Formular wird umgangen) und zu hoch (Widget
  kaputt), beide erst ab 50 Registrierungsprüfungen in der Stunde.
* Höchstens eine Mail je Portal, Alarmtyp und Stunde; fällt der Cache aus, meldet eine
  Stunde doppelt — der harmlosere Fehler als eine verschluckte Meldung.
* `withoutOverlapping()` und `onOneServer()` auf allen drei Scheduler-Einträgen.
* Empfänger: Komma-Liste aus der `.env`, sonst alle nicht gesperrten Administratoren mit
  dem Recht `update settings` — dieselbe Hürde wie der Panel-Zugang. Adressen stehen nicht
  im Repo; gesetzt wird der Verteiler in `docs/turnstile-golive.md` §1.5.

**R2 — geschlossen (#19).** `App\Listeners\Turnstile\AlertOnSiteverifyUnreachable`
hängt an `SiteverifyUnreachable`; die Event-Discovery findet ihn ohne Eintrag in einem
Provider (`php artisan event:list` zeigt die Bindung). Gemeldet wird nicht je Ereignis —
ein einzelner Zeitüberschreitungsfall ist Rauschen — sondern ab
`turnstile.alerts.siteverify_unreachable.threshold` Ausfällen in `window_seconds`
(Vorgabe 20 in 5 Minuten) und danach höchstens einmal je `cooldown_seconds`
(Vorgabe 30 Minuten). Der Listener läuft bewusst synchron und ohne `ShouldQueue`
(ein Cache-Zähler; die Mail geht in die Queue), weil er in eine Anfrage fällt, die
ohnehin schon auf eine Zeitüberschreitung gewartet hat. Der Verzug sinkt damit von bis
zu 65 Minuten auf wenige Minuten; der stündliche `turnstile:monitor` bleibt die Aufsicht
über die Quote.

## 10. Was dieses Review ausdrücklich nicht abdeckt

* **R3 (jetzt niedrig, #22 erledigt):** Anfrage-Dialog, Bewertung, Bewerbung,
  Newsletter und Korrekturvorschlag hängen seit #22 an derselben Schicht — `TurnstileRule`,
  Widget und Tiefenverteidigung stehen im Code (`SubmitReviewForm`,
  `NewsletterSubscribeForm`, `SuggestEditForm`, `ExclusiveLeadController`,
  `PublicJobController`, jeweils mit `InteractsWithAntiSpam`/`SpamGuard` und eigenem
  `RateLimitGuard`-Zähler). Offen ist allein der **Schalter**: `lead_request` und
  `contact` stehen in `config/turnstile.php:245,250` weiter auf `enabled => false`, der
  Modus auf `non_interactive`. Begründung unverändert: an diesen Formularen hängt der
  Umsatz, sie gehen Portal für Portal über `tenant_turnstile_settings.actions_json` an.
  Das ist Welle 4 und ausdrücklich **nicht** Teil von #13. Bis dahin tragen die vier
  Mittel der Tiefenverteidigung diese Formulare — nicht null Schutz, aber kein Turnstile.
* **R1:** Scraper-Verkehr auf GET (WAF, robots, Tracking) — #17 erledigt (robots,
  Tracking, `antispam:bot-traffic`), die Cloudflare-seitigen WAF-Regeln sind #24
  (Mensch, Skript `scripts/cloudflare-waf-regeln-setzen.sh` liegt bereit).
* Cloudflare-seitige Einstellungen jenseits der Widgets (WAF-Regeln, Bot Fight Mode) — #24.
* Die Bestandsbereinigung auf der Produktion selbst — #23.

---

## 11. Befunde und Stand

| # | Befund | Schwere | Stand |
|---|---|---|---|
| S1 | `ClaimModal` nutzt eine eigene, ältere Tiefenverteidigung statt der Schicht aus #8 (fester Honeypot-Name, IP im Klartext im Zähler, kein Portal-Präfix, keine Mindest-Ausfüllzeit, keine Sperrliste) | mittel | **behoben**, #26 (§5); geht mit dem Go-Live-Commit auf die Produktion (§0 B2 in `docs/turnstile-golive.md`) |
| R2 | SUN-TS-011 ohne Listener, Verzug bis 65 min | niedrig | **behoben**, #19 (§9) |
| R4 | CSP nur im Modus `report` durchsetzbar | mittel | **behoben**, #21, im Browser nachgemessen in #28/#29 (§6); Vorgabe ist `enforce` |
| R3 | Anfrage, Bewertung, Bewerbung, Newsletter, Korrekturvorschlag ohne Turnstile | niedrig (war mittel) | **angeschlossen**, #22; die Aktionen `lead_request`/`contact` bleiben bewusst aus — Welle 4, nicht #13 (§10) |
| R1 | Scraper-Verkehr auf GET nicht begrenzt | mittel | ausserhalb des Modulumfangs: #17 erledigt, Cloudflare-WAF ist #24 (Mensch, in Arbeit) |
| — | `Host`-Header ohne Allowlist im Hostname-Vergleich | niedrig | akzeptiert, Begründung in §2 |

Geprüft und **ohne** Befund: Secret-Handling (§1), Hostname- und Action-Check (§2),
Fail-Mode (§3), alle Wege zur Kontoanlage und zur Firmeneintragung (§4),
Tiefenverteidigung und Rate-Limits (§5), CSP (§6), Verifikations-Log und Datenschutz (§7),
Quarantäne-Logik (§8), Monitoring und Alarme (§9).

**Ergebnis: keine offenen Punkte im Umfang dieses Reviews.** Was bleibt, liegt
ausserhalb: die Cloudflare-WAF (#24) und Welle 4 (`lead_request`/`contact`), beides mit
eigenem Ticket und eigener Abnahme.

---

## 12. Nachprüfung am 08.10.2026

Nicht aus der Doku übernommen, sondern am Code und an der Produktion nachgesehen:

| Punkt | Prüfung | Ergebnis |
|---|---|---|
| R2 | `php artisan event:list \| grep -A1 Siteverify` | `SiteverifyUnreachable ⇂ AlertOnSiteverifyUnreachable@handle` — Bindung steht über Event-Discovery |
| R4 | `grep -n "'mode'" config/csp.php` | `env('CSP_MODE', 'enforce')` (Zeile 41); Messprotokolle liegen unter `docs/messungen/csp-browser-*` |
| R3 | `grep -nE "TurnstileRule\|InteractsWithAntiSpam\|RateLimitGuard" app/Livewire/Portal/{SubmitReviewForm,NewsletterSubscribeForm,SuggestEditForm}.php app/Http/Controllers/Portal/{ExclusiveLeadController,PublicJobController}.php` | alle fünf Formulare tragen Rule **und** Tiefenverteidigung; `config/turnstile.php:245,250` weiter `enabled => false` |
| §1 | `php artisan turnstile:keys:check` lokal | Gruppe A `Testschluessel`, B und C `fehlt`, 18 Hostnames = Soll, Zuordnung der drei Subdomain-Proben korrekt, Exit 0 (lokal ist das eine Warnung, in Produktion Exit 1) |
| §1 / Betrieb | Produktions-`.env`: `grep -c '^TURNSTILE' .env` | **0** — kein einziger Schlüssel gesetzt, auch kein `CSP_MODE`, kein `TURNSTILE_ALERT_RECIPIENTS`. Produktion steht auf `f5fa66ed`, kennt das Modul also nicht. Das ist B1/B2 im Go-Live-Dokument und keine Lücke im Code, aber der Grund, warum Stufe 1 am 08.10.2026 nicht startet |
| §4 | `grep -rn "TurnstileRegisterValidator" app/Turnstile/TurnstileServiceProvider.php` | Container-Bindung steht, kein zweiter Registrierungsweg ohne Validator |

Die Nachprüfung ändert kein Urteil aus §1–§9; sie schliesst R2, R3 und R4 und hält
fest, dass der Code sicher ist, die **Produktion aber noch ungeschützt** — ohne
Schlüssel verhält sich die Rule wie bei einem Ausfall (Fail-Mode `open`), und ohne
Deploy existiert sie dort gar nicht.
