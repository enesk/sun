<?php

namespace App\Http\Controllers\Portal;

use App\Constants\CompanyEventType;
use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyEvent;
use App\Services\Premium\CompanyStatsRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * POST /stats/beacon (#15): Telefon-, Website- und Anfrage-Klicks aus resources/js/stats-beacon.js.
 * Antwortet immer 204, damit der Browser nichts auswertet; ungueltige Meldungen verfallen still.
 */
class StatsBeaconController extends Controller
{
    public function __invoke(Request $request, CompanyStatsRecorder $recorder): Response
    {
        $validator = validator($request->all(), [
            'company_id' => ['required', 'integer', 'min:1'],
            'event' => ['required', 'string', Rule::in(CompanyEventType::beaconValues())],
            'source' => ['nullable', 'string', Rule::in([CompanyEvent::SOURCE_PROFILE, CompanyEvent::SOURCE_LISTING])],
        ]);

        if ($validator->fails() || $recorder->isBot($request)) {
            return response()->noContent();
        }

        $data = $validator->validated();
        $companyId = (int) $data['company_id'];

        if (! Company::active()->whereKey($companyId)->exists()) {
            return response()->noContent();
        }

        $recorder->click(CompanyEventType::from($data['event']), $companyId, $request, $data['source'] ?? null);

        return response()->noContent();
    }
}
