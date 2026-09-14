# Go-Live-Durchführung auf Produktion — Protokoll 09.09.2026

Ticket: #89
Vorlage: `docs/messungen/golive-durchfuehrung-anleitung.md` §9
Ausgeführt auf: `88.198.64.145` (SSH-Kürzel `sun`),
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, alle Artisan-Aufrufe als
`sudo -u sanitaerfinden /usr/bin/php8.4 artisan …`

Ergebnis in einem Satz: **Abschnitte 1 bis 4 der Checkliste sind auf einen
Fehler heruntergearbeitet, die Freischaltung der Woche 1 ist bewusst
unterblieben** — ohne `ANTHROPIC_API_KEY` (#120) erzeugt kein Portal einen
Artikel.

---

## Vorbedingungen

```
Datum:                       09.09.2026
content:golive:check vorher: 143 Prüfpunkte, 1 Fehler, 73 Warnungen, Exit 1
content:golive:check nachher:143 Prüfpunkte, 1 Fehler, 21 Warnungen, Exit 1
Fehler:                      Anthropic-Zugang — ANTHROPIC_API_KEY fehlt (#120)
Begründete Warnungen:        Voyage-Zugang — VOYAGE_API_KEY fehlt (#120)
                             Search-Console-Dienstkonto — GOOGLE_SERVICE_ACCOUNT_JSON fehlt (#120)
                             AdSense-Ertragsdaten — CONTENT_ADSENSE_ENABLED=false, so gewollt
                             Freigeschaltete Portale — keins, siehe Woche 1
                             Auto-Live-Schwelle (17x) — 80 statt 85, setzt content:rollout --threshold=85 beim Rollout
Auto-Ads-Nachweis:           kein aktiver auto_ads-Platz gemeldet
Sicherung NDJSON:            storage/app/backups/content/2026-09-09 — 46 Dateien, je Portal posts und article_drafts, durchweg 0 Zeilen
Datenbank-Backup extern:     FEHLT — die automatische Sicherung schreibt leere Dumps, siehe unten (#134)
```

### Was zusätzlich getan wurde: `tenant_content_settings` nachgezogen

`tenant_content_settings` war in **allen 23** Portal-Datenbanken leer. Ohne
diese Zeile meldete jedes Portal „articles_per_day ist 0 — das Portal erzeugt
nichts", Schwelle 0 und leere `gsc_property`. Nachgezogen mit dem vorhandenen,
idempotenten Seeder:

```
php artisan db:seed --class=TenantContentSettingSeeder --force
→ Content-Einstellungen: 23 angelegt, 0 bereits vorhanden.
```

Danach je Portal: `articles_per_day = 2`, `auto_publish_threshold` 90 für YMYL
und 80 sonst, `gsc_property = sc-domain:<domain>`, `is_active` weiterhin
`false`. Als YMYL erkannt: Tierarztportal.com, ApothekeFinden, Unfallchirurgie
in der Nähe, Zahnarzt in der Nähe, Energieberater, ArztFinder.

Damit fielen 52 der 73 Warnungen weg.

### Grün auf Produktion

| Punkt | Befund |
| --- | --- |
| Pipeline eingeschaltet | `CONTENT_PIPELINE_ENABLED=true` |
| Zeitzone des Scheduler | `Europe/Berlin` |
| Sieben Quell-Connectoren | eingeschaltet, Zugang vollständig |
| Budget | 35,00 USD/Tag, 1.050,00 USD/Monat, 2,50 USD × 23 Portale |
| Provider-Anteile | Summe 1,00 |
| Queue-Verbindung | `redis`, alle sechs Pipeline-Queues in einem Horizon-Supervisor |
| Deploy-Ziel | Host, Domain und Repository in `deploy.php` eingetragen |
| Scheduler | `content:report:daily` steht auf `0 20 * * *` |
| Erreichbarkeit | `/` und `/bot` je HTTP 200 am Ursprung für die drei Portale der Woche 1 |

### Tagesbericht geprobt

```
php artisan content:report:daily --no-mail
→ Tagesbericht 2026-09-09: 0 von 0 Artikeln, 0 offene Slots, 0.00 USD heute.
php artisan content:report:daily
→ Tagesbericht versandt an: kul@widimedia.com
```

„0 von 0" ist der richtige Wert: die Zählung nimmt nur freigeschaltete Portale
(#102), und es ist keins freigeschaltet. Der Versandweg steht.

**Offen:** Es gibt nur einen Redaktions-Account mit der Rolle `owner`
(`kul@widimedia.com`). Für Uwe fehlt einer; Abschnitt 4 der Checkliste verlangt
beide. Die Adresse steht nirgends im Repository, deshalb nicht angelegt.

### Datenbanksicherung: wirkungslos

`/home/sanitaerfinden/backups/databases/<db>/<datum>/*.sql.gz` — für jeden Tag
und jede der 23 Datenbanken genau eine Datei, jede davon **20 Byte**, also ein
leeres gzip. Auch für die zentrale Datenbank `sun` (2 GB). Der Cron-Eintrag in
`/etc/cron.d/clp` ruft `clpctl db:backup`; dieser Befehl existiert in der
installierten CloudPanel-Version nicht mehr (`Command "db:backup" is not
defined`). Es gibt damit auf Produktion keine verwertbare Datenbanksicherung.
Eigenes Ticket #134. Die geforderte externe Sicherung wurde nicht ersatzweise
von Hand gezogen: die Dumps summieren sich auf rund 8 GB Nutzdaten mit
Betriebsdaten von über 200.000 Firmen, dafür braucht es ein festgelegtes
Sicherungsziel, keinen einmaligen Download auf ein Arbeitsgerät.

---

## Woche 1

```
Freigeschaltet:              KEINS — bewusst nicht ausgeführt
Vorgesehen:                  ApothekeFinden (50, YMYL, Schwelle 90),
                             Hoch- und Tiefbauunternehmen (24, 85),
                             ElektrikerPortal (30, 85)
Befehl:                      php artisan content:rollout --activate=50 --activate=24 --activate=30 --threshold=85
Start:                       offen
Automatisch veröffentlicht:  —
Abnahme Enes:                offen
```

**Begründung der Nichtausführung.** `ANTHROPIC_API_KEY` fehlt in der
Produktions-`.env` (#120, Handarbeit im Konto). Ohne Modellzugang bricht jeder
Tageslauf in der Erzeugungsstufe ab. Eine Freischaltung setzt `activated_at`
auf den Tag der Freischaltung; die Wochenzählung der Abnahme
(`TenantRollout::activeTenantsOn()`, #102) begänne dann mit Tagen ohne jede
Produktion, und die Vorgabe „40 von 42" wäre strukturell verfehlt, ohne dass
etwas an der Pipeline falsch wäre. Freischalten heißt hier: Fehlläufe und
Alarme erzeugen und die Abnahme verderben. Deshalb erst der Schlüssel, dann der
Schalter.

**Auswahl begründet.** Alle drei antworten am Ursprung (an Cloudflare vorbei)
mit HTTP 200 auf `/` und `/bot`. Keins steckt in den Cloudflare-Weiterleitungen
aus #127 oder unter den vhost-Namen ohne Tenant aus #128. `sanitaerfinden.com`
bleibt außen vor, weil es zugleich `CENTRAL_DOMAIN` ist.

---

## Woche 2

```
Freigeschaltet:              offen
Voller Tag:                  offen
Tagesbericht 20:00:          Versandweg geprüft, erreicht Enes: ja — erreicht Uwe: nein (Account fehlt)
Abnahme Enes:                offen
```

Vor der Woche 2 zusätzlich zu klären, weil dann alle Portale öffentlich
Ratgeber ausliefern: #130 und #131 (Cloudflare-Weiterleitungen und fehlende
Namen im Zertifikat). #129 (Weiterleitungsschleife der www.-Namen) ist am
09.09.2026 behoben — alle 25 www.-Namen des vhost leiten jetzt auf die
Hauptdomain.

---

## Nächster Schritt

1. #120 abschließen: Anthropic-, Voyage- und Google-Schlüssel in die
   Produktions-`.env`, danach `config:cache`, `systemctl reload php8.4-fpm`
   und `supervisorctl restart sanitaerfinden-horizon`.
2. #105: Guthaben aufladen.
3. `content:golive:check` erneut — muss mit Exit 0 enden.
4. Redaktions-Account für Uwe anlegen.
5. Dann erst der Befehl aus „Woche 1".
