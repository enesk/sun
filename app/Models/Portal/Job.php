<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Job extends Model
{
    use HasFactory, TenantConnection;

    // ── Beschäftigungsarten ──

    public const TYPE_VOLLZEIT = 'vollzeit';
    public const TYPE_TEILZEIT = 'teilzeit';
    public const TYPE_MINIJOB = 'minijob';
    public const TYPE_AUSBILDUNG = 'ausbildung';
    public const TYPE_PRAKTIKUM = 'praktikum';

    public const EMPLOYMENT_TYPES = [
        self::TYPE_VOLLZEIT => 'Vollzeit',
        self::TYPE_TEILZEIT => 'Teilzeit',
        self::TYPE_MINIJOB => 'Minijob',
        self::TYPE_AUSBILDUNG => 'Ausbildung',
        self::TYPE_PRAKTIKUM => 'Praktikum',
    ];

    // ── Gehaltstypen ──

    public const SALARY_MONTHLY = 'monthly';
    public const SALARY_HOURLY = 'hourly';
    public const SALARY_YEARLY = 'yearly';

    public const SALARY_TYPES = [
        self::SALARY_MONTHLY => 'pro Monat',
        self::SALARY_HOURLY => 'pro Stunde',
        self::SALARY_YEARLY => 'pro Jahr',
    ];

    // ── Auto-Expire Dauer ──

    public const EXPIRES_AFTER_DAYS = 30;

    // ── Limit pro Firma ──

    public const MAX_ACTIVE_PER_COMPANY = 1;

    protected $fillable = [
        'company_id',
        'title',
        'slug',
        'description',
        'requirements',
        'benefits',
        'employment_type',
        'location',
        'city_id',
        'salary_min',
        'salary_max',
        'salary_type',
        'application_deadline',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'salary_min' => 'integer',
        'salary_max' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'application_deadline' => 'date',
        'views_count' => 'integer',
        'applications_count' => 'integer',
    ];

    // ── Boot ──

    protected static function booted(): void
    {
        static::creating(function (Job $job) {
            if (empty($job->slug)) {
                $job->slug = Str::slug($job->title) . '-' . Str::random(6);
            }

            // Auto-Publish: Jobs gehen sofort live (Auto-Approve)
            if (is_null($job->published_at)) {
                $job->published_at = now();
            }

            // Auto-Expire: 30 Tage nach Veröffentlichung
            if (is_null($job->expires_at)) {
                $job->expires_at = $job->published_at->copy()->addDays(self::EXPIRES_AFTER_DAYS);
            }
        });
    }

    // ── Relationships ──

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('jobs.is_active', true)
            ->where('jobs.expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('jobs.expires_at', '<=', now())
                ->orWhere('jobs.is_active', false);
        });
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('jobs.company_id', $companyId);
    }

    public function scopeOfType($query, string $employmentType)
    {
        return $query->where('jobs.employment_type', $employmentType);
    }

    public function scopeInCity($query, int $cityId)
    {
        return $query->where('jobs.city_id', $cityId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereFullText(['jobs.title', 'jobs.description'], $term);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('jobs.published_at')
            ->where('jobs.published_at', '<=', now());
    }

    // ── Accessors ──

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->is_active && ! $this->is_expired;
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->is_expired) {
            return 0;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }

    public function getEmploymentTypeLabelAttribute(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? $this->employment_type;
    }

    public function getSalaryTypeLabelAttribute(): ?string
    {
        if (! $this->salary_type) {
            return null;
        }

        return self::SALARY_TYPES[$this->salary_type] ?? $this->salary_type;
    }

    public function getSalaryDisplayAttribute(): ?string
    {
        if (! $this->salary_min && ! $this->salary_max) {
            return null;
        }

        $label = $this->salary_type_label ?? '';

        if ($this->salary_min && $this->salary_max) {
            return number_format($this->salary_min, 0, ',', '.') . ' – ' .
                number_format($this->salary_max, 0, ',', '.') . ' EUR ' . $label;
        }

        if ($this->salary_min) {
            return 'ab ' . number_format($this->salary_min, 0, ',', '.') . ' EUR ' . $label;
        }

        return 'bis ' . number_format($this->salary_max, 0, ',', '.') . ' EUR ' . $label;
    }

    public function getLocationDisplayAttribute(): string
    {
        if ($this->location) {
            return $this->location;
        }

        if ($this->city) {
            return $this->city->name;
        }

        return $this->company?->city?->name ?? '';
    }

    public function getUrlSlugAttribute(): string
    {
        return $this->slug;
    }

    // ── Methods ──

    public function publish(): void
    {
        $this->update([
            'is_active' => true,
            'published_at' => now(),
            'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function expire(): void
    {
        $this->update([
            'is_active' => false,
            'expires_at' => now(),
        ]);
    }

    public function incrementViewsCount(): void
    {
        $this->increment('views_count');
    }

    public function incrementApplicationsCount(): void
    {
        $this->increment('applications_count');
    }

    /**
     * Prüft ob die Firma noch einen aktiven Job erstellen darf.
     */
    public static function canCompanyCreateJob(int $companyId): bool
    {
        $activeCount = static::where('company_id', $companyId)
            ->active()
            ->count();

        return $activeCount < self::MAX_ACTIVE_PER_COMPANY;
    }
}
