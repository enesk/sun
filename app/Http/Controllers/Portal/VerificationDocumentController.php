<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\CompanyVerification;
use App\Services\Premium\CompanyVerificationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert einen Verifizierungsnachweis aus (#11). Route ist signiert und
 * zusaetzlich nur fuer Administratoren; der Disk hat keine oeffentliche URL.
 */
class VerificationDocumentController extends Controller
{
    public function __invoke(Request $request, int $verification, CompanyVerificationService $service): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $record = CompanyVerification::query()->findOrFail($verification);

        abort_unless($record->hasDocument() && $service->disk()->exists($record->document_path), 404);

        $extension = pathinfo($record->document_path, PATHINFO_EXTENSION);

        return $service->disk()->response(
            $record->document_path,
            "nachweis-{$record->company_id}-{$record->id}.{$extension}",
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
            'inline',
        );
    }
}
