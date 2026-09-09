<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\DraftSource;
use App\Content\Models\TenantContentSetting;
use App\Content\Support\BranchResolver;
use App\Content\Support\GenerationStart;
use App\Content\Support\PipelineCardKey;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pruef-Queue und Freigabe der Content-Pipeline (#20).
 *
 * Die Warteschlange zieht die Entwuerfe im Status `review` aus allen
 * sichtbaren Portalen zusammen; laengste Wartezeit zuerst
 * (design/content-dashboard.md, §4). Der Tenant-Durchgang und die Abbildung
 * auf Anzeigewerte liegen hier, damit die Livewire-Komponente keine Modelle
 * kennt — genau wie beim ContentPipelineService (#19).
 */
final class ReviewQueueService
{
    /**
     * Obergrenze je Portal. Mehr als das ist keine Warteschlange mehr,
     * sondern ein Stau — der gehoert in die Artikelliste.
     */
    private const PER_TENANT_LIMIT = 100;

    public function __construct(
        private readonly ContentTenantContext $context,
        private readonly QualityReportPresenter $quality,
        private readonly ArticleDiffRenderer $diff,
        private readonly ContentPreviewLink $previewLink,
        private readonly ContentPipelineService $pipeline,
        private readonly ArticleBlockPresenter $blocks,
        private readonly RefreshStatus $refreshStatus,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Warteschlange
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function queue(array $filters = []): array
    {
        $entries = [];

        foreach ($this->tenants($filters) as $tenant) {
            foreach ($this->tenantQueue($tenant) as $entry) {
                $entries[] = $entry;
            }
        }

        // Laengste Wartezeit zuerst.
        usort($entries, fn (array $a, array $b): int => [$a['waiting_since'], $a['tenant']] <=> [$b['waiting_since'], $b['tenant']]);

        return $entries;
    }

    /**
     * Zahl fuer die Zaehlmarke der Navigation. Bei 0 verschwindet sie.
     */
    public function pendingCount(): int
    {
        $count = 0;

        foreach ($this->tenants() as $tenant) {
            $count += (int) ($this->run($tenant, fn (): int => ArticleDraft::query()
                ->withStatus(DraftStatus::REVIEW)
                ->whereNull('withdrawn_at')
                ->count()) ?? 0);
        }

        return $count;
    }

    /**
     * Zeitpunkt der letzten Entscheidung — Grundlage des Leerzustands
     * ("Nichts zu prüfen. Zuletzt geprüft: ...").
     */
    public function lastDecisionAt(): ?string
    {
        $latest = null;

        foreach ($this->tenants() as $tenant) {
            $value = $this->run($tenant, fn (): ?string => ArticleDraft::query()
                ->whereIn('status', [DraftStatus::APPROVED->value, DraftStatus::SCHEDULED->value, DraftStatus::PUBLISHED->value])
                ->max('updated_at'));

            if (is_string($value) && ($latest === null || $value > $latest)) {
                $latest = $value;
            }
        }

        return $latest;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantQueue(Tenant $tenant): array
    {
        return $this->run($tenant, function () use ($tenant): array {
            $threshold = $this->threshold();

            return ArticleDraft::query()
                ->withStatus(DraftStatus::REVIEW)
                ->whereNull('withdrawn_at')
                ->orderBy('updated_at')
                ->limit(self::PER_TENANT_LIMIT)
                ->get()
                ->map(fn (ArticleDraft $draft): array => [
                    'key' => PipelineCardKey::draft((int) $tenant->getKey(), (int) $draft->getKey())->toString(),
                    'tenant_id' => (int) $tenant->getKey(),
                    'tenant' => (string) $tenant->name,
                    'branch' => BranchResolver::resolve($tenant),
                    'title' => (string) $draft->title,
                    'score' => $draft->quality_score !== null ? (float) $draft->quality_score : null,
                    'score_level' => $this->quality->level(
                        $draft->quality_score !== null ? (float) $draft->quality_score : null,
                        $threshold,
                    ),
                    'waiting_since' => $draft->updated_at?->toDateTimeString(),
                    'waiting_for' => $draft->updated_at?->diffForHumans(short: true),
                    'attempt' => (int) $draft->attempt,
                    'is_refresh' => $draft->article_id !== null,
                ])
                ->values()
                ->all();
        }) ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Pruefblatt
    |--------------------------------------------------------------------------
    */

    /**
     * Alles, was die Pruefflaeche braucht: Vorschau-Adresse, Qualitaetsreport,
     * Faktencheck, Quellen und — bei einer Aktualisierung — der Vergleich mit
     * der stehenden Fassung.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $key): ?array
    {
        $card = PipelineCardKey::parse($key);

        if (! $card->isDraft()) {
            return null;
        }

        $tenant = $this->tenant($card->tenantId);

        if ($tenant === null) {
            return null;
        }

        return $this->run($tenant, function () use ($tenant, $card, $key): ?array {
            $draft = ArticleDraft::query()->with(['sources', 'article'])->find($card->id);

            if ($draft === null) {
                return null;
            }

            $threshold = $this->threshold();
            $quality = $this->quality->present($draft, $threshold);

            return [
                'key' => $key,
                'tenant_id' => (int) $tenant->getKey(),
                'tenant' => (string) $tenant->name,
                'title' => (string) $draft->title,
                'status' => $draft->display_status->value,
                'status_label' => $draft->display_status->label(),
                'slug' => (string) $draft->slug,
                'meta_title' => (string) $draft->meta_title,
                'meta_description' => (string) $draft->meta_description,
                'attempt' => (int) $draft->attempt,
                'word_count' => str_word_count(strip_tags((string) $draft->body_html)),
                'scheduled_for' => $draft->scheduled_for?->format('d.m.Y H:i'),
                'preview_url' => $this->previewLink->for($tenant, $draft),
                'quality' => $quality,
                'sources' => $this->sources($draft),
                'assets' => $this->assets($draft),
                'diff' => $this->diffFor($draft),
                // Aktualisierungsstand und Herkunft (#99). Beide haengen am
                // bereits geladenen Datensatz; null heisst "nichts anzeigen".
                'refresh' => $this->refreshStatus->for($draft),
                'origin' => $this->refreshStatus->origin($draft),
            ];
        });
    }

    /**
     * Titelbild, Infografik und Meldungen des Asset-Laufs (#16) fuer die
     * Pruefflaeche (#71). Die Herkunft steht dabei gleichberechtigt neben der
     * Vorschau: nur so erkennt die Redaktion ein Branchen-Standardbild,
     * bevor sie freigibt.
     *
     * @return array{hero: ?array{url: string, width: int, height: int}, alt: ?string, credit: ?string, source: ?string, source_label: string, is_fallback: bool, infographic: ?array{url: string, alt: string}, errors: list<string>}
     */
    private function assets(ArticleDraft $draft): array
    {
        $variants = $this->blocks->heroVariants($draft);
        $source = trim((string) $draft->hero_image_source) ?: null;

        $errors = [];

        foreach ((array) ($draft->assets_json['errors'] ?? []) as $error) {
            $error = trim((string) $error);

            if ($error !== '') {
                $errors[] = $error;
            }
        }

        return [
            'hero' => $variants[0] ?? null,
            'alt' => trim((string) $draft->hero_image_alt) ?: null,
            'credit' => trim((string) $draft->hero_image_credit) ?: null,
            'source' => $source,
            'source_label' => match ($source) {
                'fal' => 'KI-Bild (fal.ai)',
                'unsplash' => 'Unsplash',
                'fallback' => 'Branchen-Standardbild',
                default => 'unbekannt',
            },
            'is_fallback' => $source === 'fallback' || $source === null,
            'infographic' => $this->blocks->infographic($draft),
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sources(ArticleDraft $draft): array
    {
        return $draft->sources
            ->sortBy('sort_order')
            ->map(function (DraftSource $source): array {
                $publishedAt = $source->published_at;

                return [
                    'title' => (string) ($source->title ?: $source->url),
                    'url' => $source->url,
                    'publisher' => $source->publisher,
                    'published_at' => $publishedAt?->format('d.m.Y'),
                    'is_cited' => (bool) $source->is_cited,
                    'snippet' => $source->snippet !== null ? Str::limit((string) $source->snippet, 180) : null,
                    // Aeltere Quellen brauchen einen zweiten Blick — die
                    // Zahl darin kann laengst ueberholt sein.
                    'stale' => $publishedAt !== null && $publishedAt->lt(now()->subMonths(12)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Vergleich nur bei einer Aktualisierung: der Entwurf haengt bereits an
     * einem veroeffentlichten Artikel (#24). Bei einem Erstentwurf gibt es
     * nichts zu vergleichen.
     *
     * @return array<string, mixed>|null
     */
    private function diffFor(ArticleDraft $draft): ?array
    {
        $article = $draft->article;

        if (! $article instanceof Post) {
            return null;
        }

        return $this->diff->diff((string) $article->body, (string) $draft->body_html) + [
            'compared_to' => ($article->published_at ?? $article->updated_at)?->format('d.m.Y'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Entscheidungen
    |--------------------------------------------------------------------------
    */

    /**
     * Freigeben. Setzt `approved` und stoesst die Folgeschritte an: Assets
     * (#16) und Einplanung/Veroeffentlichung (#21). Beide Jobs entstehen in
     * eigenen Tickets — solange sie fehlen, bleibt es beim Statuswechsel,
     * aus dem der spaetere Lauf ohnehin zieht.
     */
    public function approve(string $key): GenerationStart
    {
        $card = PipelineCardKey::parse($key);
        $tenant = $this->requireTenant($card->tenantId);

        return $tenant->run(function () use ($card, $tenant): GenerationStart {
            $draft = ArticleDraft::query()->findOrFail($card->id);

            if (! $draft->status->canTransitionTo(DraftStatus::APPROVED)) {
                throw new RuntimeException(__('Aus diesem Status heraus lässt sich nicht freigeben.'));
            }

            $draft->transitionTo(DraftStatus::APPROVED);

            $dispatched = $this->dispatchFollowUps($tenant, $draft);

            return $dispatched
                ? GenerationStart::queued(__('Freigegeben. Bilder und Veröffentlichung sind angestoßen.'))
                : GenerationStart::deferred(__('Freigegeben. Der nächste Veröffentlichungslauf übernimmt den Artikel.'));
        });
    }

    /**
     * Mit Hinweis neu generieren. Der Hinweis wird als `fix_instructions` am
     * Qualitaetsbericht hinterlegt — genau dort liest ihn der FixSectionsStep
     * des Generators (#14), und genau dort schreibt auch das Qualitaetsgate
     * seine eigenen Korrekturhinweise hin (docs/content-prompts.md, §6).
     * Eine eigene Spalte braucht es dafuer nicht.
     *
     * Das Anstossen selbst gehoert nicht hierher: Uebergang, Versuchszaehler,
     * Budget und Job liegen im gemeinsamen Einstiegspunkt
     * ContentPipelineService::startGeneration() (#42, §1). Diese Methode legt
     * nur den Hinweis ab — er ist Pruefungslogik, nicht Generatorlogik.
     */
    public function regenerate(string $key, string $instruction, ?int $userId = null): GenerationStart
    {
        $instruction = trim($instruction);
        $minLength = (int) config('content.withdrawal.reason_min_length', 10);

        if (mb_strlen($instruction) < $minLength) {
            throw new RuntimeException(
                __('Bitte einen Hinweis mit mindestens :count Zeichen angeben.', ['count' => $minLength])
            );
        }

        $card = PipelineCardKey::parse($key);
        $tenant = $this->requireTenant($card->tenantId);

        // Der Hinweis wird abgelegt, bevor der Einstiegspunkt den Entwurf auf
        // `generating` setzt: der Generator liest ihn im selben Lauf.
        $tenant->run(function () use ($card, $instruction, $userId): void {
            $draft = ArticleDraft::query()->findOrFail($card->id);

            $report = $draft->quality_report_json ?? [];
            $instructions = array_values((array) ($report['fix_instructions'] ?? []));
            $instructions[] = $instruction;

            $report['fix_instructions'] = $instructions;
            $report['review'] = [
                'fix_instructions' => $instruction,
                'by' => $userId,
                'at' => now()->toIso8601String(),
            ];

            $draft->forceFill(['quality_report_json' => $report])->save();
        });

        return $this->pipeline->startGeneration($key, $instruction);
    }

    /**
     * Verwerfen mit Pflichtgrund. Der Grund fliesst in die Lernschleife (#23);
     * eine Ablehnung ohne Grund ist verlorene Information.
     */
    public function discard(string $key, string $reason, ?int $userId = null): GenerationStart
    {
        $card = PipelineCardKey::parse($key);
        $tenant = $this->requireTenant($card->tenantId);

        return $tenant->run(function () use ($card, $reason, $userId): GenerationStart {
            $draft = ArticleDraft::query()->findOrFail($card->id);

            // Zuruecknahme haelt den Grund fest und laesst den Fingerprint
            // stehen — das Thema wird also nicht sofort neu erzeugt.
            $draft->withdraw($reason, $userId);

            if ($draft->status->canTransitionTo(DraftStatus::FAILED)) {
                $draft->transitionTo(DraftStatus::FAILED);
            }

            return GenerationStart::queued(__('Artikel verworfen.'));
        });
    }

    /**
     * Folgeschritte der Freigabe. Die Jobs stammen aus #16 und #21; fehlt
     * einer, wird er uebersprungen statt einen Fehler zu werfen.
     */
    private function dispatchFollowUps(Tenant $tenant, ArticleDraft $draft): bool
    {
        $dispatched = false;

        foreach (['App\\Content\\Jobs\\GenerateAssetsJob', 'App\\Content\\Jobs\\ScheduleAndPublishJob'] as $job) {
            if (! class_exists($job)) {
                continue;
            }

            try {
                $job::dispatch((int) $tenant->getKey(), (int) $draft->getKey());
                $dispatched = true;
            } catch (Throwable $exception) {
                Log::error('Freigabe: Folgejob konnte nicht angestossen werden.', [
                    'job' => $job,
                    'tenant_id' => $tenant->getKey(),
                    'draft_id' => $draft->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /*
    |--------------------------------------------------------------------------
    | Portale
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $filters
     * @return list<Tenant>
     */
    public function tenants(array $filters = []): array
    {
        $tenants = collect($this->context->available()->all());
        $selected = $this->context->selectedId();

        if ($selected !== null) {
            $tenants = $tenants->filter(fn (Tenant $tenant): bool => (int) $tenant->getKey() === $selected);
        }

        $portals = array_map('intval', array_filter((array) ($filters['portals'] ?? [])));

        if ($portals !== []) {
            $tenants = $tenants->filter(fn (Tenant $tenant): bool => in_array((int) $tenant->getKey(), $portals, true));
        }

        $branches = array_values(array_filter((array) ($filters['branches'] ?? [])));

        if ($branches !== []) {
            $tenants = $tenants->filter(fn (Tenant $tenant): bool => in_array(BranchResolver::resolve($tenant), $branches, true));
        }

        /** @var list<Tenant> $result */
        $result = $tenants->values()->all();

        return $result;
    }

    private function tenant(int $id): ?Tenant
    {
        foreach ($this->tenants() as $tenant) {
            if ((int) $tenant->getKey() === $id) {
                return $tenant;
            }
        }

        return null;
    }

    private function requireTenant(int $id): Tenant
    {
        return $this->tenant($id) ?? throw new RuntimeException(__('Für dieses Portal fehlt die Berechtigung.'));
    }

    private function threshold(): int
    {
        return (int) (TenantContentSetting::query()->value('auto_publish_threshold')
            ?: config('content.quality.auto_approve_score', 85));
    }

    /**
     * Tenant-Durchgang, der ein Portal ohne Pipeline-Tabellen ueberspringt
     * statt die Seite zu zerlegen.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn|null
     */
    private function run(Tenant $tenant, callable $callback)
    {
        try {
            return $tenant->run($callback);
        } catch (Throwable $exception) {
            Log::warning('Pruef-Queue: Portal uebersprungen.', [
                'tenant_id' => $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
