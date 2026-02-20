<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Company;
use Livewire\Component;

/**
 * Recent companies widget for the dashboard overview.
 *
 * Shows the last 5 registered companies with status info.
 */
class RecentCompanies extends Component
{
    public function render()
    {
        $companies = Company::with('city')
            ->latest()
            ->take(5)
            ->get();

        return view('livewire.verwaltung.recent-companies', compact('companies'));
    }
}
