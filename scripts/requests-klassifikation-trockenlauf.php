<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trockenlauf der KI-Klassifikation und Verifikation (SUN-RQ, Ticket #6)
|--------------------------------------------------------------------------
|
| Spielt fuenf anonymisierte Beispielmails plus den Sonderfall „Profil ohne
| Firmen-E-Mail" durch den echten Weg: ClassifyRequest -> RequestClassifier
| (Anthropic) -> request_changes -> RequestVerifier. Ersetzt wird nur der
| Mailversand (Mail::fake()); der KI-Aufruf ist echt, denn genau der soll
| geprueft werden.
|
| Aufruf:
|   php artisan tinker --execute='require "scripts/requests-klassifikation-trockenlauf.php";'
|
| Voraussetzungen: ANTHROPIC_API_KEY in der .env und ein lokaler Tenant mit
| Betrieben (REQUESTS_TROCKENLAUF_TENANT setzt das Portal, Vorgabe: der
| erste mit Datenbank).
|
| Der Lauf raeumt hinterher auf. Mit REQUESTS_TROCKENLAUF_KEEP=1 bleiben die
| Anfragen stehen, um sie im Panel anzusehen.
|
| Achtung beim Lesen der Ergebnisse: im lokalen Spiegel sind alle
| Firmenadressen auf firma<id>@example.test und alle Rufnummern auf einen
| Platzhalter anonymisiert. Die Firmensuche ueber Telefon ist hier deshalb
| nicht aussagekraeftig — auf der Produktion ist sie der zweitstaerkste
| Treffer.
|
*/

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Jobs\ClassifyRequest;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Support\Facades\Mail;

$keep = (bool) env('REQUESTS_TROCKENLAUF_KEEP', false);

if ((string) config('ai.providers.anthropic.api_key') === '') {
    echo "ANTHROPIC_API_KEY fehlt — der Lauf wuerde nur den Fallback zeigen.\n";

    return;
}

$tenantId = (int) env('REQUESTS_TROCKENLAUF_TENANT', 0);
$tenant = null;

foreach (Tenant::query()->whereNotNull('domain')->orderBy('id')->get() as $kandidat) {
    if ($tenantId !== 0 && (int) $kandidat->getKey() !== $tenantId) {
        continue;
    }

    try {
        $treffer = $kandidat->run(fn (): bool => Company::query()
            ->whereNotNull('email')->where('email', '!=', '')
            ->whereNotNull('tel')->where('tel', '!=', '')
            ->exists());
    } catch (Throwable) {
        continue;
    }

    if ($treffer) {
        $tenant = $kandidat;

        break;
    }
}

if ($tenant === null) {
    echo "Kein lokaler Tenant mit Betrieben gefunden — Trockenlauf nicht moeglich.\n";

    return;
}

/** @var array{mit: Company, ohne: Company|null} $betriebe */
$betriebe = $tenant->run(fn (): array => [
    'mit' => Company::query()
        ->whereNotNull('email')->where('email', '!=', '')
        ->whereNull('user_id')
        ->with('city')
        ->orderBy('id')
        ->firstOrFail(),
    'ohne' => Company::query()
        ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
        ->with('city')
        ->orderBy('id')
        ->first(),
]);

$firma = $betriebe['mit'];
$firmenMail = (string) $firma->email;
$firmenDomain = substr($firmenMail, strrpos($firmenMail, '@') + 1);
$ort = (string) ($firma->city->name ?? '');

echo "Portal: {$tenant->domain} (tenant {$tenant->id})\n";
echo "Betrieb mit E-Mail: #{$firma->id} {$firma->name}, {$ort} <{$firmenMail}>\n";
echo 'Betrieb ohne E-Mail: '.($betriebe['ohne'] === null ? '— (Sonderfall wird uebersprungen)' : "#{$betriebe['ohne']->id} {$betriebe['ohne']->name}")."\n\n";

/*
 * Die Beispielmails. Wortlaut und Aufbau sind der Stichprobe aus
 * docs/profile-requests.md 2 nachgebildet (Freitext, keine Felder, Signatur
 * am Ende); Namen, Adressen und Rufnummern sind erfunden.
 */
$faelle = [
    [
        'titel' => '1) Telefonnummer geaendert, Absender = Firmenadresse',
        'company' => $firma,
        'from' => $firmenMail,
        'name' => 'Andrea Hoffmann',
        'text' => "Guten Tag,\n\nwir haben eine neue Telefonnummer, unsere neue Nummer ist 0721 4471290. "
            ."Die alte Nummer (0721 338812) ist abgeschaltet. Bitte aendern Sie das in unserem Eintrag.\n\n"
            ."Mit freundlichen Gruessen\n{$firma->name}\nAndrea Hoffmann\n{$ort}",
        'erwartet' => 'change, request_change tel, verified ueber sender_domain',
    ],
    [
        'titel' => '2) Oeffnungszeiten und Website, Absender privat (Freemail)',
        'company' => $firma,
        'from' => 'a.hoffmann1974@gmail.com',
        'name' => 'Andrea Hoffmann',
        'text' => "Hallo,\n\nunsere Oeffnungszeiten stimmen nicht mehr. Wir haben jetzt montags bis freitags "
            .'von 7:30 bis 16:30 Uhr geoeffnet, samstags geschlossen. Ausserdem haben wir eine neue Internetseite: '
            ."www.hoffmann-haustechnik-beispiel.de\n\nViele Gruesse\n{$firma->name}\n{$ort}",
        'erwartet' => 'change, request_changes opening_hours + website, Magic-Link (Freemail zaehlt nicht)',
    ],
    [
        'titel' => '3) Loeschwunsch, Absender = Firmendomain',
        'company' => $firma,
        'from' => 'buchhaltung@'.$firmenDomain,
        'name' => 'Petra Kessler',
        'text' => "Sehr geehrte Damen und Herren,\n\nwir haben den Betrieb zum 30.09. aufgegeben und moechten, "
            .'dass unser Eintrag vollstaendig geloescht wird, einschliesslich der Bilder und Bewertungen. '
            ."Bitte bestaetigen Sie uns die Loeschung.\n\nMit freundlichen Gruessen\nPetra Kessler\n{$firma->name}",
        'erwartet' => 'delete mit Grund, verified ueber sender_domain',
    ],
    [
        'titel' => '4) Uebernahmeantrag, Absender privat',
        'company' => $firma,
        'from' => 'inhaber.kessler@web.de',
        'name' => 'Thomas Kessler',
        'text' => "Guten Tag,\n\nich bin der Inhaber von {$firma->name} in {$ort} und habe gesehen, dass unser "
            .'Betrieb bei Ihnen eingetragen ist. Ich moechte den Eintrag selbst verwalten und brauche einen Zugang. '
            ."Wie bekomme ich den?\n\nBeste Gruesse\nThomas Kessler",
        'erwartet' => 'claim, Magic-Link; nach Klick laeuft die Claim-Automatik',
    ],
    [
        'titel' => '5) Rueckfrage ohne Aenderungswunsch',
        'company' => null,
        'from' => 'marketing@agentur-beispiel.de',
        'name' => 'Sven Baumgart',
        'text' => "Hallo,\n\nwir betreuen mehrere Handwerksbetriebe im Marketing und wuerden gern wissen, "
            ."was ein Premium-Eintrag bei Ihnen kostet und ob es Staffelpreise gibt.\n\n"
            ."Freundliche Gruesse\nSven Baumgart\nAgentur Beispiel",
        'erwartet' => 'other, keine Zuordnung, Handarbeit',
    ],
];

$faelle[] = [
    'titel' => '6) Aenderung mit Firmenadresse im Text, Absender privat',
    'company' => $firma,
    'from' => 'thomas.kessler42@gmail.com',
    'name' => 'Thomas Kessler',
    'text' => "Guten Tag,\n\nbitte tragen Sie bei unserem Eintrag die E-Mail-Adresse {$firmenMail} ein und "
        ."aendern Sie die Telefonnummer auf 0341 2290841. Es geht um {$firma->name} in {$ort}.\n\n"
        ."Danke und viele Gruesse\nThomas Kessler",
    'erwartet' => 'change, Betrieb ueber die Adresse im Text gefunden, Magic-Link an die Firmen-E-Mail',
];

if ($betriebe['ohne'] instanceof Company) {
    $faelle[] = [
        'titel' => '7) Sonderfall: Betrieb ohne hinterlegte Firmen-E-Mail',
        'company' => $betriebe['ohne'],
        'from' => 'kontakt@gmx.de',
        'name' => 'Jens Wiemer',
        'text' => "Guten Tag,\n\nbitte tragen Sie bei uns die Telefonnummer 0341 2290841 ein, im Moment steht "
            ."dort nichts.\n\nMit freundlichen Gruessen\n{$betriebe['ohne']->name}",
        'erwartet' => 'awaiting_verification mit Kennzeichen manual (kein Weg zur Bestaetigung)',
    ];
}

Mail::fake();

$angelegt = [];

foreach ($faelle as $fall) {
    $request = ProfileRequest::create([
        'tenant_id' => $tenant->getKey(),
        // Betrieb bleibt leer: genau das soll die Klassifikation finden.
        'type' => RequestType::OTHER,
        'source' => RequestSource::EMAIL,
        'requester_email' => $fall['from'],
        'requester_name' => $fall['name'],
        'message' => $fall['text'],
    ]);

    $angelegt[] = $request;

    $start = microtime(true);
    ClassifyRequest::dispatchSync((int) $request->getKey());
    $dauer = round((microtime(true) - $start) * 1000);

    $request->refresh();
    $kiEreignis = $request->events()->where('event', RequestEventType::AI_SUGGESTED->value)->latest('id')->first();
    $payload = (array) ($kiEreignis->payload ?? []);

    echo $fall['titel']."\n";
    echo "   Erwartet:      {$fall['erwartet']}\n";
    echo '   Betrieb (ist): '.($fall['company'] === null ? '—' : '#'.$fall['company']->id)."\n";
    echo '   Ergebnis:      Typ '.($request->type?->value ?? '?')
        .' | Status '.$request->status->value
        .' | Portal '.($request->tenant_id ?? 'offen')
        .' | company_id '.($request->company_id ?? 'offen')
        .' | confidence '.($request->ai_confidence ?? '—')
        .' | Weg '.($request->verification_method?->value ?? '—')
        ."\n";
    echo '   Kandidaten:    '.count((array) ($payload['candidates'] ?? []))
        .' (Vorschlag '.($payload['company_id_vorschlag'] ?? '—')
        .', uebernommen '.(($payload['company_id_uebernommen'] ?? false) ? 'ja' : 'nein').")\n";

    foreach ($request->changes as $change) {
        $neu = is_scalar($change->new_value) ? (string) $change->new_value : json_encode($change->new_value, JSON_UNESCAPED_UNICODE);
        $alt = is_scalar($change->old_value) ? (string) $change->old_value : json_encode($change->old_value, JSON_UNESCAPED_UNICODE);
        echo "   Diff:          {$change->field}: ".\Illuminate\Support\Str::limit((string) $alt, 40)
            .' -> '.\Illuminate\Support\Str::limit((string) $neu, 60)
            .' | accepted '.var_export($change->accepted, true)."\n";
    }

    echo '   Zusammenfassung: '.\Illuminate\Support\Str::limit((string) $request->ai_summary, 160)."\n";
    echo '   Antwortentwurf:  '.($request->ai_reply_draft === null ? '—' : \Illuminate\Support\Str::limit(str_replace("\n", ' ', $request->ai_reply_draft), 160))."\n";
    echo '   Ereignisse:      '.$request->events()->get()->map(fn ($e): string => $e->event->value)->implode(', ')."\n";
    echo "   Dauer:           {$dauer} ms\n\n";
}

$mails = Mail::queued(App\ProfileRequests\Mail\VerifyRequestMail::class)
    ->merge(Mail::sent(App\ProfileRequests\Mail\VerifyRequestMail::class));

echo 'Bestaetigungsmails (Mail::fake): '.$mails->count()."\n";

foreach ($mails as $mail) {
    echo '   REQ-'.$mail->request->id.' an '.implode(', ', array_map(fn (array $a): string => (string) $a['address'], $mail->to))
        .' | Link auf '.parse_url($mail->verifyUrl, PHP_URL_HOST)."\n";
}

if ($keep) {
    echo "\nREQUESTS_TROCKENLAUF_KEEP=1 — die Anfragen bleiben stehen: "
        .implode(', ', array_map(fn (ProfileRequest $r): string => '#'.$r->id, $angelegt))."\n";

    return;
}

foreach ($angelegt as $request) {
    $request->events()->delete();
    $request->changes()->delete();
    $request->attachments()->delete();
    ProfileRequest::query()->whereKey($request->getKey())->delete();
}

echo "\nAufgeraeumt: ".count($angelegt)." Anfragen samt Diff und Protokoll geloescht.\n";
