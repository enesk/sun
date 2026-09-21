# Security-Review des Ratgebersystems

Status: **abgeschlossen, keine offenen Punkte** (S1–S4 behoben in #37, 2026-09-21)
Ticket: #21
Prüfdatum: 2026-09-21, Stand Arbeitskopie (Ratgebersystem noch nicht eingecheckt, siehe `docs/guide-golive.md` §0)
Umfang: `app/Guide/**`, `config/guide.php`, Content-Panel (`ContentPanelProvider`, `User`),
Portal-Routen `guide.preview`, `/<key>.txt`, Weiterleitungen, Login-Weiterleitung.
Vorgänger für die alte Pipeline: `docs/content-security-review.md` (#26).

Bewertung je Feld: **ok** / **Lücke** (mit Schweregrad niedrig / mittel / hoch).
Ein Punkt ist erst geschlossen, wenn er in §7 auf „behoben“ mit Commit steht.

---

## 1. Panel-Guard — ok, eine bewusste Entscheidung

* Zugang nur über `User::canAccessPanel()` → `canAccessContentPanel()` (`app/Models/User.php:129`).
  Rolle aus `contentRole()`: gesperrt → keine Rolle, sonst `guide_role`, Admin ohne Rolle
  = owner (`app/Content/Concerns/InteractsWithContentPanel.php:26-39`). Portal-Nutzer ohne
  Rolle erhalten 403.
* `guide_role` ist nicht mass-assignable (`User.php:37-49`).
* Filaments `Authenticate` läuft als persistente Middleware auch bei Livewire-Updates
  (`ContentPanelProvider.php:134-139`): ein während der Sitzung gesperrtes Konto fliegt
  beim nächsten Request.
* Nur-Inhaber-Seiten serverseitig gesperrt: Kosten (`Costs.php:36-41`), Einstellungen
  (`TenantGuideSettings.php:80-85`), Prompts (`PromptTemplateResource.php:67-72`).
* Aktionen Freigeben / Neu schreiben / Verwerfen über `->disabled()` (`ReviewRun.php:138,166,193`),
  von Filament serverseitig nicht ausführbar; Rollback prüft die Zugehörigkeit der Fassung
  (`VersionHistory.php:196`); Tenant-Switcher prüft `isAllowed()`.
* **Entscheidung, kein Befund:** Jede Panel-Rolle sieht alle Portale
  (`canAccessContentTenant()` = true). Das entspricht dem Freeze in `docs/guide-system.md` §7
  (Redaktion arbeitet portalübergreifend). Kommt eine Portalbindung je Editor, müssen
  `ReviewRun::$tenantId/$runId` und `ViewTopic::$tenantId/$topicId` `#[Locked]` werden.
* Hinweis: Das Panel verlangt keine bestätigte Mailadresse; Konten legt nur
  `guide:user:create` an, daher unkritisch.

## 2. Signierte Preview-Routen — ok

* `guide.preview` mit Middleware `signed` + `EnsureContentPreviewAccess` (`routes/tenant.php:218-221`).
* Ablauf 60 min (`GuidePreviewLink.php:38-45`); Signatur über die absolute URL inkl.
  Portal-Host — ein Link von Portal A ist auf Portal B ungültig; die Fassung wird in der
  Tenant-DB des Hosts gesucht (`GuidePreviewController.php:37`). ID-Durchprobieren scheitert
  an der Signatur.
* `noindex` doppelt (`X-Robots-Tag`, `indexable: false`), keine Anzeigen in der Vorschau.
* Restrisiko (akzeptiert): Der Link ist 60 min für jeden gültig, der ihn hat. Nicht in
  Tickets oder Chats kopieren.

## 3. API-Keys — ok

* `ANTHROPIC_API_KEY` (`config/guide.php:38`) und `FAL_API_KEY` (`:378`) per `env()` ohne
  Vorgabewert; im Code nur `config()`.
* Keys nur im Request-Header (`LlmClient.php:641-642`, `FalClient.php:155`); Fehlertexte,
  `llm_usage_logs` und Alarme enthalten Status und Antwortrumpf, keinen Header.
* Livewire-State der Einstellungen enthält nur Budgetzahlen.
* IndexNow: je Portal aus `APP_KEY` abgeleiteter HMAC, 32 Zeichen, Vergleich mit
  `hash_equals`, sonst 404. Öffentlich gewollt; `APP_KEY` ist daraus nicht rückrechenbar.

## 4. Upload-Validierung im Import-Wizard — Lücke (mittel)

Ok: Dateityp csv/xlsx, 5 MB, Endungsprüfung (`ImportWizard.php:286-291, 432-436`), Ablage
auf dem privaten Disk `local` unter `guide-imports/` mit UUID-Name, Aufräumen nach 7 Tagen.

* **S1 (mittel):** `data.stored_path` ist öffentlicher Livewire-State und wird ungeprüft
  gelesen (`ImportWizard.php:82, 506`). Ein Editor kann per `$wire.set('data.stored_path', …)`
  beliebige csv/tsv/txt/xlsx unter `storage/app` in der Vorschau lesen (u. a. `backups/`,
  `seo-cleanup/`). `finish()` prüft `MAX_ROWS` nicht erneut.
* **S1b (niedrig):** XLSX wird vollständig geladen, bevor das Zeilenlimit greift
  (Zip-Bomb); das Einfügefeld hat keine Längengrenze.

## 5. HTML-Sanitizing — ok für Themenartikel, Lücke im Altpfad (niedrig)

* Themenartikel: eigener DOM-Whitelist-Filter `HtmlAssembler::sanitize`
  (`app/Guide/Writing/HtmlAssembler.php:269-309`): erlaubte Tags aus `config/guide.php`,
  `script/style/iframe/svg/img` samt Inhalt entfernt, alle Attribute weg außer `scope`,
  Links nur intern ohne `//` oder auf Quellen mit `^https?://`.
* Kurzantwort, FAQ: `strip_tags` + `{{ }}`. Changelog- und Quellen-URLs über `safeUrl`
  (nur http/https), `rel="nofollow noopener"`.
* JSON-LD mit `JSON_HEX_TAG` — kein `</script>`-Ausbruch. Diff-Ansicht escaped jedes Wort
  (`ArticleDiffRenderer.php:263-304`); Prompt-Editor-Vorschau über `e()`.
* **S3 (niedrig):** Altartikel im selben Template: `body_html` (`GuidePageData.php:358`) und
  `credit_html` (`ArticleBlockPresenter.php:370`) ungefiltert über `{!! !!}`
  (`show.blade.php:47,95,103`); Quellen-URLs ohne Schema-Prüfung (`GuidePageData.php:386`).
  Eine `javascript:`-URL in `draft_sources` würde klickbar. `intro_html` der Kategorie
  (`category.blade.php:25`) ungefiltert, derzeit ohne Eingabeweg.
* **S4 (niedrig):** Externe Links im Fließtext nur `rel="noopener"` (`HtmlAssembler.php:431`).

## 6. Redirect-Tabelle und Weiterleitungen — Tabelle ok, Lücke beim Login

* `guide_redirects`: Quelle über `normalizePath` (aus `//evil.com/x` wird `/x`); einzige
  Schreiber erzeugen relative Pfade (`Category.php:61-66`, `LegacyOverlapResolver.php:106-110`).
  Kein Panel-Feld, kein Import. CRLF blockt `header()`.
* **S4 (niedrig):** `Redirect::normalizeTarget` erlaubt absolute `https?://`-Ziele
  (`app/Guide/Models/Redirect.php:104`) — heute ohne Aufrufer mit Nutzereingabe, trotzdem
  entfernen. CTA-Ziel in *Einstellungen › Portal* lässt `//evil.com` zu
  (`TenantGuideSettings.php:207`). `TenantSwitcher.php:56` vergleicht den Referer ohne
  abschließenden Slash.
* **S2 (niedrig–mittel):** Offene Weiterleitung nach dem Login:
  `LoginController::showLoginForm` setzt `setIntendedUrl(url()->previous())` aus dem
  ungeprüften Referer (`LoginController.php:39-41`, ebenso `OAuthController.php:30`,
  `RegisterController.php:84`); `RedirectAwareTrait::getRedirectUrl` leitet dorthin. Ein Link
  von `evil.com` auf `/login` führt nach dem Login auf `evil.com`. Betrifft auch
  `/content/login`, das auf diesen Login weiterleitet.

## Nebenbefunde

* Cache-Trennung ok: Seiten-Cache-Keys tragen die Tenant-UUID (`GuidePageCache.php:42-76`),
  Panel-Caches die Menge der Tenant-IDs; Import-State je Nutzer.
* **S4 (niedrig):** Leistungs-Export schreibt Titel ungefiltert in CSV
  (`PerformanceDashboardService.php:183-184`) — Formel-Injection bei `= + - @`.

---

## 7. Offene Punkte

| # | Punkt | Schwere | Status | Commit |
|---|---|---|---|---|
| S1 | Import-Wizard: Pfad serverseitig, Präfix/UUID prüfen, `MAX_ROWS` in `finish()` | mittel | behoben | #37 (Arbeitskopie; Commit zusammen mit dem Ratgebersystem, siehe `guide-golive.md` §0) |
| S1b | Import: zeilenweises Lesen mit Abbruch, `maxLength` Einfügefeld | niedrig | behoben | #37 (Arbeitskopie; Commit zusammen mit dem Ratgebersystem, siehe `guide-golive.md` §0) |
| S2 | Login: Intended-URL nur auf eigene Hosts | niedrig–mittel | behoben | #37 (Arbeitskopie; Commit zusammen mit dem Ratgebersystem, siehe `guide-golive.md` §0) |
| S3 | Altpfad: `body_html`/`credit_html` sanitizen, Quellen-URLs über `safeUrl`, `intro_html` | niedrig | behoben | #37 (Arbeitskopie; Commit zusammen mit dem Ratgebersystem, siehe `guide-golive.md` §0) |
| S4 | CTA-Regex, `normalizeTarget` nur Pfade, `nofollow` im Fließtext, CSV-Formeln, Switcher-Referer | niedrig | behoben | #37 (Arbeitskopie; Commit zusammen mit dem Ratgebersystem, siehe `guide-golive.md` §0) |

Freigabe: Wenn alle Zeilen „behoben“ tragen, Status oben auf **abgeschlossen, keine offenen
Punkte** setzen und in `docs/guide-golive.md` §7 eintragen. — Erledigt 2026-09-21 (#37).

### Behebung (#37)

* **S1/S1b** `app/Guide/Filament/Pages/ImportWizard.php`: Pfad liegt in `#[Locked] $storedPath`
  (nicht mehr in `data`), Import-State im Cache hält ihn serverseitig. Vor jedem Lesen prüft
  `storedFile()` `guide-imports/<UUID>.(csv|xlsx)` und die Existenz. `rows()` liest die Quelle
  zeilenweise und bricht nach `MAX_ROWS + 2` Inhaltszeilen bzw. 10.000 Zeilen insgesamt ab
  (kein `iterator_to_array()` mehr). `finish()` prüft `MAX_ROWS` erneut. Einfügefeld
  `maxLength(200.000)`, serverseitig in `rows()` nochmals geprüft.
* **S2** neu `app/Support/IntendedUrl.php`: Intended-URL nur mit http(s) und Host = aktueller
  Host, `tenancy.central_domains` oder `tenants.domain` (mit/ohne `www.`). Gesetzt über
  `IntendedUrl::rememberPrevious()` in Login-, Register- und OAuth-Controller; beim Lesen
  prüfen `RedirectAwareTrait::getRedirectUrl` und `RegisterController::redirectPath` erneut
  (schützt auch bereits in Sessions stehende Werte).
* **S3** `HtmlAssembler::sanitizeLegacy()`: dieselbe Whitelist wie `sanitize()`, aber H2/H3 mit
  `id` und `class` an `<p>` bleiben, interne Links auf wurzelrelative Pfade, externe nur http(s).
  Angewendet auf `body_html` und `intro_html` (`GuidePageData`) sowie `credit_html`
  (`ArticleBlockPresenter`). Quellen-URLs im Altpfad über `safeUrl` (Presenter und
  `GuidePageData::legacyArticle`).
* **S4** CTA-Regex `^(/(?![/\\])|https?://)` in `TenantGuideSettings`, beim Ausgeben in
  `GuidePageData::cta()` dieselbe Prüfung (Altwerte fallen auf die Firmensuche zurück).
  `Redirect::normalizeTarget` liefert nur noch Pfade; `HandleGuideRedirects` folgt keinem
  gespeicherten Ziel, das nicht mit genau einem `/` beginnt. Fließtext-Links
  `rel="nofollow noopener"`. Leistungs-Export: Textzellen mit `= + - @`/Tab/CR bekommen ein `'`.
  `TenantSwitcher::returnUrl` vergleicht gegen `url('/')` bzw. `url('/').'/'`.
