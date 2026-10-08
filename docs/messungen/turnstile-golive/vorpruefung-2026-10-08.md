# Vorprüfung vor dem Go-Live (Ticket #13)

Datum: 08.10.2026
Zweck: alles belegen, was ohne Produktionsschlüssel und ohne Deploy prüfbar ist, damit
Stufe 1 nur noch aus Handgriffen besteht (#36).

## 1. Produktion — Ausgangszustand

```
ssh sun; cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
grep -c '^TURNSTILE' .env            → 0
grep -n '^CSP_MODE\|^TURNSTILE_ALERT' .env → keine Treffer
git log --oneline -1                 → f5fa66ed feat(theme): Branchenpaket Gutachter …
crontab -u sanitaerfinden -l         → * * * * * … /usr/bin/php8.4 artisan schedule:run
```

Lesart: das Modul ist dort nicht vorhanden (B2), kein Schlüssel gesetzt (B1), der
Minuten-Cron für den Scheduler läuft — die drei Turnstile-Einträge kommen mit dem
Deploy von selbst dazu und brauchen keinen Crontab-Eingriff.

## 2. Schlüssel und Gruppen (lokal, Vorprüfung zu §1.2)

```
php artisan turnstile:keys:check
+--------+-------------+-----------+-------------+----------------+------------+-------------------+
| Gruppe | Name        | Hostnames | Sitekey     | Schluessel     | Siteverify | Falsch zugeordnet |
+--------+-------------+-----------+-------------+----------------+------------+-------------------+
| A      | SUN Welle 1 | 3         | 1x00000000… | Testschluessel | –          | –                 |
| B      | SUN Welle 2 | 10        | (leer)      | fehlt          | –          | –                 |
| C      | SUN Welle 3 | 5         | (leer)      | fehlt          | –          | –                 |
+--------+-------------+-----------+-------------+----------------+------------+-------------------+
Eingetragene Hostnames insgesamt: 18 (Soll laut docs/turnstile.md Abschnitt 8: 18)
  www.elektrikerportal.com → Gruppe A (ok)
  apotheke.firmenfreund.de → Gruppe B (ok)
  zahnarzt.firmenfreund.de → Gruppe B (ok)
Exit 0
```

Lokal ist das der erwartete Zustand: Testschlüssel in A, B und C leer, Exit 0 als
Warnung. In Produktion gibt derselbe Befehl Exit 1, solange eine Gruppe
Testschlüssel trägt oder fehlt — das ist die Sperre aus §1.2.

Belegt ist damit: die Gruppenzuordnung stimmt (18 = Soll), die `www`-Variante und die
`firmenfreund.de`-Subdomains finden ihre Gruppe über den Elterneintrag — der Fall mit
dem grössten Fehlerrisiko in Stufe 2.

## 3. Rollout-Werkzeug (§0.1)

`php artisan turnstile:rollout` ohne Schalter listet alle Portale mit Gruppe,
Schalter, Modus beider Aktionen, Fail-Mode und Schlüsselherkunft und ändert nichts;
lokal stehen 22 `.test`-Portale auf `ja`, Fail-Mode `open`, Schlüssel `Gruppe`. Der
Lauf legt keine Zeilen an.

## 4. Alarm-Bindung (§1.5, Nachzug zu #19)

```
php artisan event:list | grep -A1 Siteverify
  App\Turnstile\Events\SiteverifyUnreachable
  ⇂ App\Listeners\Turnstile\AlertOnSiteverifyUnreachable@handle
```

## 5. CSP (§1.5, Nachzug zu #21)

`config/csp.php:41` → `env('CSP_MODE', 'enforce')`. Browser-Messungen liegen unter
`docs/messungen/csp-browser-messung-2026-10-08.md` (Chrome 154, #28) und
`csp-webkit-nonce-2026-10-08.json` (WebKit, #29). In Produktion trotzdem erst
`report` für 24 h, Begründung in `docs/turnstile-golive.md` §1.5.

## 6. Offen

B1 und B2 aus §0 — beides Handgriffe eines Menschen, Ticket #36.
