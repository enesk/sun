# Funnel-Token je Portal (#24)

Der Anfrage-Dialog auf dem Firmenprofil (Theme sun-v2) spricht die Funnel-Runtime-API
des Leadsystems an. Bisher gab es dafür einen Token je Installation
(`LEADS_ELEKTRIKER_FUNNEL_TOKEN`). Seit #24 hängt der Token am Portal.

## Wo der Token steht

- Gespeichert in der `data`-Spalte des Tenants unter `lead_funnel_token`
  (Konstante `Tenant::LEAD_FUNNEL_TOKEN`). Eine Migration gibt es nicht.
- Gelesen über `Tenant::leadFunnelToken(): ?string`; `null` heißt: kein Funnel, kein Dialog.
- Gepflegt im Admin: Tenants → Bearbeiten → Tab „Features & Analytics“ →
  Abschnitt „Anfrage-Dialog“ → Feld „Anfrage-Funnel (Token)“.
- Die Basis-URL der API bleibt global in `config('themes.sun-v2.lead.api_url')`.

Der Token ist der öffentliche `public_token` des Funnels, kein Geheimnis.

## Nachzug auf einer bestehenden Installation

Einmalig je Umgebung, **bevor** der neue Code live geht bzw. direkt danach:

```bash
# Wert der alten Variable nachsehen
grep LEADS_ELEKTRIKER_FUNNEL_TOKEN .env

# Trockenlauf
php artisan tenants:move-lead-funnel-token --tenant=<ID|UUID|Domain des Elektriker-Portals> --token=<Wert>

# Schreiben
php artisan tenants:move-lead-funnel-token --tenant=<...> --token=<Wert> --apply
```

- `--token` ist Pflicht. Der Befehl liest die alte Variable nicht selbst, weil `env()` bei
  gecachter Config (Produktion) nichts liefert.
- Ein abweichender, bereits gepflegter Token bleibt stehen, ersetzen nur mit `--force`.
- Produktion: als Eigentümer ausführen,
  `sudo -u sanitaerfinden /usr/bin/php8.4 artisan tenants:move-lead-funnel-token ...`.
- Danach die Zeile `LEADS_ELEKTRIKER_FUNNEL_TOKEN` aus der `.env` entfernen. Die Config
  liest sie nicht mehr.

Alle weiteren Portale bekommen ihren Token über das Admin-Feld, sobald der Funnel im
Leadsystem veröffentlicht ist.
