<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Console\Commands\Guide\GuideImportsPrune;
use App\Guide\Enums\TopicStatus;
use App\Guide\Filament\Concerns\HasTopicTabs;
use App\Guide\Import\ImportPreview;
use App\Guide\Import\ImportReport;
use App\Guide\Import\PlaceholderResolver;
use App\Guide\Import\TopicListAssigner;
use App\Guide\Import\TopicListImporter;
use App\Guide\Import\TopicRowParser;
use App\Guide\Models\Central\TopicList;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\Usd;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Import-Wizard (#15, design/guide-dashboard.md §4), Reiter "Import" unter
 * Themen: Hochladen oder Einfuegen → Spalten zuordnen → Vorschau mit Fehlern
 * und Dubletten → Portale waehlen, Liste speichern und zuweisen.
 *
 * Die Datei wird nach Schritt 1 unter storage/app/guide-imports abgelegt und
 * nach 7 Tagen von guide:imports:prune geloescht. Der Zwischenstand liegt je
 * Benutzer im Cache, ein Neuladen verliert nichts. Gespeichert und
 * zugewiesen wird ueber TopicListImporter und TopicListAssigner (#6) — die
 * Vorschau wendet dieselben Regeln an (ImportPreview).
 *
 * @property-read Schema $form
 */
class ImportWizard extends Page
{
    use HasTopicTabs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $slug = 'themen/import';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'content.guide.import-wizard';

    public const MAX_ROWS = 1000;

    public const MAX_FILE_KB = 5120;

    public const MAX_PASTE_CHARS = 200000;

    /**
     * Obergrenze gelesener Zeilen inkl. Leerzeilen, damit eine Datei aus
     * lauter Leerzeilen (Zip-Bomb bei XLSX) nicht unbegrenzt gelesen wird.
     */
    private const MAX_READ_LINES = 10000;

    /**
     * Gespeicherte Upload-Datei (guide-imports/<uuid>.csv|xlsx). Locked und
     * nicht Teil von $data: der Client darf den Pfad nicht setzen (Review S1).
     */
    #[Locked]
    public ?string $storedPath = null;

    private const STATE_TTL_DAYS = 7;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Ergebnis nach "Import abschliessen".
     *
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    /**
     * Vorschau innerhalb einer Anfrage.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $preview = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Themen importieren');
    }

    public function mount(): void
    {
        // Upload und Vorschau arbeiten central: Im Tenant-Kontext zeigte
        // storage_path() auf den Speicher des Portals.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $state = Cache::get($this->stateKey(), [
            'source' => 'file',
            'has_header' => true,
        ]);

        $this->storedPath = $state['stored_path'] ?? null;
        unset($state['stored_path']);

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    $this->uploadStep(),
                    $this->columnsStep(),
                    $this->previewStep(),
                    $this->assignStep(),
                ])
                    ->persistStepInQueryString('schritt')
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" icon="heroicon-o-check">'.e(__('Import abschließen')).'</x-filament::button>'
                    ))),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('discard')
                ->label(__('Import verwerfen'))
                ->color('gray')
                ->visible(fn (): bool => $this->result === null && (filled($this->storedPath) || filled($this->data['text'] ?? null)))
                ->requiresConfirmation()
                ->modalHeading(__('Import verwerfen?'))
                ->modalDescription(__('Die Angaben dieses Imports werden gelöscht. Bereits gespeicherte Themenlisten bleiben erhalten.'))
                ->action(fn () => $this->restart()),
        ];
    }

    public function restart(): void
    {
        Cache::forget($this->stateKey());

        $this->result = null;
        $this->preview = null;
        $this->storedPath = null;
        $this->form->fill(['source' => 'file', 'has_header' => true]);

        $this->redirect(static::getUrl());
    }

    /**
     * Schritt 4 abgeschlossen: Liste speichern, Themen importieren, zuweisen.
     */
    public function finish(): void
    {
        $data = $this->form->getState();
        $data = [...$this->data, ...$data];
        $tenantIds = array_map('intval', (array) ($data['tenants'] ?? []));
        $directory = app(TopicDirectory::class);

        try {
            $rows = $this->rows();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        // Erneut pruefen: Zeilenzahl aus Schritt 1 ist Client-State.
        if ($this->contentRows($rows) - ((bool) ($data['has_header'] ?? false) ? 1 : 0) > self::MAX_ROWS) {
            Notification::make()->title(__('Die Liste hat mehr als :max Zeilen. Teilen Sie sie in zwei Listen auf.', [
                'max' => number_format(self::MAX_ROWS, 0, ',', '.'),
            ]))->danger()->send();

            return;
        }

        $list = TopicList::query()->create([
            'name' => trim((string) $data['name']),
            'branch' => $data['branch'] ?? null,
            'source' => ($data['source'] ?? 'file') === 'paste' ? TopicListImporter::FORMAT_PASTE : $this->format(),
            'created_by' => Filament::auth()->id(),
        ]);

        $report = app(TopicListImporter::class)->importMapped(
            $list,
            $rows,
            ($data['source'] ?? 'file') === 'paste' ? TopicListImporter::FORMAT_PASTE : $this->format(),
            $this->columnMap(),
            (bool) ($data['has_header'] ?? false),
        );

        $portals = [];

        foreach ($tenantIds as $tenantId) {
            $tenant = $directory->tenant($tenantId);

            if ($tenant === null) {
                continue;
            }

            try {
                $portalReport = app(TopicListAssigner::class)->assign($list, $tenant);
            } catch (Throwable $e) {
                $portalReport = new ImportReport;
                $portalReport->addError(0, __('Zuweisung fehlgeschlagen: :message', ['message' => Str::limit($e->getMessage(), 160)]));
            }

            $portals[] = ['tenant_id' => $tenantId, 'name' => (string) $tenant->name, ...$portalReport->toArray()];
        }

        $directory->forget();
        Cache::forget($this->stateKey());

        $this->result = [
            'list_id' => (int) $list->getKey(),
            'list_name' => (string) $list->name,
            'report' => $report->toArray(),
            'portals' => $portals,
            'created' => array_sum(array_column($portals, 'imported')),
            'outline_pending' => collect($directory->topics())->where('status', TopicStatus::OUTLINE_PENDING->value)->count(),
        ];
    }

    /**
     * Vorschau fuer Schritt 3 und die Zusammenfassung in Schritt 4.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        if ($this->preview !== null) {
            return $this->preview;
        }

        try {
            $rows = $this->rows();
        } catch (InvalidArgumentException) {
            return $this->preview = ['rows' => [], 'counts' => ['new' => 0, 'duplicate' => 0, 'new_category' => 0, 'outline' => 0, 'error' => 0], 'categories' => [], 'hints' => [], 'importable' => 0];
        }

        $tenantId = (int) ($this->data['preview_tenant'] ?? 0);
        $tenant = $tenantId > 0 ? app(TopicDirectory::class)->tenant($tenantId) : null;

        return $this->preview = app(ImportPreview::class)->build($rows, $this->columnMap(), (bool) ($this->data['has_header'] ?? false), $tenant);
    }

    private function uploadStep(): Step
    {
        return Step::make('upload')
            ->label(__('Hochladen'))
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('name')
                        ->label(__('Name der Themenliste'))
                        ->required()
                        ->maxLength(80),
                    Select::make('branch')
                        ->label(__('Branche'))
                        ->options(fn (): array => $this->branchOptions())
                        ->required(fn (): bool => $this->branchOptions() !== [])
                        ->searchable()
                        ->helperText(__('Portale dieser Branche sind bei der Zuweisung vorausgewählt; ihre Werte ersetzen die Platzhalter {branche} und {branchen}.')),
                ]),
                ToggleButtons::make('source')
                    ->label(__('Eingabeweg'))
                    ->options(['file' => __('Datei'), 'paste' => __('Einfügen')])
                    ->icons(['file' => 'heroicon-o-document-arrow-up', 'paste' => 'heroicon-o-clipboard-document'])
                    ->inline()
                    ->grouped()
                    ->live()
                    ->required(),
                Text::make(fn (): string => __('Datei: :name (:rows Zeilen)', [
                    'name' => (string) ($this->data['file_name'] ?? ''),
                    'rows' => (int) ($this->data['row_count'] ?? 0),
                ]))
                    ->visible(fn (Get $get): bool => $get('source') === 'file' && filled($this->storedPath)),
                FileUpload::make('upload')
                    ->label(fn (): string => filled($this->storedPath) ? __('Andere Datei hochladen') : __('CSV- oder Excel-Datei'))
                    ->helperText(__('Erlaubt: .csv, .xlsx, höchstens 5 MB und 1.000 Zeilen.'))
                    ->acceptedFileTypes([
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(self::MAX_FILE_KB)
                    ->storeFiles(false)
                    ->visible(fn (Get $get): bool => $get('source') === 'file')
                    ->required(fn (Get $get): bool => $get('source') === 'file' && blank($this->storedPath)),
                Textarea::make('text')
                    ->label(__('Themen einfügen'))
                    ->rows(12)
                    ->maxLength(self::MAX_PASTE_CHARS)
                    ->helperText(__('Eine Zeile je Thema. Spalten mit Tabulator trennen — so kommt es aus Excel oder Google Tabellen.'))
                    ->visible(fn (Get $get): bool => $get('source') === 'paste')
                    ->required(fn (Get $get): bool => $get('source') === 'paste'),
            ])
            ->afterValidation(function (): void {
                $this->storeUpload();
                $this->analyse();
                $this->remember();
            });
    }

    private function columnsStep(): Step
    {
        return Step::make('columns')
            ->label(__('Spalten zuordnen'))
            ->schema(fn (): array => [
                Toggle::make('has_header')
                    ->label(__('Erste Zeile ist eine Kopfzeile'))
                    ->live(),
                Text::make(__('Überschriften stehen in einer Zelle, getrennt mit „|“. Ein führendes „>“ macht einen Eintrag zur H3 unter der vorherigen H2, z. B. „Kosten | > Material | > Arbeitszeit | Förderung“.')),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema(
                    collect($this->data['columns'] ?? [])->map(fn (array $column, int $index): Select => Select::make("map.{$index}")
                        ->label(fn (Get $get): string => $get('has_header') ? ($column['head'] !== '' ? $column['head'] : __('Spalte :n', ['n' => $index + 1])) : __('Spalte :n', ['n' => $index + 1]))
                        ->helperText(fn (Get $get): HtmlString => $this->sampleLine($column, (bool) $get('has_header')))
                        ->options($this->targets())
                        ->selectablePlaceholder(false)
                        ->default('skip')
                        ->hint(fn (): ?string => ($column['detected'] ?? false) ? __('erkannt') : null)
                        ->live())
                        ->all(),
                ),
                Text::make(__('„Weiter“ geht erst, wenn eine Spalte als „Thema“ zugeordnet ist.'))
                    ->color('danger')
                    ->visible(fn (Get $get): bool => ! in_array(TopicRowParser::FIELD_QUESTION, (array) $get('map'), true)),
            ])
            ->afterValidation(function (): void {
                $map = array_filter((array) ($this->data['map'] ?? []), fn (?string $target): bool => $target !== null && $target !== 'skip');
                $counts = array_count_values($map);

                if (($counts[TopicRowParser::FIELD_QUESTION] ?? 0) !== 1) {
                    $this->fail(__('Ordnen Sie genau eine Spalte als „Thema“ zu.'));
                }

                $twice = array_keys(array_filter($counts, fn (int $count): bool => $count > 1));

                if ($twice !== []) {
                    $this->fail(__('Jedes Ziel darf nur einmal vergeben werden: :targets.', [
                        'targets' => implode(', ', array_map(fn (string $target): string => $this->targets()[$target] ?? $target, $twice)),
                    ]));
                }

                $this->data['preview_tenant'] ??= $this->defaultPreviewTenant();
                $this->remember();
            });
    }

    private function previewStep(): Step
    {
        return Step::make('preview')
            ->label(__('Vorschau'))
            ->schema([
                Select::make('preview_tenant')
                    ->label(__('Vorschau für Portal'))
                    ->helperText(__('Platzhalter, Dubletten und neue Kategorien werden gegen dieses Portal geprüft.'))
                    ->options(fn (): array => collect(app(TopicDirectory::class)->portals())->mapWithKeys(fn (array $portal): array => [$portal['tenant_id'] => $portal['name']])->all())
                    ->searchable()
                    ->live(),
                View::make('content.guide.partials.import-preview')
                    ->viewData(fn (): array => ['preview' => $this->preview()]),
            ])
            ->afterValidation(function (): void {
                if ($this->preview()['importable'] === 0) {
                    $this->fail(__('Die Liste enthält kein neues Thema.'));
                }

                if (($this->data['tenants'] ?? []) === []) {
                    $this->data['tenants'] = $this->defaultTenants();
                }

                $this->remember();
            });
    }

    private function assignStep(): Step
    {
        return Step::make('assign')
            ->label(__('Zuweisen'))
            ->schema([
                CheckboxList::make('tenants')
                    ->label(__('Portale'))
                    ->options(fn (): array => collect(app(TopicDirectory::class)->portals())
                        ->mapWithKeys(fn (array $portal): array => [(string) $portal['tenant_id'] => $portal['name'].($portal['branch'] ? " · {$portal['branch']}" : '')])
                        ->all())
                    ->descriptions(fn (): array => collect(app(TopicDirectory::class)->portals())
                        ->mapWithKeys(fn (array $portal): array => [(string) $portal['tenant_id'] => trans_choice('{0} noch keine Themen|{1} hat bereits ein Thema|[2,*] hat bereits :count Themen', $portal['topics'], ['count' => $portal['topics']])])
                        ->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->required()
                    ->live(),
                View::make('content.guide.partials.import-summary')
                    ->viewData(fn (Get $get): array => $this->summary(count((array) $get('tenants')))),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(int $portals): array
    {
        $preview = $this->preview();
        $topics = (int) $preview['importable'];
        $outline = collect($preview['rows'])->filter(fn (array $row): bool => in_array('new', $row['marks'], true) && in_array('outline', $row['marks'], true))->count();

        return [
            'topics' => $topics,
            'portals' => $portals,
            'total' => $topics * $portals,
            'new_categories' => count(array_unique(array_filter(array_map(
                fn (array $row): ?string => in_array('new_category', $row['marks'], true) ? Str::slug((string) $row['category'], '-', 'de') : null,
                $preview['rows'],
            )))),
            'with_outline' => $outline,
            'without_outline' => $topics - $outline,
            'cost' => Usd::format(Usd::estimate('create', $topics * $portals)),
        ];
    }

    /**
     * Datei aus dem Livewire-Zwischenspeicher nach storage/app/guide-imports.
     */
    private function storeUpload(): void
    {
        if (($this->data['source'] ?? 'file') !== 'file') {
            return;
        }

        $file = collect((array) ($this->data['upload'] ?? []))->first(fn (mixed $value): bool => $value instanceof TemporaryUploadedFile);

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            $this->fail(__('Nur .csv- oder .xlsx-Dateien sind erlaubt.'));
        }

        $this->storedPath = $file->storeAs(GuideImportsPrune::DIRECTORY, Str::uuid()->toString().".{$extension}", 'local');
        $this->data['file_name'] = $file->getClientOriginalName();
        $this->data['upload'] = [];
    }

    /**
     * Zeilen zaehlen, Spalten und Kopfzeile erkennen, Zuordnung vorbelegen.
     */
    private function analyse(): void
    {
        try {
            $rows = collect($this->rows())->filter(fn (array $cells): bool => $this->hasContent($cells));
        } catch (Throwable $e) {
            $this->fail(__('Die Datei lässt sich nicht lesen: :message', ['message' => Str::limit($e->getMessage(), 120)]));
        }

        if ($rows->isEmpty()) {
            $this->fail(__('Die Liste ist leer.'));
        }

        // rows() bricht nach MAX_ROWS + 2 Inhaltszeilen ab; die genaue Zahl ist unbekannt.
        if ($rows->count() > self::MAX_ROWS + 1) {
            $this->fail(__('Die Liste hat mehr als :max Zeilen. Teilen Sie sie in zwei Listen auf.', [
                'max' => number_format(self::MAX_ROWS, 0, ',', '.'),
            ]));
        }

        $parser = app(TopicRowParser::class);
        $first = array_values($rows->first());
        $header = $parser->headerMap($first);
        $width = (int) $rows->map(fn (array $cells): int => count($cells))->max();
        $samples = $rows->slice($header !== null ? 1 : 0, 3)->values();
        $map = $header ?? $parser->defaultMap($width);

        $this->data['has_header'] = $header !== null;
        $this->data['row_count'] = $rows->count() - ($header !== null ? 1 : 0);
        $this->data['columns'] = array_map(fn (int $index): array => [
            'head' => trim((string) ($first[$index] ?? '')),
            'samples' => $samples->map(fn (array $cells): string => Str::limit(trim((string) (array_values($cells)[$index] ?? '')), 60))->all(),
            'detected' => $header !== null && in_array($index, $header, true),
        ], range(0, max(0, $width - 1)));
        $this->data['map'] = array_map(fn (int $index): string => (string) (array_search($index, $map, true) ?: 'skip'), range(0, max(0, $width - 1)));
    }

    /**
     * Zeilen der Quelle; liest hoechstens MAX_ROWS + 2 Inhaltszeilen bzw.
     * MAX_READ_LINES Zeilen insgesamt, der Rest wird nicht mehr entpackt.
     *
     * @return array<int, array<int, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function rows(): array
    {
        $importer = app(TopicListImporter::class);

        if (($this->data['source'] ?? 'file') === 'paste') {
            $text = (string) ($this->data['text'] ?? '');

            if (mb_strlen($text) > self::MAX_PASTE_CHARS) {
                throw new InvalidArgumentException(__('Der eingefügte Text ist zu lang (höchstens :max Zeichen).', [
                    'max' => number_format(self::MAX_PASTE_CHARS, 0, ',', '.'),
                ]));
            }

            return $this->limited($importer->readPasted($text));
        }

        return $this->limited($importer->readFile(Storage::disk('local')->path($this->storedFile())));
    }

    /**
     * @param  iterable<int, array<int, mixed>>  $source
     * @return array<int, array<int, mixed>>
     */
    private function limited(iterable $source): array
    {
        $rows = [];
        $content = 0;

        foreach ($source as $line => $cells) {
            $rows[$line] = $cells;

            if ($this->hasContent($cells)) {
                $content++;
            }

            if ($content > self::MAX_ROWS + 1 || count($rows) >= self::MAX_READ_LINES) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function contentRows(array $rows): int
    {
        return count(array_filter($rows, fn (array $cells): bool => $this->hasContent($cells)));
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function hasContent(array $cells): bool
    {
        return array_filter($cells, fn (mixed $cell): bool => trim((string) $cell) !== '') !== [];
    }

    /**
     * Relativer Pfad der hochgeladenen Datei, nur innerhalb von guide-imports/
     * und nur im Namensschema aus storeUpload().
     *
     * @throws InvalidArgumentException
     */
    private function storedFile(): string
    {
        $path = (string) $this->storedPath;
        $pattern = '#^'.preg_quote(GuideImportsPrune::DIRECTORY, '#').'/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(csv|xlsx)$#';

        if (preg_match($pattern, $path) !== 1 || ! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException(__('Die hochgeladene Datei ist nicht mehr vorhanden. Bitte erneut hochladen.'));
        }

        return $path;
    }

    private function format(): string
    {
        return app(TopicListImporter::class)->formatOf($this->storedFile());
    }

    /**
     * Feld => Spaltenindex aus der Auswahl in Schritt 2.
     *
     * @return array<string, int>
     */
    private function columnMap(): array
    {
        $map = [];

        foreach ((array) ($this->data['map'] ?? []) as $index => $target) {
            if (is_string($target) && $target !== 'skip' && array_key_exists($target, $this->targets())) {
                $map[$target] ??= (int) $index;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function targets(): array
    {
        return [
            TopicRowParser::FIELD_QUESTION => __('Thema'),
            TopicRowParser::FIELD_CATEGORY => __('Kategorie'),
            TopicRowParser::FIELD_HEADINGS => __('Überschriften'),
            TopicRowParser::FIELD_NOTES => __('Notiz'),
            TopicRowParser::FIELD_PRIORITY => __('Priorität'),
            TopicRowParser::FIELD_REFRESH_INTERVAL => __('Prüfabstand (Tage)'),
            'skip' => __('Nicht übernehmen'),
        ];
    }

    /**
     * Beispielwerte einer Spalte; Platzhalter als Marke, unbekannte mit Hinweis.
     *
     * @param  array{head: string, samples: list<string>}  $column
     */
    private function sampleLine(array $column, bool $hasHeader): HtmlString
    {
        $values = $hasHeader ? $column['samples'] : [$column['head'], ...array_slice($column['samples'], 0, 2)];
        $resolver = app(PlaceholderResolver::class);
        $unknown = [];

        $html = collect($values)
            ->filter(fn (string $value): bool => $value !== '')
            ->map(function (string $value) use ($resolver, &$unknown): string {
                $unknown = [...$unknown, ...$resolver->unknown($value)];

                return (string) preg_replace_callback(
                    '/\{\{?\s*[a-zA-Z_äöüÄÖÜ]+\s*\}?\}/u',
                    fn (array $match): string => $resolver->unknown(html_entity_decode($match[0])) === []
                        ? '<mark class="content-placeholder">'.$match[0].'</mark>'
                        : '<mark class="content-placeholder content-placeholder--missing">'.$match[0].'</mark>',
                    e($value),
                );
            })
            ->implode(' · ');

        foreach (array_unique($unknown) as $placeholder) {
            $html .= '<br><span class="text-status-failed-fg">'.e(__('Unbekannter Platzhalter :name — Zeile wird übersprungen.', ['name' => $placeholder])).'</span>';
        }

        return new HtmlString($html !== '' ? $html : e(__('(leer)')));
    }

    /**
     * @return array<string, string>
     */
    private function branchOptions(): array
    {
        return collect(app(TopicDirectory::class)->portals())
            ->pluck('branch')
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $branch): array => [$branch => $branch])
            ->all();
    }

    /**
     * Portale der gewaehlten Branche, sonst das oben gewaehlte Portal.
     *
     * @return list<string>
     */
    private function defaultTenants(): array
    {
        $directory = app(TopicDirectory::class);
        $branch = $this->data['branch'] ?? null;

        if (filled($branch)) {
            return collect($directory->portals())
                ->where('branch', $branch)
                ->map(fn (array $portal): string => (string) $portal['tenant_id'])
                ->values()
                ->all();
        }

        return $directory->isNetworkWide() ? [] : $directory->tenants()->keys()->map(fn (int $id): string => (string) $id)->all();
    }

    private function defaultPreviewTenant(): ?int
    {
        $defaults = $this->defaultTenants();

        if ($defaults !== []) {
            return (int) $defaults[0];
        }

        return collect(app(TopicDirectory::class)->portals())->first()['tenant_id'] ?? null;
    }

    private function remember(): void
    {
        $state = $this->data;
        unset($state['upload']);
        $state['stored_path'] = $this->storedPath;

        Cache::put($this->stateKey(), $state, now()->addDays(self::STATE_TTL_DAYS));
    }

    private function stateKey(): string
    {
        return 'guide:import-wizard:'.Filament::auth()->id();
    }

    /**
     * Bricht den Schrittwechsel mit einer Meldung ab.
     *
     * @throws Halt
     */
    private function fail(string $message): never
    {
        Notification::make()->title($message)->danger()->persistent()->send();

        throw new Halt;
    }
}
