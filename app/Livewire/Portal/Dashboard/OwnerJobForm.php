<?php

namespace App\Livewire\Portal\Dashboard;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\Job;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class OwnerJobForm extends Component
{
    public ?Job $job = null;
    public bool $isEdit = false;

    // Formulardaten
    public string $title = '';
    public string $description = '';
    public string $requirements = '';
    public string $benefits = '';
    public string $employment_type = Job::TYPE_VOLLZEIT;
    public string $location = '';
    public ?int $city_id = null;
    public string $citySearch = '';
    public ?int $salary_min = null;
    public ?int $salary_max = null;
    public string $salary_type = Job::SALARY_MONTHLY;
    public ?string $application_deadline = null;

    // UI State
    public array $citySuggestions = [];
    public bool $showSalary = false;

    public function mount(?Job $job = null): void
    {
        if ($job && $job->exists) {
            $this->job = $job;
            $this->isEdit = true;
            $this->fillFromJob($job);
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:3000'],
            'benefits' => ['nullable', 'string', 'max:3000'],
            'employment_type' => ['required', Rule::in(array_keys(Job::EMPLOYMENT_TYPES))],
            'location' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:999999', 'gte:salary_min'],
            'salary_type' => ['required_with:salary_min,salary_max', Rule::in(array_keys(Job::SALARY_TYPES))],
            'application_deadline' => ['nullable', 'date', 'after:today'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Bitte geben Sie einen Stellentitel ein.',
            'title.min' => 'Der Titel muss mindestens 5 Zeichen lang sein.',
            'description.required' => 'Bitte beschreiben Sie die Stelle.',
            'description.min' => 'Die Beschreibung muss mindestens 50 Zeichen lang sein.',
            'employment_type.required' => 'Bitte wählen Sie die Beschäftigungsart.',
            'salary_max.gte' => 'Das Maximalgehalt muss mindestens so hoch wie das Mindestgehalt sein.',
            'application_deadline.after' => 'Die Bewerbungsfrist muss in der Zukunft liegen.',
        ];
    }

    public function updatedCitySearch(string $value): void
    {
        if (strlen($value) < 2) {
            $this->citySuggestions = [];
            return;
        }

        $this->citySuggestions = City::search($value)
            ->select('id', 'name', 'zipcode')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function selectCity(int $cityId): void
    {
        $city = City::find($cityId);
        if ($city) {
            $this->city_id = $city->id;
            $this->citySearch = "{$city->zipcode} {$city->name}";
            $this->citySuggestions = [];
        }
    }

    public function clearCity(): void
    {
        $this->city_id = null;
        $this->citySearch = '';
        $this->citySuggestions = [];
    }

    public function save(): void
    {
        $this->validate();

        $company = Company::ownedBy(Auth::id())->firstOrFail();

        // Premium-Check
        if (! $company->is_premium) {
            $this->dispatch('toast', type: 'error', message: 'Premium-Abo erforderlich.');
            return;
        }

        $data = [
            'company_id' => $company->id,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements ?: null,
            'benefits' => $this->benefits ?: null,
            'employment_type' => $this->employment_type,
            'location' => $this->location ?: null,
            'city_id' => $this->city_id,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_type' => ($this->salary_min || $this->salary_max) ? $this->salary_type : null,
            'application_deadline' => $this->application_deadline ?: null,
        ];

        if ($this->isEdit) {
            $this->job->update($data);
            $this->dispatch('toast', type: 'success', message: "Stellenanzeige \"{$this->title}\" wurde aktualisiert.");
        } else {
            // Limit-Check
            if (! Job::canCompanyCreateJob($company->id)) {
                $this->dispatch('toast', type: 'error', message: 'Maximale Anzahl aktiver Stellenanzeigen erreicht (' . Job::MAX_ACTIVE_PER_COMPANY . ').');
                return;
            }

            Job::create($data);
            $this->dispatch('toast', type: 'success', message: "Stellenanzeige \"{$this->title}\" wurde veröffentlicht.");
        }

        $this->redirect(route('portal.owner.jobs.index'), navigate: true);
    }

    private function fillFromJob(Job $job): void
    {
        $this->title = $job->title;
        $this->description = $job->description ?? '';
        $this->requirements = $job->requirements ?? '';
        $this->benefits = $job->benefits ?? '';
        $this->employment_type = $job->employment_type;
        $this->location = $job->location ?? '';
        $this->city_id = $job->city_id;
        $this->salary_min = $job->salary_min;
        $this->salary_max = $job->salary_max;
        $this->salary_type = $job->salary_type ?? Job::SALARY_MONTHLY;
        $this->application_deadline = $job->application_deadline?->format('Y-m-d');

        $this->showSalary = ($job->salary_min || $job->salary_max);

        if ($job->city) {
            $this->citySearch = "{$job->city->zipcode} {$job->city->name}";
        }
    }

    public function render()
    {
        return view('livewire.portal.dashboard.owner-job-form', [
            'employmentTypes' => Job::EMPLOYMENT_TYPES,
            'salaryTypes' => Job::SALARY_TYPES,
        ]);
    }
}
