# Security-Review der Anfragen-Inbox (SUN-RQ #12)

Review zu Ticket #12 des Vorhabens „Zentrale Anfragen-Inbox fuer alle
SUN-Portale". Geprueft wurde der Code der Tickets #3 bis #11, so wie er im
Branch `feature/werbeplaetze-sun-v2` liegt.

Stand: 21.10.2026 · Autor: Dimitri (Backend) · Freigabe: Enes

**Ergebnis: keine offenen Punkte.** Zwei Befunde, beide in diesem Ticket
behoben und nachgemessen (Abschnitt 2). Alles weitere ist geprueft und ohne
Befund (Abschnitt 3) oder eine getroffene Entscheidung (Abschnitt 4).

---

## 1. Umfang und Methode

Gegenstand waren die sieben Punkte aus dem Ticket: Verifikation (Magic-Link,
Domain-Match-Ausnahmen), KI ohne Schreibrechte, Tenant-Kontext-Wechsel in
allen Actions, signierte Status-URLs, Rechte der Loeschpipeline, IMAP-Zugang —
und als achter Punkt der Zugang zum Central-Panel.

Methode: Lesen des Codes gegen das Konzept in `docs/profile-requests.md`,
dazu gezielte Proben auf dem lokalen Spiegel (`php artisan tinker`,
Leseabfragen) und der Secret-Scan ueber alle versionierten Dateien.

Zwei Grenzen, die das Ergebnis mitbestimmen:

