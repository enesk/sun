<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Models\Portal\Job;
use App\Models\Portal\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerJobController extends Controller
{
    private function getCompany(): Company
    {
        return Company::ownedBy(Auth::id())
            ->with(['city'])
            ->firstOrFail();
    }

    /**
     * Stellenanzeigen-Übersicht (aktive + abgelaufene).
     * GET /firmenprofil/stellenanzeigen
     *
     * Soft-Lock: Free-User sehen die locked-View (Premium-Upsell),
     * Premium-User sehen die volle Übersicht.
     * Kein Middleware-Gate hier — bewusst im Controller für unterschiedliche Views.
     */
    public function index()
    {
        $company = $this->getCompany();

        // Soft-Lock: Premium-Upsell für Free-User
        if (! $company->is_premium) {
            return view('pages.dashboard.jobs.locked', compact('company'));
        }

        $activeJobs = Job::forCompany($company->id)
            ->active()
            ->with(['city', 'applications'])
            ->latest('published_at')
            ->get();

        $expiredJobs = Job::forCompany($company->id)
            ->expired()
            ->with(['city', 'applications'])
            ->latest('expires_at')
            ->take(10)
            ->get();

        $canCreate = Job::canCompanyCreateJob($company->id);

        return view('pages.dashboard.jobs.index', compact(
            'company', 'activeJobs', 'expiredJobs', 'canCreate'
        ));
    }

    /**
     * Neue Stellenanzeige erstellen.
     * GET /firmenprofil/stellenanzeigen/erstellen
     *
     * Premium-Gate via EnsurePremiumCompany Middleware auf Route-Ebene.
     * Policy prüft zusätzlich das Job-Limit.
     */
    public function create()
    {
        $company = $this->getCompany();

        $this->authorize('create', Job::class);

        // Limit-Check: Policy gibt false zurück, aber wir wollen eine
        // benutzerfreundliche Redirect-Nachricht statt 403.
        if (! Job::canCompanyCreateJob($company->id)) {
            return redirect()
                ->route('portal.owner.jobs.index')
                ->with('error', 'Sie haben bereits die maximale Anzahl aktiver Stellenanzeigen erreicht (' . Job::MAX_ACTIVE_PER_COMPANY . ').');
        }

        return view('pages.dashboard.jobs.create', compact('company'));
    }

    /**
     * Stellenanzeige bearbeiten.
     * GET /firmenprofil/stellenanzeigen/{id}/bearbeiten
     *
     * Premium-Gate via Middleware. Policy prüft Ownership.
     */
    public function edit(int $id)
    {
        $company = $this->getCompany();

        $job = Job::where('id', $id)
            ->forCompany($company->id)
            ->with(['city'])
            ->firstOrFail();

        $this->authorize('update', $job);

        return view('pages.dashboard.jobs.edit', compact('company', 'job'));
    }

    /**
     * Stellenanzeige aktivieren/deaktivieren.
     * POST /firmenprofil/stellenanzeigen/{id}/toggle
     *
     * Premium-Gate via Middleware. Policy prüft Ownership.
     */
    public function toggle(int $id)
    {
        $company = $this->getCompany();

        $job = Job::where('id', $id)
            ->forCompany($company->id)
            ->firstOrFail();

        $this->authorize('toggle', $job);

        if ($job->is_active) {
            $job->deactivate();
            return back()->with('success', "Stellenanzeige \"{$job->title}\" wurde deaktiviert.");
        }

        // Beim Reaktivieren: Limit prüfen
        if (! Job::canCompanyCreateJob($company->id)) {
            return back()->with('error', 'Maximale Anzahl aktiver Stellenanzeigen erreicht.');
        }

        $job->publish();
        return back()->with('success', "Stellenanzeige \"{$job->title}\" wurde reaktiviert (30 Tage).");
    }

    /**
     * Stellenanzeige löschen.
     * DELETE /firmenprofil/stellenanzeigen/{id}
     *
     * Kein Premium-Gate — User dürfen nach Downgrade aufräumen.
     * Policy prüft nur Ownership.
     */
    public function destroy(int $id)
    {
        $company = $this->getCompany();

        $job = Job::where('id', $id)
            ->forCompany($company->id)
            ->firstOrFail();

        $this->authorize('delete', $job);

        $title = $job->title;

        // Bewerbungen + Media löschen
        foreach ($job->applications as $application) {
            $application->clearMediaCollection('cv');
        }
        $job->applications()->delete();
        $job->delete();

        return redirect()
            ->route('portal.owner.jobs.index')
            ->with('success', "Stellenanzeige \"{$title}\" wurde gelöscht.");
    }

    /**
     * Bewerbungen für eine Stellenanzeige.
     * GET /firmenprofil/stellenanzeigen/{id}/bewerbungen
     *
     * Premium-Gate via Middleware. Policy prüft Ownership.
     */
    public function applications(int $id)
    {
        $company = $this->getCompany();

        $job = Job::where('id', $id)
            ->forCompany($company->id)
            ->firstOrFail();

        $this->authorize('viewApplications', $job);

        $applications = $job->applications()
            ->latest()
            ->paginate(15);

        return view('pages.dashboard.jobs.applications', compact(
            'company', 'job', 'applications'
        ));
    }

    /**
     * Bewerbungsstatus ändern.
     * POST /firmenprofil/stellenanzeigen/{jobId}/bewerbungen/{applicationId}/status
     *
     * Premium-Gate via Middleware. Policy prüft Ownership.
     */
    public function updateApplicationStatus(Request $request, int $jobId, int $applicationId)
    {
        $company = $this->getCompany();

        $job = Job::where('id', $jobId)
            ->forCompany($company->id)
            ->firstOrFail();

        $this->authorize('manageApplications', $job);

        $application = JobApplication::where('id', $applicationId)
            ->forJob($job->id)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:reviewed,contacted,rejected'],
        ]);

        match ($validated['status']) {
            'reviewed' => $application->markReviewed(),
            'contacted' => $application->markContacted(),
            'rejected' => $application->markRejected(),
        };

        return back()->with('success', "Bewerbung als \"{$application->status_label}\" markiert.");
    }
}
