<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\City;
use App\Models\Portal\Job;
use App\Models\Portal\JobApplication;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PublicJobController extends Controller
{
    public function __construct(
        private readonly TrackingService $trackingService,
    ) {}

    /**
     * GET /jobs — Jobbörse-Übersichtsseite mit Suche, Filter, Paginierung
     */
    public function index(Request $request): View
    {
        $query = Job::active()
            ->published()
            ->with(['company', 'company.media', 'company.city', 'city']);

        // ── Volltextsuche ──
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // ── Filter: Beschäftigungsart ──
        if ($request->filled('type') && array_key_exists($request->type, Job::EMPLOYMENT_TYPES)) {
            $query->ofType($request->type);
        }

        // ── Filter: Stadt ──
        if ($request->filled('city')) {
            $city = City::where('slug', $request->city)
                ->orWhere('name', $request->city)
                ->first();
            if ($city) {
                // Jobs in dieser Stadt ODER Firmen in dieser Stadt (Fallback)
                $query->where(function ($q) use ($city) {
                    $q->where('jobs.city_id', $city->id)
                        ->orWhereHas('company', fn ($cq) => $cq->where('city_id', $city->id));
                });
            }
        }

        // ── Sortierung ──
        // Premium-Firmen-Jobs zuerst (analog zu Companies)
        $sort = $request->get('sort', 'newest');

        $query->leftJoin('companies', 'jobs.company_id', '=', 'companies.id')
            ->select('jobs.*')
            ->orderByDesc('companies.is_premium');

        $query = match ($sort) {
            'az' => $query->orderBy('jobs.title'),
            'salary' => $query->orderByDesc('jobs.salary_max'),
            default => $query->latest('jobs.published_at'), // newest
        };

        // ── Paginierung ──
        $jobs = $query->paginate(15)->withQueryString();

        // ── Sidebar-Daten (gecacht) ──
        $employmentTypes = $this->getEmploymentTypeCounts();

        $cities = Cache::remember('portal.jobs.cities.sidebar', 3600, fn () =>
            City::select('cities.id', 'cities.name', 'cities.slug')
                ->join('jobs', function ($join) {
                    $join->on('cities.id', '=', 'jobs.city_id')
                        ->where('jobs.is_active', true)
                        ->where('jobs.expires_at', '>', now());
                })
                ->groupBy('cities.id', 'cities.name', 'cities.slug')
                ->selectRaw('COUNT(jobs.id) as jobs_count')
                ->orderByDesc('jobs_count')
                ->limit(30)
                ->get()
        );

        // Auch Städte über Company-Beziehung zählen
        $companyCities = Cache::remember('portal.jobs.company_cities.sidebar', 3600, fn () =>
            City::select('cities.id', 'cities.name', 'cities.slug')
                ->join('companies', 'cities.id', '=', 'companies.city_id')
                ->join('jobs', function ($join) {
                    $join->on('companies.id', '=', 'jobs.company_id')
                        ->where('jobs.is_active', true)
                        ->where('jobs.expires_at', '>', now())
                        ->whereNull('jobs.city_id');
                })
                ->groupBy('cities.id', 'cities.name', 'cities.slug')
                ->selectRaw('COUNT(jobs.id) as jobs_count')
                ->orderByDesc('jobs_count')
                ->limit(30)
                ->get()
        );

        // Merge und Deduplizierung
        $allCities = $cities->merge($companyCities)
            ->groupBy('id')
            ->map(function ($group) {
                $first = $group->first();
                $first->jobs_count = $group->sum('jobs_count');
                return $first;
            })
            ->sortByDesc('jobs_count')
            ->values()
            ->take(30);

        $totalJobs = Cache::remember('portal.jobs.total', 900, fn () =>
            Job::active()->published()->count()
        );

        // ── Job-Suchimpressions tracken ──
        $jobCompanyPairs = $jobs->getCollection()->map(fn ($job) => [
            'job_id' => $job->id,
            'company_id' => $job->company_id,
        ])->toArray();

        if (! empty($jobCompanyPairs)) {
            $this->trackingService->trackJobSearchImpressions(
                $jobCompanyPairs,
                $request->input('q'),
                $request,
            );
        }

        return view('pages.jobs.index', [
            'jobs' => $jobs,
            'employmentTypes' => $employmentTypes,
            'cities' => $allCities,
            'totalJobs' => $totalJobs,
            'sort' => $sort,
        ]);
    }

    /**
     * GET /jobs/{slug} — Job-Detailseite
     */
    public function show(string $slug): View
    {
        $job = Job::where('slug', $slug)
            ->with([
                'company',
                'company.media',
                'company.city',
                'company.categories',
                'company.openingHours',
                'city',
            ])
            ->firstOrFail();

        // Auch abgelaufene Jobs anzeigen — mit Banner
        if (! $job->company || ! $job->company->is_active) {
            abort(404);
        }

        // View-Counter inkrementieren (einmal pro Session) + Tracking-Event
        $sessionKey = 'job_viewed_' . $job->id;
        if (! session()->has($sessionKey)) {
            $job->incrementViewsCount();
            session()->put($sessionKey, true);
        }

        // Detailliertes Tracking (jeden Aufruf, mit Referrer, Bot-Filter, DSGVO-IP)
        $this->trackingService->trackJobPageView($job->id, $job->company_id, request());

        // Weitere Jobs derselben Firma
        $relatedJobs = Job::active()
            ->published()
            ->forCompany($job->company_id)
            ->where('id', '!=', $job->id)
            ->with(['city'])
            ->limit(3)
            ->latest('published_at')
            ->get();

        // Ähnliche Jobs (gleiche Beschäftigungsart + Stadt/Region)
        $similarJobs = collect();
        if ($relatedJobs->count() < 3) {
            $similarJobs = Job::active()
                ->published()
                ->where('jobs.id', '!=', $job->id)
                ->where('jobs.company_id', '!=', $job->company_id)
                ->ofType($job->employment_type)
                ->with(['company', 'company.media', 'city'])
                ->limit(3)
                ->latest('jobs.published_at')
                ->get();
        }

        // Breadcrumb
        $breadcrumb = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Stellenanzeigen', 'url' => route('portal.jobs.index')],
            ['label' => $job->title],
        ];

        return view('pages.jobs.show', compact(
            'job',
            'relatedJobs',
            'similarJobs',
            'breadcrumb',
        ));
    }

    /**
     * POST /jobs/{slug}/bewerben — In-App Bewerbung absenden
     */
    public function apply(Request $request, string $slug): RedirectResponse
    {
        $job = Job::where('slug', $slug)
            ->active()
            ->published()
            ->firstOrFail();

        // Rate Limiting: max 5 Bewerbungen pro IP pro Stunde
        $rateLimitKey = 'job-apply:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return back()
                ->with('error', 'Sie haben zu viele Bewerbungen abgeschickt. Bitte versuchen Sie es später erneut.')
                ->withInput();
        }
        RateLimiter::hit($rateLimitKey, 3600);

        // Duplikat-Check: gleiche E-Mail für gleichen Job
        $existing = JobApplication::where('job_id', $job->id)
            ->where('applicant_email', $request->email)
            ->exists();

        if ($existing) {
            return back()
                ->with('error', 'Sie haben sich bereits auf diese Stelle beworben.')
                ->withInput();
        }

        // Validierung
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|min:20|max:5000',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // max 10MB
        ], [
            'name.required' => 'Bitte geben Sie Ihren Namen an.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'message.required' => 'Bitte schreiben Sie eine kurze Nachricht.',
            'message.min' => 'Die Nachricht sollte mindestens 20 Zeichen lang sein.',
            'cv.mimes' => 'Der Lebenslauf muss eine PDF-, DOC- oder DOCX-Datei sein.',
            'cv.max' => 'Der Lebenslauf darf maximal 10 MB groß sein.',
        ]);

        // Bewerbung erstellen
        $application = JobApplication::create([
            'job_id' => $job->id,
            'applicant_name' => $validated['name'],
            'applicant_email' => $validated['email'],
            'applicant_phone' => $validated['phone'] ?? null,
            'message' => $validated['message'],
            'status' => JobApplication::STATUS_PENDING,
            'ip_address' => $request->ip(),
        ]);

        // CV-Upload (Spatie Media Library)
        if ($request->hasFile('cv')) {
            $application->addMediaFromRequest('cv')
                ->toMediaCollection('cv');
        }

        // Bewerbungszähler inkrementieren
        $job->incrementApplicationsCount();

        return redirect()
            ->route('portal.jobs.show', $slug)
            ->with('application_success', true);
    }

    /**
     * Beschäftigungsart-Counts für Sidebar-Facetten
     */
    private function getEmploymentTypeCounts(): array
    {
        return Cache::remember('portal.jobs.types.counts', 900, function () {
            $counts = Job::active()
                ->published()
                ->select('employment_type', DB::raw('COUNT(*) as count'))
                ->groupBy('employment_type')
                ->pluck('count', 'employment_type')
                ->toArray();

            $result = [];
            foreach (Job::EMPLOYMENT_TYPES as $slug => $label) {
                $result[$slug] = [
                    'label' => $label,
                    'count' => $counts[$slug] ?? 0,
                ];
            }

            return $result;
        });
    }
}
