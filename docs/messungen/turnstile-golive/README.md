# Belege zum Go-Live des Bot-Schutzes

Ablage zu `docs/turnstile-golive.md` §8. Eine Zeile der Checkliste gilt nur mit Beleg
als erledigt — Befehlsausgabe als `.txt`/`.json`, Sichtprüfung als Screenshot.

| Datei / Ordner | Inhalt | Wer |
|---|---|---|
| `vorpruefung-2026-10-08.md` | Maschinenanteil vor dem Deploy: was lokal und an der Produktion geprüft wurde, ohne Schlüssel | Agent (#13) |
| `durchfuehrung-2026-10-08.md` | Nachprüfung 08.10.2026, Commit-Reife des Moduls, offene Zeilen | Agent (#36) |
| `schluessel-2026-10-08.txt` | §1.1: Tokenablage, Soll-Ist der drei Widgets, sechs `.env`-Werte, Siteverify je Gruppe, Config-Cache-Drift | Agent (#36) |
| `deploy-<zeitstempel>.log` | Protokoll von `scripts/turnstile-golive-deploy.sh` (Trockenlauf und Ausführung) | #36 |
| `keys-check-<datum>.txt` | `turnstile:keys:check --siteverify` in Produktion, Exit 0 | #36 |
| `deploy-<stempel>.log` | Protokoll von `scripts/turnstile-golive-deploy.sh` — Vorbedingungen, jeder Befehl, Ausgabe von `migrate`, `tenants:migrate`, Seeder und Backfill. Ersetzt `migrate-<datum>.txt`, wenn der Deploy über das Skript lief | #36 |
| `migrate-<datum>.txt` | `migrate --force` und `tenants:migrate --force`, falls von Hand gefahren | #36 |
| `vorwoche-<datum>.json` | neue `users` und `companies` je Portal, sieben Tage vor Stufe 1 | #36 |
| `backup-<datum>.txt` | `antispam:backup`, Dateiliste | #36 |
| `csp-report-<datum>.txt` | 24 h `CSP_MODE=report` in Produktion, gesichtete Meldungen | #36 |
| `stufe1/` | sechs Screenshots (drei Portale × mobil/Desktop), `turnstile:report --json` nach 24 h | #36 |
| `stufe2/`, `stufe3/` | Screenshots und je drei Tagesberichte | #36 |

Welche Zeile der Checkliste als naechste offen ist und welche dieser Belege noch fehlen,
sagt `scripts/turnstile-golive-stand.sh` (nur lesend, Exit 70 = etwas offen). Den Deploy
selbst fährt `scripts/turnstile-golive-deploy.sh` (ohne Argument Trockenlauf).

Nie hier ablegen: Secrets, vollständige Tokens, Klartext-IPs oder -Mailadressen. Die
Ausgabe von `turnstile:keys:check` kürzt den Sitekey selbst und zeigt nie einen Secret;
das Verifikations-Log führt ohnehin nur HMAC-Hashes.
