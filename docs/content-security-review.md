# Security-Review der Ratgeber-Pipeline

Status: **abgeschlossen, keine offenen Punkte**
Ticket: #26
Prüfdatum: 2026-09-09
Umfang: `app/Content/**`, das Content-Panel, die Portal-Routen der Pipeline
(`/ratgeber/vorschau/*`, `/<key>.txt`, `/bot`) und die Konsolenbefehle
`content:*`.

Geprüft wurden vier Felder: Panel-Guard, signierte Vorschau-Adressen,
Zugangsschlüssel und Crawler-Etikette. Jeder Abschnitt nennt den Befund, die
Stelle im Code und — wo etwas zu tun war — die Änderung.

---

## 1. Panel-Guard und Rollen

**Befund: in Ordnung.**

Das Content-Panel ist ein eigenes Filament-Panel mit eigenem Guard, eigener
Benutzertabelle und eigenem Passwort-Broker
(`App\Providers\Filament\ContentPanelProvider`, `config/content.php` → `panel`).
Es teilt sich mit dem Portal- und Admin-Bereich keine Sitzung: der Guard heißt
`content`, der Provider `content_users`.

| Prüfpunkt | Stelle | Befund |
| --- | --- | --- |
| Zugang nur für aktive Accounts | `ContentUser::canAccessPanel()` | `is_active` **und** Panel-ID werden geprüft |
| Rollentrennung | `App\Content\Enums\ContentRole` | `owner` sieht Einstellungen und Kosten, `editor` nur Produktion und Prüfung |
| Einstellungen abgeriegelt | `Filament\Content\Pages\Settings::canAccess()` | nur `owner` — damit auch der Rollout-Schalter |
| Portalzuordnung | `ContentUser::canAccessTenant()`, `InitializeContentTenant` | leere Liste = alle Portale, sonst Whitelist; wird bei jeder Anfrage neu geprüft |
| Sitzungsschutz | `ContentPanelProvider::panel()` | `EncryptCookies`, `StartSession`, `AuthenticateSession`, `VerifyCsrfToken` |
| Anmeldeversuche | `Filament\Auth\Pages\Login::authenticate()` | 5 Versuche je Minute und Schlüssel |
| Passwörter | `ContentUser` | `password` mit Cast `hashed`, im `$hidden`-Array |

Bewusst **keine** Spatie-Permissions: die hängen am `web`-Guard und würden die
Trennung wieder aufheben.

## 2. Signierte Vorschau-Adressen

**Befund: in Ordnung.**

Die Artikelvorschau liegt auf der Portaldomain, das Panel auf der Zentraldomain.
Eine gemeinsame Sitzung gibt es deshalb nicht — die Adresse trägt den prüfenden
Account als Parameter mit und ist signiert.

* Erzeugt in `App\Content\Services\ContentPreviewLink::for()` über
  `URL::temporarySignedRoute()`, Gültigkeit **60 Minuten**.
* Die Route hängt hinter `signed` **und**
  `App\Content\Http\Middleware\EnsureContentPreviewAccess`
  (`routes/tenant.php`, `ratgeber.preview`).
* Die Middleware prüft danach erneut: Account existiert, ist aktiv, darf dieses
  Portal sehen. Ein Link allein reicht also nicht, wenn der Account inzwischen
  abgeschaltet wurde.
* Die Antwort trägt `X-Robots-Tag: noindex, nofollow`. Eine Vorschau kann nicht
  in den Index geraten.
* Die Signatur schließt den Host ein. Ein Link für Portal A lässt sich nicht auf
  Portal B abspielen; die Entwurfsnummer wird ohnehin in der Datenbank des
  jeweiligen Portals aufgelöst.

Abhängigkeit: die Signatur hängt an `APP_KEY`. Wird der Schlüssel gewechselt,
sind alle offenen Vorschau-Adressen sofort ungültig — gewollt, steht als
Hinweis in der Go-Live-Checkliste.

## 3. Zugangsschlüssel

**Befund: ein Punkt gefunden und behoben.**

