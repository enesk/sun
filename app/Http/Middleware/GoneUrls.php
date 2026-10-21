<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Companies\Deletion\GoneUrlRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antwortet mit **410 Gone** auf Profil-URLs, die die Loeschpipeline (#8)
 * eingetragen hat.
 *
 * Warum nicht einfach der 404 des Controllers: ein 404 heisst „nicht
 * gefunden" und laesst Google die URL monatelang weiter abfragen; 410 heisst
 * „dauerhaft weg" und fuehrt deutlich schneller zur Entfernung aus dem Index.
 * Fuer einen Loeschantrag nach Art. 17 ist genau das der Punkt.
 *
 * Haengt an den beiden Profilrouten in routes/tenant.php und laeuft damit
 * **nach** dem Tenancy-Initializer der Gruppe — die Pfadliste liegt in der
 * Tenant-DB und im Portal-Cache, beides ohne Tenant nicht erreichbar.
 *
 * Die Pruefung kostet im Normalfall einen Cache-Treffer
 * ({@see GoneUrlRegistry::cachedPaths()}, eine Stunde), keine Abfrage.
 */
class GoneUrls
{
    public function __construct(private readonly GoneUrlRegistry $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if (! $this->registry->has($request->path())) {
            return $next($request);
        }

        // `noindex` zusaetzlich zum Status: Bing und einige Crawler lesen den
        // Header auch bei 4xx, und ein widersprechendes Signal gibt es hier
        // nicht mehr.
        return response()
            ->view('errors.410', [], 410)
            ->header('X-Robots-Tag', 'noindex');
    }
}
