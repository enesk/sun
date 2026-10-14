# Portale: 21 ausgeliefert, 2 nicht

**Stand 14.10.2026.** Im System stehen **23 Tenants**, ausgeliefert werden
davon **21**. Jede Zahl in Tickets, Berichten und Prüfläufen bezieht sich auf
die 21 — wer 23 liest, liest einen Stand vor dem 14.10.2026.

## Warum 21 und nicht 23

Zwei Domains zeigen an der Cloudflare-Kante auf ein anderes, gepflegtes
Angebot. Die Portale dahinter sind intakt und antworten am Ursprung mit 200,
erreichbar ist sie aber niemand:

| Tenant | Domain | Weiterleitung an der Kante | Ziel |
|---|---|---|---|
| 51 | `firmenfreund.net` | 301 | `https://solar-finden.de/` |
| 43 | `tierarztportal.com` | 302 | `https://pfotencheck.tierarztportal.com/` |

**Entscheidung von Enes am 14.10.2026 (Variante A, Ticket #16):** Die Domains
bleiben beim anderen Angebot. Damit sind es keine Portale mehr. **An Cloudflare
wird nichts angefasst** — keine Regel, kein Token, kein SSL-Modus.

## Wann „23" weiter richtig ist

Nicht jede 23 im Projekt ist falsch. Die Zahl der **Tenants und Datenbanken**
bleibt 23 — die beiden Portale behalten Tenant, Datenbank und Storage:

* `tenants:migrate`, `sun:db:pull`, Sicherungen und Dump-Zählungen arbeiten
  weiter über 23 Tenants (plus die zentrale Datenbank).
* Wer dort auf 21 ändert, bricht die Prüfungen.

Auf **21** bezieht sich alles, was mit Auslieferung zu tun hat: Texte,
Theme-Umstellung, Sichtprüfung, Messung, ausgelieferte Hostnames.

## Woran das im Code hängt

Maßgeblich ist ein Attribut am Tenant, keine Liste in einer
Konfigurationsdatei — eine zweite Liste läuft beim nächsten Portalwechsel
auseinander:

* `Tenant::NOT_DELIVERED` (`not_delivered`, bool) und
  `Tenant::NOT_DELIVERED_NOTE` (Grund samt Datum und Ticket),
* abgefragt über `$tenant->isDelivered()` und `$tenant->notDeliveredNote()`.

Wer ein weiteres Portal stilllegt, setzt diese zwei Attribute — und sonst
nichts.

## Was dadurch anders läuft

| Stelle | Verhalten |
|---|---|
| `sun:branches:status` | führt beide als „nicht ausgeliefert", zählt sie nicht zu den offenen, nennt den Grund unter der Tabelle; `--bereit` listet sie nicht |
| `turnstile:keys:check` | meldet „18 eingetragen, davon 16 ausgeliefert" und nennt die zwei; Exit 0, ohne dass ein Widget geändert wurde |
| Branchenpakete `solar`, `tierarzt` | bleiben liegen und zugeordnet, bekommen aber keine Texte (#13) |
| GA4-Kennungen der beiden | bleiben in `tenants.data` stehen, wirken aber nicht (#14) |
| Tenant-Datenbanken | bleiben unverändert, `sun:db:pull` spiegelt sie weiter mit (#11) |
| Cloudflare-Widgets | unverändert, beide Hostnames stehen weiter in Gruppe B |

## Wenn die Entscheidung zurückgedreht wird

Dann sind es drei Schritte, in dieser Reihenfolge:

1. Kontoarbeit bei Cloudflare (nur Mensch): SSL-Modus der Zone auf „Full",
   Weiterleitung entfernen, Zertifikat nachziehen, zurück auf „Full (strict)".
   Begründung und Fallen in `docs/messungen/weiterleitungen-entfernen-anleitung.md`
   — bei `firmenfreund.net` nimmt die Regel `/.well-known/acme-challenge/` mit,
   eine HTTP-01-Probe scheitert also, solange sie steht.
2. `not_delivered` am Tenant entfernen.
3. Texte für das Branchenpaket schreiben, dann umstellen (#13, #15).

Nachbauen muss niemand etwas: Pakete, Datenbanken und Messkennungen stehen
alle noch.
