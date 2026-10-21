# Messungen zum Anfragen-Pilot (SUN-RQ #12)

Hier liegen die Berichte der Pilotwochen. Ein Bericht entsteht mit

```bash
php artisan requests:pilot-report --since=<Starttag> --tenant=30 --tenant=29 --tenant=44 --json \
  > docs/messungen/profile-requests-pilot/woche-1.json
```

Ablauf, Schwellen und die Vergleichsbasis von vorher (Median 203 Stunden):
`docs/profile-requests-golive.md`, Abschnitt 4.

Noch keine Messung abgelegt — der Pilot laeuft auf der Produktion und
braucht das Sammelpostfach (Ticket „Freigabe Enes").
