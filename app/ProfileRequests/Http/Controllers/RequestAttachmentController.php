<?php

declare(strict_types=1);

namespace App\ProfileRequests\Http\Controllers;

use App\Models\User;
use App\ProfileRequests\Models\RequestAttachment;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Einen Anhang einer Anfrage im Panel ansehen (SUN-RQ, gemeldet von Enes
 * am 22.10.2026: die Nachweise der Übernahmeanträge standen in der Inbox
 * nur als Dateiname, ohne Weg sie zu öffnen).
 *
 * Die Dateien liegen auf der privaten Platte `requests`
 * (storage/app/private/requests/…) und haben keine öffentliche URL — genau
 * so soll es bleiben. Diese Route ist der einzige Weg daran, und sie ist
 * **kein** öffentlicher: angemeldet, Administrator, und der Anhang muss zu
 * der Anfrage gehören, die in der Adresse steht.
 *
 * Nicht unter `/admin`, damit sie nicht mit den Routen des Filament-Panels
 * konkurriert — dasselbe Muster wie die Werbemittel-Vorschau
 * ({@see \App\Ads\Http\Controllers\AdUnitPreviewController}).
 *
 * Nachweise sind PDF oder Bild: die zeigt der Browser im Tab an (`inline`).
 * Alles andere wird heruntergeladen, statt es dem Browser zur Deutung zu
 * überlassen; dazu `nosniff` und eine CSP, die der Datei jedes Nachladen
 * verbietet. Eine fremde Datei, die uns ein Unbekannter geschickt hat,
 * bekommt keine Rechte auf unserer Domain.
 */
class RequestAttachmentController extends Controller
{
    /** Typen, die im Browser gezeigt werden dürfen. */
    private const INLINE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __invoke(int $request, RequestAttachment $attachment): Response|StreamedResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);

        // Die ID aus der Adresse muss zur Anfrage des Anhangs passen: sonst
        // wäre die Anhang-ID allein der Schlüssel zu jedem fremden Upload.
        abort_unless((int) $attachment->request_id === $request, 404);

        $disk = Storage::disk(RequestAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        $mime = (string) ($attachment->mime ?: 'application/octet-stream');
        $inline = in_array($mime, self::INLINE_MIMES, true);

        return $disk->response(
            $attachment->path,
            $attachment->fileName(),
            [
                'Content-Type' => $mime,
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Cache-Control' => 'no-store, private',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }
}
