#!/bin/bash
#
# /ads.txt aller Portal-Domains von aussen pruefen (Ticket #31,
# docs/ad-slots-golive.md §4).
#
# Je Domain: HTTP-Status, Zahl der nicht-leeren Zeilen, Zahl der Google-Zeilen
# und die gefundenen Publisher-IDs. Erwartet wird fuer ein freigegebenes Portal
# genau EINE Google-Zeile; fuer ein Portal ohne Freigabe eine 404 oder nur
# Zusatzzeilen fremder Vermarkter. Eine Dublette ist in Googles Pruefbericht
# eine Warnung, eine leere Datei liest Google als "kein autorisierter
# Verkaeufer".
#
# Laeuft ohne Zugang zur Produktion (reines curl). Nach jeder Rollout-Stufe
# wiederholen und die Ausgabe nach
# docs/messungen/werbeplaetze-golive/adstxt-sweep-<datum>.txt legen.
#
# Die Domainliste ist der Stand vom 16.10.2026; maßgeblich bleibt die Tabelle
# aus `php artisan ads:golive:check` auf der Produktion.
#
# Aufruf: bash scripts/ads-txt-sweep.sh
#
doms="24 firmenfreund.de
27 bodenlegerfinden.com
28 sanitaerfinden.com
29 fahrschulefinder.de
30 elektrikerportal.com
33 malerfinder.de
37 geruestbauer.gmbh
38 metallbauer.io
43 tierarztportal.com
44 kfzwerkstatt.io
45 findegutachter.de
46 fliesenleger.io
47 mjet.net
49 sanitaerfinder.com
50 apotheke.firmenfreund.de
51 firmenfreund.net
52 unfallarzt.firmenfreund.de
53 zahnarzt.firmenfreund.de
54 klempner.firmenfreund.de
55 energieberaterportal.net
56 arztfinder.firmenfreund.de
57 speditionportal.com
58 schluesseldienstportal.com"
printf "%-4s %-30s %-6s %-7s %-7s %s\n" "ID" "Domain" "HTTP" "Zeilen" "Google" "Publisher-IDs / Hinweis"
while read -r id dom; do
  body=$(curl -sL --max-time 20 -w '\n%{http_code}|%{url_effective}' "https://$dom/ads.txt")
  meta=$(printf '%s' "$body" | tail -1)
  code=${meta%%|*}
  eff=${meta#*|}
  txt=$(printf '%s' "$body" | sed '$d')
  zeilen=$(printf '%s' "$txt" | grep -c '[^[:space:]]')
  goog=$(printf '%s' "$txt" | grep -ci 'google.com')
  pubs=$(printf '%s' "$txt" | grep -oiE 'pub-[0-9]+' | sort -u | tr '\n' ' ')
  hin="$pubs"
  case "$eff" in *"$dom/ads.txt") ;; *) hin="$hin [-> $eff]";; esac
  printf "%-4s %-30s %-6s %-7s %-7s %s\n" "$id" "$dom" "$code" "$zeilen" "$goog" "$hin"
done <<< "$doms"
