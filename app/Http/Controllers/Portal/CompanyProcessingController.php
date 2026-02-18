<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyProcessingController extends Controller
{
    /**
     * Nächstes unverarbeitetes Unternehmen holen.
     *
     * Performance: Statt ORDER BY RAND() (Full Table Scan + Filesort)
     * nutzen wir COUNT + random OFFSET. Zwei Queries, aber beide nutzen
     * den idx_companies_bot_queue Index.
     */
    public function getNext(): JsonResponse
    {
        $baseQuery = Company::whereNull('google_added_at')
            ->whereNull('website');

        $count = $baseQuery->count();

        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Keine Unternehmen zu verarbeiten',
                'remaining' => 0,
            ], 404);
        }

        $company = (clone $baseQuery)
            ->select(['id', 'name', 'slug', 'city_id'])
            ->with('city:id,name')
            ->offset(random_int(0, $count - 1))
            ->limit(1)
            ->first();

        $cityName = $company->city?->name ?? '';

        return response()->json([
            'success' => true,
            'remaining' => $count - 1,
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'url' => url("/{$company->url_slug}"),
                'google_link' => 'https://www.google.com/search?' . http_build_query([
                    'q' => "{$company->name} {$cityName}",
                ]) . '#irp=we',
            ],
        ]);
    }

    /**
     * Batch: Mehrere Unternehmen auf einmal holen.
     * Für Bots die einen Vorrat brauchen (z.B. 10 Stück vorladen).
     */
    public function getBatch(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 10), 50);

        $companies = Company::whereNull('google_added_at')
            ->whereNull('website')
            ->select(['id', 'name', 'slug', 'city_id'])
            ->with('city:id,name')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        if ($companies->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Keine Unternehmen zu verarbeiten',
                'remaining' => 0,
            ], 404);
        }

        $remaining = Company::whereNull('google_added_at')
            ->whereNull('website')
            ->count() - $companies->count();

        return response()->json([
            'success' => true,
            'remaining' => max(0, $remaining),
            'data' => $companies->map(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'url' => url("/{$c->url_slug}"),
                'google_link' => 'https://www.google.com/search?' . http_build_query([
                    'q' => "{$c->name} " . ($c->city?->name ?? ''),
                ]) . '#irp=we',
            ]),
        ]);
    }

    /**
     * Unternehmen als verarbeitet markieren.
     * Direktes UPDATE ohne vorheriges SELECT (1 Query statt 2).
     */
    public function markComplete(int $id): JsonResponse
    {
        $affected = Company::where('id', $id)
            ->whereNull('google_added_at')
            ->update(['google_added_at' => now()]);

        if ($affected === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unternehmen nicht gefunden oder bereits verarbeitet',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Unternehmen erfolgreich markiert',
        ]);
    }

    /**
     * Batch-Complete: Mehrere IDs auf einmal als verarbeitet markieren.
     */
    public function markBatchComplete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|max:50',
            'ids.*' => 'integer',
        ]);

        $affected = Company::whereIn('id', $request->input('ids'))
            ->whereNull('google_added_at')
            ->update(['google_added_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "{$affected} Unternehmen markiert",
            'affected' => $affected,
        ]);
    }

    /**
     * Unternehmen als fehlgeschlagen markieren (überspringen).
     * Setzt google_added_at damit es nicht erneut im Queue auftaucht.
     */
    public function markFailed(int $id, Request $request): JsonResponse
    {
        $affected = Company::where('id', $id)
            ->whereNull('google_added_at')
            ->update([
                'google_added_at' => now(),
                // website bleibt NULL → erkennbar als "verarbeitet aber kein Ergebnis"
            ]);

        if ($affected === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unternehmen nicht gefunden oder bereits verarbeitet',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Als fehlgeschlagen markiert',
        ]);
    }

    /**
     * Statistiken zur Verarbeitung.
     */
    public function getStats(): JsonResponse
    {
        // Ein einziger Query mit conditional aggregation statt 3 separate Queries
        $stats = Company::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN google_added_at IS NOT NULL THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN google_added_at IS NULL AND website IS NULL THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN google_added_at IS NOT NULL AND website IS NOT NULL THEN 1 ELSE 0 END) as with_website,
            SUM(CASE WHEN google_added_at IS NOT NULL AND website IS NULL THEN 1 ELSE 0 END) as failed
        ")->first();

        $total = (int) $stats->total;
        $processed = (int) $stats->processed;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'processed' => $processed,
                'pending' => (int) $stats->pending,
                'with_website' => (int) $stats->with_website,
                'failed' => (int) $stats->failed,
                'progress_percent' => $total > 0
                    ? round(($processed / $total) * 100, 1)
                    : 0,
            ],
        ]);
    }
}
