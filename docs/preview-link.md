# Vorschau-Link (SUN-PV-001)

## Zweck

Eingeladene Personen sehen ein Portal auf der echten Domain im Theme sun-v2,
ohne Werbung und ohne Tracking. Alle anderen Besucher sehen das Portal
unverändert. Am Tenant, an `ad_settings` und an den Belegungen ändert sich
nichts.

## Ablauf für Enes

1. **Anlegen:** Central-Panel → *Vorschau-Links* → *Anlegen*. Name, Ablaufdatum,
   Portale (oder „alle Portale") wählen. Danach zeigt der Dialog die URL je
   Portal (`https://<portal-domain>/vorschau/<token>`) — **nur dieses eine
   Mal**, gespeichert wird nur der Hash. Geht die URL verloren:
   *Token neu erzeugen* (der alte Link ist dann ungültig).
2. **Verschicken:** URL kopieren und an die Person schicken. Beim Öffnen setzt
   das Portal das Cookie `sun_preview` und leitet auf die Startseite weiter.
   Ab dann ist jede Seite dieses Portals im Browser der Person Vorschau,
   bis der Link abläuft oder widerrufen wird. Selbst beenden:
   `https://<portal-domain>/vorschau/beenden`.
3. **Widerrufen:** In der Liste *Widerrufen*. Das Prüfergebnis wird auf allen
   betroffenen Portalen sofort verworfen, spätestens nach 60 Sekunden
   (Cache-TTL) sieht die Person wieder das normale Portal, ihr Cookie wird
   beim nächsten Aufruf gelöscht.

## Cache-Befund (26.11.2026)

| Ebene | Befund |
|---|---|
| Anwendung | Kein Response- oder Page-Cache für HTML. `GuidePageCache` speichert nur View-Daten (Arrays), das HTML entsteht je Request neu. Einzige HTML-Ablage im Cache ist das Bewertungs-Widget (`/widget/{slug}`, iframe für fremde Seiten), das hat kein Portal-Layout, keine Werbung und hängt nicht am Theme. |
| nginx (88.198.64.145, `sites-enabled/sanitaerfinden.dev.conf`) | Kein `fastcgi_cache`, kein `proxy_cache`. Der 443-Block reicht an nginx `:8080` weiter, der direkt an PHP-FPM geht. `expires max` gilt nur für statische Dateien (css, js, Bilder, Schriften). Varnish läuft auf dem Server (`:6081`), liegt aber **nicht** im Weg der Portale. |
| Cloudflare | Kein Zugriff aufs Konto (Vorgabe). Von außen gemessen: Startseite und Stadtseite von elektrikerportal.com liefern bei wiederholtem Abruf `cf-cache-status: DYNAMIC`, die Kante cacht HTML also nicht. Ursprung liefert für normale Seiten `Cache-Control: no-cache, no-store, private` plus Session-Cookie. sanitaerfinden.com läuft ohne Kante. |

**Ausnahme der Vorschau:** Da HTML nirgends gecacht wird, braucht es keine
Bypass-Regel. Zusätzlich tragen alle Vorschau-Antworten (`ResolvePreview`) sowie
Einstieg und Ausstieg (`PreviewController`) `Cache-Control: private, no-store`
und `X-Robots-Tag: noindex, nofollow`. Sollte für die Portal-Zonen später eine
Cloudflare-Regel entstehen, die HTML cacht, braucht sie die Ausnahme
„Cache umgehen, wenn Cookie `sun_preview` vorhanden". Die richtet Enes ein.

## Review (26.11.2026)

| Punkt | Ergebnis |
|---|---|
| Token nicht im Klartext gespeichert | erfüllt: `preview_links.token_hash` (sha256), der Klartext existiert nur als Rückgabe von `issue()`/`regenerate()` und im Dialog. Die Anwendung loggt ihn nirgends. |
| Token nicht geloggt | Restrisiko akzeptiert: Die Einstiegs-URL steht wie jede URL im nginx-Zugriffslog (`/home/sanitaerfinden/logs/nginx`, 7 Tage) und im Cloudflare-Log, genau wie Passwort-Reset-Links. Gegenmittel: begrenzte Laufzeit, Widerruf, *Token neu erzeugen*. Der Weiterleitung fehlt der Referer (`Referrer-Policy: no-referrer`), der Token bleibt nicht in der Adresszeile. |
| Cookie verschlüsselt, HttpOnly | erfüllt: `sun_preview` steht nicht in der Ausnahmeliste von `EncryptCookies` (`bootstrap/app.php`), HttpOnly, SameSite=Lax, Secure (außer lokal über http), Laufzeit bis zum Ablauf des Links. |
| Rate-Limit Token-Route | erfüllt: `throttle:10,1` je IP und Domain auf `/vorschau/{token}`, Token-Muster `[A-Za-z0-9]{40}`. Ein ungültiger Token bekommt dieselbe Weiterleitung wie ein gültiger, nur ohne Cookie. |
| Widerruf wirkt | erfüllt: Widerrufen und *Token neu erzeugen* rufen `forgetCached()` je Portal auf, die Middleware prüft bei jedem Request gegen die Central-DB (60 s gecacht) und löscht ein ungültiges Cookie. |
| Keine Werbe- und Tracking-Requests | erfüllt: `AdSlot::isSuppressed()` und `Sun\AdSlot::shouldRender()` greifen bei `preview_active()`. Damit fallen feste Slots, Sticky, Auto-Ads-Tag (`Sun\AutoAds`), der AdSense-Loader im `head`-Stack und die eigene Ad-Zählung weg. `partials/analytics` lässt GA4, Consent-Mode-Aufruf, GTM und Tracking-HTML weg. |
| Normale Besucher unverändert | erfüllt: Ohne Cookie läuft `ResolvePreview` ohne Wirkung durch, `PreviewContext` ist inaktiv, Theme und Optionen kommen wie bisher vom Tenant. |

Offene Punkte: keine.

## Gegenprobe auf Live und Abnahme

Steht noch aus, weil die Funktion noch nicht ausgerollt ist
(`/vorschau/…` liefert auf Live am 26.11.2026 HTTP 404).

Gegenprobe (Profilseite eines großen Portals): Browser A öffnet den
Vorschau-Link und surft, Browser B ohne Cookie ruft dieselben URLs auf.
Erwartet: B zeigt das normale Layout mit Werbung, A zeigt sun-v2 ohne Werbung,
und A hat in der Antwort `Cache-Control: private, no-store`.

| Datum | Portal / URL | Browser A (Vorschau) | Browser B (normal) | Prüfer |
|---|---|---|---|---|
| | | | | |

Abnahme durch Enes: Link anlegen, auf dem Handy öffnen, durchs Portal
klicken, Link widerrufen. Erledigt am: ____
