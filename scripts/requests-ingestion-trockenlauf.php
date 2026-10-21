<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trockenlauf der E-Mail-Ingestion (SUN-RQ, Ticket #5)
|--------------------------------------------------------------------------
|
| Spielt die vier Faelle der Abnahme ohne Postfach durch: neue Mail,
| Thread-Antwort, Auto-Reply und Duplikat — dazu einen zu grossen Anhang.
| Dafuer wird nur der IMAP-Zugriff ersetzt (ein Fetcher, der die Mails aus
| Strings statt aus dem Postfach liefert); alles dahinter ist der echte
| Command `requests:fetch-mail`.
|
| Aufruf:
|   php artisan tinker --execute='require "scripts/requests-ingestion-trockenlauf.php";'
|
| Der Lauf raeumt hinterher auf: angelegte Anfragen, Protokollzeilen und
| Dateien werden geloescht. Mit REQUESTS_TROCKENLAUF_KEEP=1 bleiben sie
| stehen, um sie im Panel anzusehen.
|
| Gegen ein echtes Postfach zu pruefen ist Sache der Staging-Abnahme; dieses
| Skript spricht keinen fremden Dienst an.
|
*/

use App\Models\Tenant;
use App\ProfileRequests\Ingestion\ImapMailFetcher;
use App\ProfileRequests\Ingestion\MailOutcome;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestMailLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Webklex\PHPIMAP\Message;

$keep = (bool) env('REQUESTS_TROCKENLAUF_KEEP', false);

$tenant = Tenant::query()->whereNotNull('domain')->orderBy('id')->first();

if ($tenant === null) {
    echo "Kein Tenant mit Domain vorhanden — Trockenlauf nicht moeglich.\n";

    return;
}

$portalDomain = (string) $tenant->domain;
$portalAdresse = "info@{$portalDomain}";
$sammel = (string) (config('profile-requests.mailbox.address') ?: 'anfragen@sanitaerfinden.com');
$lauf = substr(md5((string) microtime(true)), 0, 8);

/** Rohmail bauen. */
$eml = static function (array $header, string $body): string {
    $zeilen = [];

    foreach ($header as $name => $wert) {
        $zeilen[] = "{$name}: {$wert}";
    }

    return implode("\r\n", $zeilen)."\r\n\r\n".$body;
};

$grundHeader = static fn (string $id, string $betreff, array $extra = []): array => array_merge([
    'Return-Path' => '<chef@beispielbetrieb.de>',
    'Delivered-To' => $portalAdresse,
    'To' => $sammel,
    'From' => 'Max Beispiel <chef@beispielbetrieb.de>',
    'Subject' => $betreff,
    'Message-ID' => "<{$id}>",
    'Date' => now()->toRfc2822String(),
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
], $extra);

// 1. Neue Anfrage, mit einem zu grossen Anhang.
$grenze = (int) config('profile-requests.max_attachment_mb', 10) * 1024 * 1024;
$grosserAnhang = chunk_split(base64_encode(str_repeat('X', $grenze + 1024)), 76, "\r\n");

$neueMail = $eml(
    $grundHeader("neu-{$lauf}@beispielbetrieb.de", 'Telefonnummer bitte aendern', [
        'Content-Type' => 'multipart/mixed; boundary="GRENZE"',
    ]),
    "--GRENZE\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
    ."Guten Tag, unsere neue Telefonnummer lautet 0221 1234567. Bitte aendern.\r\n\r\n"
    ."--GRENZE\r\nContent-Type: application/pdf\r\nContent-Transfer-Encoding: base64\r\n"
    ."Content-Disposition: attachment; filename=\"gross.pdf\"\r\n\r\n{$grosserAnhang}\r\n"
    ."--GRENZE--\r\n",
);

// 2. Auto-Reply nach RFC 3834.
$autoMail = $eml(
    $grundHeader("auto-{$lauf}@beispielbetrieb.de", 'Automatische Antwort: Ihre Anfrage', [
        'Auto-Submitted' => 'auto-replied',
    ]),
    "Ich bin bis Montag nicht im Haus.\r\n",
);

$fetcher = new class extends ImapMailFetcher
{
    /** @var list<string> */
    public array $rohmails = [];

    /** @var list<string> */
    public array $abgeschlossen = [];

    public function unread(?int $limit = null): array
    {
        return array_map(static fn (string $roh): Message => Message::fromString($roh), $this->rohmails);
    }

    public function finish(Message $message): void
    {
        $this->abgeschlossen[] = (string) $message->getSubject();
    }

    public function disconnect(): void {}
};

app()->instance(ImapMailFetcher::class, $fetcher);

$vorher = ProfileRequest::query()->max('id') ?? 0;

// --- Durchlauf 1: neue Mail + Auto-Reply ------------------------------
$fetcher->rohmails = [$neueMail, $autoMail];
Artisan::call('requests:fetch-mail');
echo trim(Artisan::output())."\n";

$neu = ProfileRequest::query()->where('id', '>', $vorher)->orderBy('id')->get();

echo "\n1) Neue Mail\n";
foreach ($neu as $request) {
    echo '   Anfrage #'.$request->id
        .' | Quelle '.$request->source->value
        .' | Status '.$request->status->value
        .' | Portal '.($request->tenant_id ?? 'offen')." (erwartet {$tenant->id})"
        .' | Absender '.$request->requester_email."\n";
    echo '   Rohmail: '.($request->raw_email_path ?? '—')
        .' ('.(Storage::disk('local')->exists((string) $request->raw_email_path) ? 'vorhanden' : 'FEHLT').")\n";
    echo '   Ereignisse: '.$request->events()->get()->map(static fn ($e): string => $e->event->value)->implode(', ')."\n";
    echo '   Anhaenge gespeichert: '.$request->attachments()->where('is_raw_mail', false)->count()."\n";
}

$verworfen = RequestMailLog::query()->where('outcome', MailOutcome::DISCARDED)->latest('id')->first();
echo "\n2) Auto-Reply\n   Protokoll: ".($verworfen?->outcome->value ?? '—')
    .' | Grund '.($verworfen?->reason ?? '—')
    .' | Anfragen daraus: '.ProfileRequest::query()->where('id', '>', $vorher)->count()." (erwartet 1)\n";

// --- Durchlauf 2: Duplikat derselben Mail -----------------------------
$fetcher->rohmails = [$neueMail];
Artisan::call('requests:fetch-mail');
echo "\n3) Duplikat\n   ".trim(Artisan::output())."\n";
echo '   Anfragen insgesamt aus diesem Lauf: '.ProfileRequest::query()->where('id', '>', $vorher)->count()." (erwartet 1)\n";

// --- Durchlauf 3: Antwort im Thread -----------------------------------
$erste = $neu->first();

if ($erste !== null) {
    $antwort = $eml(
        $grundHeader("antwort-{$lauf}@beispielbetrieb.de", "Re: [REQ-{$erste->id}] Ihre Anfrage", [
            'In-Reply-To' => "<req-{$erste->id}-abc123@{$portalDomain}>",
            'References' => "<req-{$erste->id}-abc123@{$portalDomain}>",
        ]),
        "Ja, bitte so uebernehmen. Danke.\r\n",
    );

    $fetcher->rohmails = [$antwort];
    Artisan::call('requests:fetch-mail');
    echo "\n4) Thread-Antwort\n   ".trim(Artisan::output())."\n";
    echo '   Anfragen insgesamt: '.ProfileRequest::query()->where('id', '>', $vorher)->count()." (erwartet 1)\n";
    echo '   Ereignisse an #'.$erste->id.': '.$erste->events()->get()->map(static fn ($e): string => $e->event->value)->implode(', ')."\n";
}

// --- Aufraeumen --------------------------------------------------------
if ($keep) {
    echo "\nDaten bleiben stehen (REQUESTS_TROCKENLAUF_KEEP=1).\n";

    return;
}

foreach (ProfileRequest::query()->where('id', '>', $vorher)->get() as $request) {
    Storage::disk('local')->deleteDirectory('requests/'.$request->id);
    $request->events()->delete();
    $request->attachments()->delete();
    RequestMailLog::query()->where('request_id', $request->id)->delete();
    $request->delete();
}

RequestMailLog::query()->where('message_id', 'like', "%{$lauf}@beispielbetrieb.de")->delete();

echo "\nAufgeraeumt.\n";