| Prüfpunkt | Befund |
| --- | --- |
| Schlüssel im Code | **Behoben:** `app/Console/Commands/GetCompanies.php` trug einen Google-Places-Schlüssel als Vorgabewert im Klartext (in der Versionsgeschichte insgesamt vier verschiedene). Der Vorgabewert ist entfernt, der Befehl liest nur noch `GOOGLE_PLACES_API_KEY`. |
| Modell- und Preisangaben | ausschließlich aus `config/content.php`, nie im Code |
| Schlüssel in der Konfiguration | alle über `env()`, kein Wert eingecheckt |
| Dienstkonto Search Console | `App\Content\Providers\SearchConsoleClient` wirft, wenn die Schlüsseldatei unter `public/` liegt; `content:golive:check` prüft dasselbe vor dem Go-Live |
| Schlüssel in Protokollen | `llm_usage_logs` speichert Tokens, Kosten, Dauer und eine gekürzte Fehlermeldung — **keine** Prompts, keine Antworten, keine Zugangsdaten |
| IndexNow-Schlüssel | wird nicht gespeichert, sondern je Portal aus `APP_KEY` abgeleitet (`IndexNowClient::key()`) und unter `/<key>.txt` berechnet ausgeliefert. Das Routenmuster ist auf Hex und mindestens acht Zeichen begrenzt, damit es `robots.txt`, `ads.txt` und `llms.txt` nicht verschluckt. |
| Wiederholung verhindert | **Neu (#98):** `scripts/secret-scan.php` prüft `AIza…`, `sk-ant-…`, `sk-…`, private Schlüsselblöcke, AWS-Zugangsdaten und `env('X', '<literal>')` mit nichtleerem Vorgabewert — im Pre-Commit-Hook (`.githooks/pre-commit`) und verbindlich in der CI (`.github/workflows/secret-scan.yml`). Details: `docs/secret-scan.md` |
| Erwartete Variablen dokumentiert | **Neu (#98):** `.env.example` führt alle erwarteten Variablen ohne Werte, `GOOGLE_PLACES_API_KEY` eingeschlossen; `.gitignore` sperrt zusätzlich alle übrigen `.env.*` |
| Zweite Sicherung | Ausgabenlimit im Anthropic-Konto, siehe Go-Live-Checkliste §2. Der `BudgetGuard` schützt vor eigenen Fehlläufen, nicht vor einem entwendeten Schlüssel. |

**Zu rotieren:** die bisher im Repository stehenden Google-Places-Schlüssel — es
sind vier, nicht einer — müssen in der Google Cloud Console gelöscht und neu
ausgestellt werden. Anleitung mit Protokoll:
`docs/messungen/places-key-rotation-anleitung.md`, Ausführung als Handarbeit unter
Ticket #97. Zu beachten: drei der vier antworten heute nur „You must enable
Billing" — das ist keine Sperre, sie sind weiterhin gültig. Er war in der
Versionsgeschichte und gilt als kompromittiert. Das ist kein offener Punkt der
Pipeline, sondern eine Aufgabe am Google-Konto — Ticket #88.

Stand 09.09.2026: Es sind **vier** Schlüssel betroffen, nicht einer — dieselbe
`env()`-Zeile trug über die Commits `e7e4664`, `fc06dd3`, `437556e` und `1096a1a`
nacheinander vier verschiedene Werte. Drei davon antworten nur deshalb mit
`REQUEST_DENIED`, weil auf ihrem Projekt die Abrechnung aus ist; sie sind gültig
und funktionieren wieder, sobald jemand die Abrechnung einschaltet. Der vierte
liegt auf einem Projekt mit deaktivierten APIs. Auch der aktuell in der `.env`
eingetragene Schlüssel wird abgelehnt, `companies:get` läuft derzeit also nicht.
Löschen aller vier, Neuausstellung mit IP- und API-Einschränkung sowie die
Prüfung der Abrechnung stehen weiterhin aus und brauchen Console-Zugang; Ablauf und
Protokolltabelle in `docs/messungen/places-key-rotation-anleitung.md`.
`companies:get` bricht seit #88 beim ersten `REQUEST_DENIED` ab, statt jede
Stadt still als geprüft zu markieren.

## 4. Crawler-Etikette

**Befund: ein Punkt gefunden und behoben.**

Die Quell-Connectoren rufen fremde Server ab. Dabei gilt:

* **Kennung.** Jeder Abruf trägt `SUN-ContentBot/1.0 (+https://<domain>/bot)`
  (`AbstractHttpConnector::userAgent()`, `content.sources.http.user_agent_template`).
  Der Bot gibt sich nie als Browser aus.
* **Auskunftsseite.** **Behoben:** die Adresse im User-Agent war bislang ein
  404. Neu ist `App\Http\Controllers\Portal\BotInfoController` unter
  `/bot` je Portal: Zweck, Verhalten, Kontakt und die `robots.txt`-Zeilen zum
  Aussperren, als `text/plain` und selbst auf `noindex`.
* **robots.txt.** Wird vor jedem Abruf je Host geprüft und für sechs Stunden
  zwischengespeichert (`AbstractHttpConnector::mayCrawl()`,
  `CompetitorOutlineExtractor`). Abschaltbar nur über
  `CONTENT_SOURCES_RESPECT_ROBOTS` — auf Produktion bleibt der Wert `true`.
* **Last.** Ein Abruf nach dem anderen, mit Pause zwischen den Seiten
  (`FoerderdatenbankConnector::throttle()`), Timeout 15 Sekunden, kein
  Wiederholungslauf innerhalb desselben Durchgangs. Der Circuit Breaker in
  `provider_states` nimmt eine Quelle nach wiederholten Fehlern aus dem
  Verkehr, statt weiter dagegen zu laufen.
* **Was nicht abgerufen wird.** Keine Formulare, keine Anmeldung, keine
  kostenpflichtigen Inhalte. Vom Wettbewerb wird die Gliederung ausgewertet,
  nicht der Text übernommen — die Duplikatsprüfung
  (`DuplicateChecker::simhash()`) verwirft zu ähnliche Entwürfe vor der
  Veröffentlichung.

## 5. Weitere Beobachtungen ohne Handlungsbedarf

* Der Rollout-Schalter ist standardmäßig aus. Fällt die Migration auf einem
  Portal aus oder ist dessen Datenbank nicht erreichbar, meldet
  `TenantRollout::isActive()` „inaktiv" und protokolliert eine Warnung. Ein
  Fehler führt also nie dazu, dass ein Portal ungewollt veröffentlicht.
* Der Tagesbericht geht ausschließlich an aktive Accounts mit der Rolle `owner`
  (`ContentAlert::ownerRecipients()`). Ein abgeschalteter Account bekommt
  sofort keine Post mehr.
* Die Sicherung aus `content:golive:backup` liegt unter `storage/app/`, das
  vollständig in `.gitignore` steht und nicht über den Webserver ausgeliefert
  wird.

---

## Ergebnis

Zwei Punkte gefunden, beide im selben Durchgang behoben: der eingecheckte
Google-Places-Schlüssel und die fehlende Auskunftsseite des Bots. Offen bleibt
nur die Rotation des kompromittierten Schlüssels am Google-Konto — eine
Handlung außerhalb des Repositories.