* **Kein fremder Dienst wurde angesprochen.** Nach `CLAUDE.md` („Externe
  Dienste und Konten") gibt es ohne Zustimmung von Enes keinen Aufruf gegen
  Postfach, Mailhoster oder Claude-Konto — auch nicht lesend. Der IMAP-Teil
  ist deshalb gegen Code und Konfiguration geprueft, nicht gegen eine
  Verbindung.
* **Absenderadressen sind im lokalen Spiegel anonymisiert**
  (`config/db-pull.php` ersetzt `companies.email` und `reporter_email` durch
  `…@example.test`). Quoten zum Domain-Match lassen sich lokal nicht messen,
  nur die Regel selbst pruefen. Die Zahlen kommen aus dem Pilot
  (`docs/profile-requests-golive.md`).

---

## 2. Befunde

### Befund 1 — Domain-Match griff auch zur Elterndomain (behoben)

**Wo:** `app/ProfileRequests/Verification/SenderDomainMatcher.php`,
`sameDomain()`.

**Was:** Der Vergleich lief in beide Richtungen
(`str_ends_with($sender, '.'.$company) || str_ends_with($company, '.'.$sender)`).
Damit galt auch ein Absender als autorisiert, dessen Domain **oberhalb** der
Domain des Profils liegt. Der Klassenkommentar beschreibt ausdruecklich nur
die eine Richtung — der Code war strenger dokumentiert als er war.

**Warum das zaehlt:** Profile mit einer Firmen-E-Mail auf einer
Anbieter-Subdomain sind der Normalfall bei Homepage-Baukaesten
(`kontakt@kunde123.anbieter.de`). Jede Adresse der Anbieterdomain hat damit
eine fremde Profilanfrage autorisieren koennen — ohne Magic-Link, ohne
Handpruefung. Die Freemail-Liste faengt das nicht, weil eine Baukastendomain
dort nicht steht, und verifizierte Anfragen laufen in die Auto-Regeln (#9).

**Behoben:** Nur noch Adressgleichheit oder Absender **unterhalb** der
Profildomain. Nachgemessen am ungespeicherten Company-Objekt
(`email = kontakt@kunde123.anbieter.de`, `website = kunde123.anbieter.de`):

| Absender | erwartet | gemessen |
|---|---|---|
| `fremd@anbieter.de` | kein Treffer | `false` (`kein_treffer`) |
| `kontakt@kunde123.anbieter.de` | Treffer | `true` (`adresse_identisch`) |
| `info@mail.kunde123.anbieter.de` | Treffer | `true` (`domain_der_firmen_mail`) |
| `chef@gmail.com` | kein Treffer | `false` (`freemail_domain`) |

### Befund 2 — Anhaenge aus dem Formular landeten in der Portal-Ablage (behoben)

**Wo:** `app/ProfileRequests/Models/RequestAttachment.php` (`DISK`),
wirksam in `app/ProfileRequests/Actions/CreateRequest.php::storeFiles()`.

**Was:** Rohmails und Anhaenge lagen auf der Platte `local`. `local` steht in
`config/tenancy.php` unter `filesystem.disks` und bekommt waehrend eines
Portal-Requests den Wurzelpfad `storage/tenant<id>/app/`
(`filesystem.root_override`). Der Poller (#5) laeuft zentral und schreibt
nach `storage/app/`; das **Self-Service-Formular (#4) laeuft auf der
Portal-Domain** und schrieb damit in die Ablage des Portals. Der Pfad in
`request_attachments.path` ist relativ und in der Central-DB gleich — gesucht
wird aber zentral.

**Warum das zaehlt:** Drei Folgen, die erste faellt auf, die dritte nicht:

1. Das Panel (#7/#11) zeigt den Anhang nicht an, die Vorschau laeuft ins
   `null` — die Anfrage ist nicht entscheidbar.
2. `ApplyChanges` findet die Bilddatei nicht und uebernimmt Logo oder
   Galerie stillschweigend nicht.
3. `requests:purge-raw-mail` loescht die Datei **nie**. Die
   Aufbewahrungsfrist von 90 Tagen laeuft fuer eine Datei, die der
   Loeschlauf nicht sieht, ins Leere — eine Bilddatei mit Personenbezug
   bleibt unbefristet liegen.

**Behoben:** Eigene Platte `requests` in `config/filesystems.php`, nicht in
`tenancy.filesystem.disks`, mit demselben Wurzelpfad wie das zentrale
`local` — bestehende Pfade loesen unveraendert auf, kein Umzug noetig.
`RequestAttachment::DISK` zeigt darauf. Nachgemessen:

```
zentral  : requests=storage/app/            local=storage/app/
im Tenant: requests=storage/app/            local=storage/tenant<key>/app/
```

---

## 3. Geprueft, ohne Befund

### 3.1 Magic-Link

* Der Link geht **ausschliesslich** an `companies.email` des betroffenen
  Profils, nie an die Adresse des Anfragenden
  (`RequestVerifier::companyEmail()`). Fehlt die Adresse oder ist sie
  unbrauchbar, geht der Vorgang auf `awaiting_verification` mit Kennzeichen
  `keine_firmen_email` in die Handpruefung.
* Token: 64 Zeichen aus `Str::random`, in der Tabelle liegt nur der SHA-256
  (`ProfileRequest::hashToken`), verglichen wird mit `hash_equals`. Der
  Klartext existiert in der Mail und im Protokoll **nicht** — die
  Ereigniszeile `verification_requested` traegt nur Zieladresse und Frist.
* Zwei Sperren pro Aufruf: Signatur der URL **und** Token-Hash
  (`RequestVerificationController::resolve()`). Ein neu angeforderter Link
  entwertet den alten, weil der Hash ersetzt wird; die Verifikation selbst
  raeumt den Hash ab (einmalige Verwendung).
* Frist 72 Stunden (`verification_ttl_hours`), doppelt geprueft: Ablauf der
  Signatur und `verification_expires_at`. Ein abgelaufener Link fuehrt nicht
  zum Fehler, sondern auf eine Seite mit Neuanforderung — und den Vorgang
  auf `expired` setzt allein `requests:expire`, nicht der Klick.
* Mengenbegrenzung: hoechstens `max_links_per_company_per_day` (3)
  Bestaetigungsmails je **Profil** und Tag, gezaehlt ueber alle Vorgaenge des
  Profils. Drei Formularabsendungen erzeugen damit keine drei Mails. Die
  Routen sind zusaetzlich durchgesetzt (`throttle:20,1`, Neuanforderung
  `throttle:5,10`).
* Der Link wird auf der **Portal-Domain** signiert
  (`URL::forceRootUrl` vor `temporarySignedRoute`, danach zurueck). Richtig
  so: Laravel prueft die Signatur gegen die aufgerufene Adresse, und eine
  fremde Domain in der Bestaetigungsmail eines Portals sieht aus wie
  Phishing.

### 3.2 Domain-Match und seine Ausnahmen

* Der Match gilt **nur** fuer Quelle `email`. Beim Formular ist die Adresse
  Eingabe und beweist nichts — genau so ist es umgesetzt
  (`RequestVerifier::domainMatches()` prueft `source`).
* Freemail- und Sammeldomains sind ausgenommen, inklusive ihrer Subdomains
  (`mail.gmx.de`), und zwar **auch bei exakter Adressgleichheit** — wer eine
  Mail faelscht, faelscht zuerst das From-Feld.
* Die Liste kommt aus der Config plus Pflegedatei
  (`resources/profile-requests/generic-email-domains.txt`), damit sie auf der
  Produktion ohne Deploy nachziehbar ist.
* Verglichen wird auf Label-Grenzen, nie mit `str_contains` — siehe Befund 1
  fuer die verbleibende Richtung.
* Jede Entscheidung steht mit Grund im Protokoll (`treffer`, `grund`,
  `absender_domain`), auch die negative. Ohne diese Zeile waere im Pilot
  nicht nachvollziehbar, warum eine Anfrage in die Handpruefung lief.

### 3.3 KI ohne Schreibrechte

Drei Schichten, jede allein ausreichend:

1. **Der Aufruf liefert nur Vorschlaege.** `ClassifyRequest::store()`
   schreibt `ai_*` am Vorgang und den Diff als `request_changes` mit
   `accepted = null`. Kein Profilfeld, keine Mail, keine Erledigung.
2. **Die Zuordnung ist auf eine Auswahl begrenzt.** `company_id` wird nur
   uebernommen, wenn die ID aus der serverseitig gebildeten Kandidatenliste
   stammt (`CompanyCandidateFinder`, hoechstens 5) **und** die Zuversicht
   ueber `classification.min_confidence` (0,7) liegt. Eine erfundene ID
   verwirft der Classifier.
3. **Das Schreiben hat eine eigene Liste.** `ApplyChanges::writeField()`
   kennt nur `DIRECT_COLUMNS` plus Sonderwege fuer Stadt, Oeffnungszeiten,
   Leistungen. `categories` und `user_id` antworten ausdruecklich mit einer
   Begruendung statt zu schreiben; alles Unbekannte faellt auf
   „Unbekanntes Feld". Ein Diff-Feld `is_premium` oder `verified` kann nicht
   wirken, auch wenn es jemand in `request_changes` schreibt.

Dazu die Auto-Regeln (#9): automatisch laeuft eine Mail-Aenderung nur mit
Zuversicht ≥ `auto.min_ai_confidence` (0,9), nur verifiziert, nur wenn
**jedes** Feld des Diffs auf `auto_apply_fields` steht, und nie bei einem
Betrieb aus `auto.manual_review` oder mit laufendem Premium-Vertrag
(`ContractGuard`). Der Notschalter `REQUESTS_AUTO_DISABLED` haelt alles an,
ohne den Panel-Weg zu schliessen.

### 3.4 Tenant-Kontext in allen Actions

Geprueft wurde jeder Zugriff auf ein Tenant-Model im Modul (`Company`,
`City`, `CompanyOpeningHour`). Alle liegen in einem Closure von
`TenantCompanyResolver::runFor()` bzw. `ProfileRequest::runInTenantContext()`
— also unter `Tenant::run()`, das den vorher aktiven Tenant hinterher
wiederherstellt. Betroffen und geprueft: `ApplyChanges`, `AssignCompany`,
`ApproveClaim`, `SendReply`, `RevertChanges`, `CompanyCandidateFinder`,
`ContractGuard`, `ScheduleVerifiedDeletion`, `DeleteCompanyAction`.

Kein Zugriff laeuft im Central-Kontext auf eine Tenant-Tabelle; kein Aufruf
benutzt `tenancy()->initialize()` ohne Rueckweg. Die Bilddateien einer
Uebernahme werden **vor** dem Kontextwechsel in temporaere Dateien geholt,
weil die Platte im Portal umgebogen ist — derselbe Mechanismus, der Befund 2
verursacht hat, hier richtig behandelt.

`RequestLinks::panel()` baut die Panel-Adresse aus `config('app.url')`, die
der `TenantUrlBootstrapper` je Portal umsetzt. Aufgerufen wird sie nur im
Tagesdigest (`RequestsDailyDigest`), und der laeuft zentral — kein Befund,
aber die Stelle vertraegt keinen Aufruf aus einem Portal-Request.

### 3.5 Signierte Status-URL

* Route `portal.requests.status` mit `signed` und `throttle:30,1`,
  `whereNumber`. Ohne gueltige Signatur kein Zugriff; eine ID lässt sich
  nicht durchprobieren.
* **Bewusst ohne Ablauf.** Ein Vorgang laeuft Wochen. Dafuer steht auf der
  Seite nur, was der Betrieb selbst eingereicht hat: der Zeitstrahl ist auf
  `VISIBLE_EVENTS` begrenzt, KI-Vorschlaege, Notizen, Mailprotokoll,
  Zuversichtswerte und Bearbeiternamen fehlen, die Adresse des Anfragenden
  steht maskiert da (`MaskedEmail`).
* Die Knoepfe der Seite aendern den Zustand nur ueber die Automaten-Methoden
  des Models, also mit Protokollzeile. `requestId` ist eine
  Livewire-Eigenschaft mit Pruefsumme — ein fremder Vorgang laesst sich
  ueber `POST /livewire/update` nicht unterschieben.
* Die Seite wird auf jeder Portal-Domain ohne Tenant-Kontext ausgeliefert und
  haengt an keinem Portal-Asset; `noindex` ist gesetzt.

### 3.6 Rechte der Loeschpipeline

* Ein Profil wird ausschliesslich ueber `DeleteCompanyAction` geloescht —
  Panel, Auto-Regel und `company:delete` benutzen dieselbe Action, die
  Schritte stehen in `config('profile-requests.deletion.steps')`, jeder
  Schritt protokolliert in `deletion_log` der Tenant-DB.
* Die automatische Loeschung setzt **drei** Dinge voraus: verifiziert,
  Karenzfrist von 48 Stunden abgelaufen (`grace_until`, Stornolink in der
  Ankuendigung) und `ContractGuard` ohne Einwand. `REQUESTS_AUTO_DISABLED`
  haelt auch bestehende Karenzfristen an, statt sie ablaufen zu lassen.
* `companies:hard-delete-due` greift nur `onlyTrashed()` mit gesetztem und
  abgelaufenem `hard_delete_after`, laeuft nur im Tenant-Kontext (sonst
  Abbruch mit Hinweis) und ist auf 500 Profile je Lauf begrenzt. Im
  Scheduler taeglich 02:30, nach den Anonymisierungslaeufen.
* `company:delete` ohne `--dry-run` loescht sofort und fragt im
  nicht-interaktiven Lauf nicht nach. Das ist ein Werkzeug fuer die Konsole
  der Produktion; der Zugang dorthin ist die Rechtegrenze, nicht der Befehl.
* Geloeschte Profil-URLs liefern dauerhaft 410 (`gone_urls` +
  `App\Http\Middleware\GoneUrls` an beiden Profilrouten), `gone_urls` und
  `deletion_log` bleiben auch nach dem harten Loeschen — ohne sie verlieren
  wir den 410 und den Nachweis.

### 3.7 IMAP-Zugang

* Zugangsdaten stehen ausschliesslich in der `.env` (`REQUESTS_IMAP_*`), im
  Code gelesen ohne Vorgabewert, alle Variablen leer in `.env.example`.
  `php scripts/secret-scan.php` am 21.10.2026: sauber (2934 Dateien).
* Verbindung ueber Port 993 mit `encryption = ssl` und
  `validate_cert = true` als Vorgabe; `timeout = 30`.
* Der Poller **loescht im Postfach nie**. Eine eingelesene Mail wird als
  gelesen markiert und nach `Verarbeitet` verschoben — und zwar erst nach
  der Verarbeitung. Das Postfach bleibt die unabhaengige zweite Spur.
* Dedupe laeuft ueber einen Unique-Index auf dem Message-ID-Hash in
  `request_mail_log`; die Zeile entsteht **vor** Anfrage und Dateien, damit
  eine zweite Zustellung auch nach einem Abbruch draussen bleibt.
* Fremdeingaben aus der Mail: Anhaenge nur mit erlaubtem MIME-Typ (Bilder,
  PDF) und unter `max_attachment_mb`, Dateiname wird neu gewuerfelt
  (Originalname landet nie auf der Platte), Auto-Antworten, Newsletter,
  Bounces und `noreply`-Absender werden verworfen und protokolliert.
* Anfragen aus dem Mailweg sind **nicht** automatisch autorisiert: ohne
  Domain-Match geht ein Magic-Link an die Firmenadresse. Eine gefaelschte
  Absenderadresse bringt also nichts.

### 3.8 Zugang zum Central-Panel

Die Resource `App\Filament\Central\Resources\ProfileRequestResource` ist im
Admin-Panel registriert. Der Zugang dorthin haengt an
`User::canAccessPanel()`: `is_admin`. Ohne dieses Flag ist das Panel
unerreichbar, also auch die Inbox. Die Ein-Klick-Aktionen bringen keinen
eigenen Weg mit — sie laufen ueber Filament, also mit Sitzung und
CSRF-Schutz, und sind zusaetzlich an den Zustand des Vorgangs gebunden
(`->visible(... isOpen())`).

---

## 4. Entscheidungen

Zwei Punkte, bei denen das Review eine Entscheidung verlangt statt einer
Korrektur. Beide sind getroffen, keiner bleibt offen.

**(a) Keine Feinsteuerung der Panel-Rechte.** Die Inbox unterscheidet nicht
zwischen „sehen" und „erledigen"; wer Administrator ist, darf beides. Das
entspricht dem Betrieb — Enes arbeitet die Inbox allein ab — und eine
Rollentrennung ohne zweite Person waere Konfiguration ohne Wirkung. Kommt
eine zweite Person hinzu, ist das eine Aenderung an diesem Punkt und kein
Nachtrag hier.

**(b) Aufbewahrung geschlossener Vorgaenge.** Rohmails und Anhaenge fallen
nach 90 Tagen (`retention_days`, `requests:purge-raw-mail`). Der Vorgang
selbst — inklusive `requester_email` — bleibt unbefristet: er ist der
Nachweis, dass eine Loeschung oder Aenderung autorisiert war, und ohne ihn
steht die Loeschpipeline ohne Beleg nach Art. 17 da. Eine zusaetzliche
Verfallsfrist fuer geschlossene Vorgaenge ist eine Frage der
Aufbewahrungspflicht, nicht der Sicherheit; sie ist als eigenes Ticket
notiert und nicht Teil dieses Reviews.

> **Nachtrag (#15, 21.10.2026).** Die Frist ist gesetzt: 24 Monate ab
> Abschluss, danach nimmt `requests:anonymize-closed` (taeglich 04:00) dem
> Vorgang den Personenbezug — Name, Adresse, Telefon, Freitext, bestaetigte
> Adresse, die KI-Texte und die Diff-**Werte**. Typ, Portal, Zeitpunkte,
> Diff-**Feldnamen** und das Protokoll bleiben, der Nachweis nach Art. 17
> also auch. Unbefristet ist damit nichts mehr. Einzelheiten:
> `docs/profile-requests.md`, Abschnitt 11f.

---

## 5. Restrisiken, die kein Befund sind

Drei Dinge bleiben, weil sie dem Verfahren selbst innewohnen. Sie stehen
hier, damit sie nicht spaeter als Ueberraschung auftauchen.

1. **Wer das Firmenpostfach kontrolliert, kontrolliert das Profil.** Darin
   liegt der ganze Beweis des Magic-Links. Ein uebernommenes oder veraltetes
   Firmenpostfach ist deshalb ein Vollzugriff auf den Eintrag. Die
   Karenzfrist von 48 Stunden mit Stornolink ist die einzige Bremse — und
   sie ist die richtige, weil die Alternative (Nachweisdokumente fuer jede
   Telefonnummer) das Verfahren unbenutzbar macht.
2. **Eine falsche KI-Zuordnung trifft den falschen Betrieb.** Deshalb
   uebernimmt die Automatik eine Zuordnung erst ab 0,7 Zuversicht, eine
   automatische Erledigung erst ab 0,9, und die Loeschung nie ohne
   Karenzfrist. Die Fehlerquote wird im Pilot gemessen (Ziel unter 5 %) und
   ist das Abnahmekriterium fuer das Anschalten der Auto-Regeln.
3. **Weitergeleitete Mails brechen SPF.** Am Sammelpostfach darf deshalb
   nicht hart auf SPF-Fail gefiltert werden, sonst gehen Anfragen verloren.
   Die Folge ist, dass eine gefaelschte Absenderadresse im Postfach
   ankommt — was sie nicht autorisiert (3.7), aber sie erzeugt einen Vorgang
   und damit womoeglich einen Magic-Link an den echten Betrieb. Das
   Tageslimit von drei Mails je Profil ist die Grenze dieses Missbrauchs.

---

## 6. Beruehrte Dateien

| Datei | Aenderung |
|---|---|
| `app/ProfileRequests/Verification/SenderDomainMatcher.php` | Befund 1: Domain-Match nur noch in eine Richtung |
| `config/filesystems.php` | Befund 2: Platte `requests`, nicht mandantengetrennt |
| `app/ProfileRequests/Models/RequestAttachment.php` | Befund 2: `DISK` zeigt auf `requests` |
