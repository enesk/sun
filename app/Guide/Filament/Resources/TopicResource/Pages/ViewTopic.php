<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\TopicResource\Pages;

use App\Guide\Assets\HeroImageGenerator;
use App\Guide\Enums\RunDisplay;
use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Enums\TopicStatus;
use App\Guide\Enums\TrustLevel;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Jobs\GenerateTopicImageJob;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\Central\TopicList;
use App\Guide\Models\Central\TopicListItem;
use App\Guide\Models\Fact;
use App\Guide\Models\Source;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Services\TopicAdminService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Services\TopicRunStarter;
use App\Guide\Support\GuidePageCache;
use App\Guide\Support\UnreachableSources;
use App\Guide\Support\Usd;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Thema-Detail (design/guide-dashboard.md §5.3): Kopf mit Status und
 * letztem Lauf, Reiter Gliederung (Gliederungs-Editor), Fakten, Quellen,
 * Laeufe. Adresse /themen/{tenant-id}-{topic-id}.
 * Titelbild (#20): Vorschau im Kopf, "Bild neu erzeugen" und "Bild
 * ersetzen" (Upload) unter Aktionen, sobald ein Artikel existiert.
 *
 * Die Seite arbeitet nie im Tenant-Kontext der Anfrage, sondern liest und
 * schreibt ueber $tenant->run() — so passt sie auch bei "Alle Portale" und
 * nach einem Portalwechsel in der Kopfzeile. Solange der letzte Lauf
 * unterwegs ist, pollt der Laufbereich alle 5 Sekunden.
 */
class ViewTopic extends Page
{
    protected static string $resource = TopicResource::class;

    protected string $view = 'content.guide.topic-detail';

    public const TABS = ['gliederung', 'fakten', 'quellen', 'laeufe'];

    private const RUN_HISTORY = 30;

    public int $tenantId;

    public int $topicId;

    #[Url(as: 'reiter')]
    public string $tab = 'gliederung';

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $detail = null;

    public function mount(string $record): void
    {
        $parsed = TopicDirectory::parseKey($record);

        abort_if($parsed === null || app(TopicDirectory::class)->tenant($parsed[0]) === null, 404);

        [$this->tenantId, $this->topicId] = $parsed;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'gliederung';
        }

        abort_if($this->detail() === null, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return (string) ($this->detail()['question'] ?? __('Thema'));
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $detail = $this->detail();

        return array_filter([
            TopicResource::getUrl() => __('Themen'),
            $detail['category_name'] ?? null,
            __('Thema'),
        ]);
    }

    public function key(): string
    {
        return TopicDirectory::key($this->tenantId, $this->topicId);
    }

    public function tenant(): Tenant
    {
        return app(TopicDirectory::class)->tenant($this->tenantId) ?? abort(404);
    }

    /**
     * Kopf und Laufbereich neu lesen (wire:poll, Sperre im Gliederungs-Editor).
     */
    #[On('guide-outline-locked')]
    public function refreshRuns(): void
    {
        $this->detail = null;
    }

    public function isRunActive(): bool
    {
        $display = $this->detail()['run']['run_display'] ?? null;

        return in_array($display, [RunDisplay::QUEUED->value, RunDisplay::IN_PROGRESS->value], true);
    }

    /**
     * Alles, was die Seite zeigt, in einem Durchgang durch die Tenant-DB.
     *
     * @return array<string, mixed>|null
     */
    public function detail(): ?array
    {
        if ($this->detail !== null) {
            return $this->detail;
        }

        $tenant = $this->tenant();

        $detail = $tenant->run(function () use ($tenant): ?array {
            /** @var Topic|null $topic */
            $topic = Topic::query()->with(['category:id,name,slug', 'article:id,slug,status'])->find($this->topicId);

            if ($topic === null) {
                return null;
            }

            /** @var Post|null $article */
            $article = $topic->article;
            $articleDetail = $article !== null ? ArticleDetail::query()->where('article_id', $article->getKey())->first() : null;

            $runs = $topic->runs()->latest('run_date')->latest('id')->limit(self::RUN_HISTORY)->get();
            $unreachable = UnreachableSources::forTopic($topic);
            $unreachableIds = array_column($unreachable, 'id');

            return [
                'question' => (string) $topic->question,
                'status' => $topic->status->value,
                'category_name' => $topic->category?->name,
                'interval' => $topic->refreshIntervalDays(),
                'interval_custom' => $topic->refresh_interval_days !== null,
                'list_item_id' => $topic->list_item_id,
                'article_id' => $topic->article_id,
                'article_url' => $article?->status === 'published'
                    ? $this->portalUrl($tenant, route('guide.show', $article->slug, false))
                    : null,
                'outline_locked_at' => $topic->outline_locked_at?->toIso8601String(),
                'has_article_detail' => $articleDetail !== null,
                'hero' => HeroImageGenerator::presentation($articleDetail),
                'run' => TopicDirectory::runFields($runs->first()),
                'runs' => $runs->map(fn (TopicRun $run): array => $this->runRow($run))->all(),
                'unreachable' => $unreachable,
                'facts' => $topic->currentFacts()->with('source:id,url,title,publisher')->orderBy('label')->get()
                    ->map(fn (Fact $fact): array => [
                        'label' => (string) $fact->label,
                        'value' => (string) $fact->value,
                        'unit' => $fact->unit,
                        'valid_from' => $fact->valid_from?->format('d.m.Y'),
                        'last_seen_at' => Carbon::make($fact->last_seen_at)?->timezone(config('guide.timezone'))->format('d.m.Y'),
                        'source' => $fact->source !== null ? [
                            'url' => $fact->source->url,
                            'title' => $fact->source->title ?? $fact->source->publisher ?? $fact->source->url,
                            'unreachable' => in_array((int) $fact->source->getKey(), $unreachableIds, true),
                        ] : null,
                    ])->all(),
                'facts_history' => $topic->facts()->where('is_current', false)->count(),
                'sources' => $topic->sources()->latest('retrieved_at')->get()
                    ->map(fn (Source $source): array => [
                        'url' => (string) $source->url,
                        // Marke nur, solange die Quelle einen aktuellen Fakt belegt (§5.7.3)
                        'unreachable' => in_array((int) $source->getKey(), $unreachableIds, true),
                        'link_status_code' => $source->link_status_code,
                        'link_checked_at' => $source->link_checked_at?->timezone(config('guide.timezone'))->format('d.m., H:i'),
                        'title' => $source->title ?? $source->url,
                        'publisher' => $source->publisher,
                        'published_at' => $source->published_at?->format('d.m.Y'),
                        'retrieved_at' => $source->retrieved_at?->timezone(config('guide.timezone'))->format('d.m.Y'),
                        'trust' => TrustLevel::tryFrom((string) ($source->trust_level instanceof TrustLevel ? $source->trust_level->value : $source->trust_level))?->label(),
                    ])->all(),
            ];
        });

        if ($detail !== null) {
            $detail['tenant_name'] = (string) $tenant->name;
            /** @var TopicList|null $list */
            $list = $detail['list_item_id'] !== null
                ? TopicListItem::query()->with('topicList:id,name')->find($detail['list_item_id'])?->topicList
                : null;
            $detail['list_name'] = $list?->name;
        }

        return $this->detail = $detail;
    }

    protected function getHeaderActions(): array
    {
        $detail = $this->detail() ?? [];
        $status = TopicStatus::tryFrom((string) ($detail['status'] ?? ''));

        return [
            Action::make('portal')
                ->label(__('Im Portal ansehen'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url($detail['article_url'] ?? null, shouldOpenInNewTab: true)
                ->visible(filled($detail['article_url'] ?? null)),
            ActionGroup::make([
                Action::make('runNow')
                    ->label(__('Jetzt ausführen …'))
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->modalHeading(__('Jetzt ausführen'))
                    ->modalDescription(fn (): string => TopicResource::runCostLine(collect([['article_id' => $detail['article_id'] ?? null]])))
                    ->modalSubmitActionLabel(__('Lauf starten'))
                    ->action(function (): void {
                        $result = app(TopicAdminService::class)->runNow([$this->key()])[0] ?? null;
                        $started = ($result['outcome'] ?? null) === TopicRunStarter::STARTED;
                        $this->detail = null;
                        $this->tab = 'laeufe';

                        $notification = Notification::make()
                            ->title($started ? __('Lauf gestartet') : __('Kein neuer Lauf gestartet'))
                            ->body($started ? __('Der Fortschritt erscheint hier unter „Läufe“.') : ($result['reason'] ?? null));

                        ($started ? $notification->success() : $notification->warning())->send();
                    }),
                Action::make('regenerateImage')
                    ->label(__('Bild neu erzeugen …'))
                    ->icon('heroicon-o-photo')
                    ->visible((bool) ($detail['has_article_detail'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(__('Titelbild neu erzeugen'))
                    ->modalDescription(__('Das Bild wird im Hintergrund über fal.ai neu erzeugt und ersetzt das bisherige. Schlägt das fehl, wird das Branchen-Standardbild verwendet.'))
                    ->modalSubmitActionLabel(__('Neu erzeugen'))
                    ->action(function (): void {
                        GenerateTopicImageJob::dispatch($this->tenantId, $this->topicId, true);

                        Notification::make()
                            ->title(__('Titelbild wird neu erzeugt'))
                            ->body(__('Das neue Bild erscheint nach einigen Minuten hier und im Artikel.'))
                            ->success()
                            ->send();
                    }),
                Action::make('replaceImage')
                    ->label(__('Bild ersetzen …'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible((bool) ($detail['has_article_detail'] ?? false))
                    ->modalHeading(__('Titelbild ersetzen'))
                    ->modalSubmitActionLabel(__('Hochladen'))
                    ->schema([
                        FileUpload::make('image')
                            ->label(__('Bilddatei'))
                            ->helperText(__('JPEG, PNG oder WebP im Querformat. Wird auf 16:9 zugeschnitten und in 1200/800/400 px gespeichert.'))
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) config('guide.images.max_upload_kb', 10240))
                            ->storeFiles(false)
                            ->required(),
                        TextInput::make('alt')
                            ->label(__('Alternativtext'))
                            ->helperText(__('Was ist auf dem Bild zu sehen? Leer lassen, um den bisherigen Text zu behalten.'))
                            ->maxLength((int) config('guide.images.alt_max_chars', 125)),
                    ])
                    ->action(fn (array $data) => $this->replaceImage($data)),
                Action::make('pause')
                    ->label(__('Pausieren'))
                    ->icon('heroicon-o-pause-circle')
                    ->visible($status === TopicStatus::ACTIVE)
                    ->action(fn () => $this->afterChange(app(TopicAdminService::class)->pause([$this->key()]), __('pausiert'))),
                Action::make('resume')
                    ->label(__('Fortsetzen'))
                    ->icon('heroicon-o-play-circle')
                    ->visible($status === TopicStatus::PAUSED)
                    ->action(fn () => $this->afterChange(app(TopicAdminService::class)->activate([$this->key()]), __('fortgesetzt'))),
                Action::make('archive')
                    ->label(__('Archivieren …'))
                    ->icon('heroicon-o-archive-box')
                    ->color('status-failed')
                    ->visible($status !== null && $status !== TopicStatus::ARCHIVED)
                    ->requiresConfirmation()
                    ->modalDescription(__('Archivierte Themen laufen nicht mehr im Tageslauf mit. Ein veröffentlichter Artikel wird zurückgezogen.'))
                    ->action(fn () => $this->afterChange(app(TopicAdminService::class)->archive([$this->key()]), __('archiviert'))),
            ])->label(__('Aktionen'))->button()->color('gray'),
        ];
    }

    /**
     * Kopfzeile unter dem Titel: Portal · Liste · Pruefabstand.
     */
    public function metaLine(): string
    {
        $detail = $this->detail() ?? [];

        return collect([
            $detail['tenant_name'] ?? null,
            filled($detail['list_name'] ?? null) ? __('angelegt aus Liste „:name“', ['name' => $detail['list_name']]) : null,
            trans_choice('{1} Prüfabstand täglich|[2,*] Prüfabstand :count Tage', (int) ($detail['interval'] ?? 7), ['count' => (int) ($detail['interval'] ?? 7)])
                .(($detail['interval_custom'] ?? false) ? '' : ' '.__('(Vorgabe)')),
        ])->filter()->implode(' · ');
    }

    /**
     * Zweiter Satz des Bands "nicht erreichbar" (§5.7.3): wann die naechste
     * Tiefenrecherche Ersatz sucht. Nur aktive Themen laufen im Tageslauf.
     */
    public function unreachableNextStep(): string
    {
        $status = TopicStatus::tryFrom((string) ($this->detail()['status'] ?? ''));

        if ($status !== TopicStatus::ACTIVE) {
            return __('Solange das Thema nicht aktiv ist, sucht kein Tageslauf Ersatz.');
        }

        return __('Die nächste Tiefenrecherche sucht Ersatz — am :date im Tageslauf.', [
            'date' => UnreachableSources::nextDailyRun()->format('d.m.'),
        ]);
    }

    /**
     * Upload aus "Bild ersetzen": gleicher Weg wie das erzeugte Bild ab dem
     * Optimizer. Die Datei wird vor dem Tenant-Wechsel gelesen, weil der
     * Livewire-Zwischenspeicher am zentralen storage_path haengt.
     *
     * @param  array<string, mixed>  $data
     */
    private function replaceImage(array $data): void
    {
        $file = collect(is_array($data['image'] ?? null) ? $data['image'] : [$data['image'] ?? null])
            ->first(fn (mixed $value): bool => $value instanceof TemporaryUploadedFile);
        $binary = $file instanceof TemporaryUploadedFile ? (string) $file->get() : '';

        if ($binary === '') {
            Notification::make()->title(__('Keine Bilddatei erhalten'))->danger()->send();

            return;
        }

        try {
            $this->tenant()->run(function () use ($binary, $data): void {
                $topic = Topic::query()->findOrFail($this->topicId);
                $detail = ArticleDetail::query()->where('article_id', $topic->article_id)->firstOrFail();

                $image = app(HeroImageGenerator::class)->fromUpload($topic, $binary, $data['alt'] ?? null, $detail->hero_image_alt);

                $detail->forceFill([
                    'hero_image_path' => $image['path'],
                    'hero_image_alt' => $image['alt'],
                    'hero_image_width' => $image['width'],
                    'hero_image_height' => $image['height'],
                ])->save();

                GuidePageCache::flush();
            });
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('Bild konnte nicht verarbeitet werden'))
                ->body(__('Bitte eine andere Datei (JPEG, PNG oder WebP) versuchen.'))
                ->danger()
                ->send();

            return;
        }

        $this->detail = null;

        Notification::make()->title(__('Titelbild ersetzt'))->success()->send();
    }

    /**
     * @param  array{done: int, skipped: int}  $result
     */
    private function afterChange(array $result, string $verb): void
    {
        TopicResource::notifyResult($result, $verb);

        $this->redirect(TopicResource::detailUrl($this->key(), $this->tab), navigate: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function runRow(TopicRun $run): array
    {
        $status = $run->status;
        $mode = $run->mode instanceof RunMode ? $run->mode : RunMode::tryFrom((string) $run->mode);
        $start = Carbon::make($run->started_at ?? $run->created_at);
        $end = Carbon::make($run->finished_at);

        return [
            ...TopicDirectory::runFields($run),
            'date' => $start?->timezone(config('guide.timezone'))->format('d.m.Y, H:i') ?? $run->run_date?->format('d.m.Y'),
            'mode' => $mode?->label(),
            'status_value' => $status instanceof RunStatus ? $status->value : (string) $status,
            'duration' => $start !== null && $end !== null ? $this->duration((int) $start->diffInSeconds($end)) : null,
            'cost' => Usd::format((float) $run->cost_usd),
            'steps' => array_filter([
                __('angelegt') => Carbon::make($run->created_at)?->timezone(config('guide.timezone'))->format('H:i:s'),
                __('gestartet') => Carbon::make($run->started_at)?->timezone(config('guide.timezone'))->format('H:i:s'),
                __('beendet') => Carbon::make($run->finished_at)?->timezone(config('guide.timezone'))->format('H:i:s'),
            ]),
            'changed_sections' => count($run->changed_section_ids_json ?? []),
            'change_summary' => $run->change_summary,
        ];
    }

    private function duration(int $seconds): string
    {
        return $seconds < 60
            ? __(':s s', ['s' => $seconds])
            : __(':m min :s s', ['m' => intdiv($seconds, 60), 's' => $seconds % 60]);
    }

    private function portalUrl(Tenant $tenant, string $path): ?string
    {
        if (blank($tenant->domain)) {
            return null;
        }

        $scheme = app()->environment('production') ? 'https' : 'http';

        return "{$scheme}://{$tenant->domain}{$path}";
    }
}
