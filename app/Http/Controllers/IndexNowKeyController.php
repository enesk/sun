<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Providers\IndexNowClient;
use App\Models\Tenant;
use Illuminate\Http\Response;

/**
 * Schluesseldatei fuer IndexNow (#21).
 *
 * IndexNow verlangt den Nachweis, dass der meldende Dienst die Domain
 * kontrolliert: unter der im Ping genannten `keyLocation` muss eine Textdatei
 * liegen, die genau den Schluessel enthaelt.
 *
 * Die Datei wird nicht abgelegt, sondern berechnet — der Schluessel ist je
 * Mandant deterministisch (IndexNowClient::key). Ein fremder oder erratener
 * Schluessel bekommt 404, damit unter der Domain nicht beliebige .txt-Pfade
 * antworten.
 */
class IndexNowKeyController extends Controller
{
    public function __invoke(string $key, IndexNowClient $indexNow): Response
    {
        $tenant = tenant();

        abort_if(! $tenant instanceof Tenant, 404);

        $expected = $indexNow->key($tenant);

        abort_unless(hash_equals($expected, $key), 404);

        return response($expected, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
