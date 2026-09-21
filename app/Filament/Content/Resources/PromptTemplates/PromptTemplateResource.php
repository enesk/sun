<?php

declare(strict_types=1);

namespace App\Filament\Content\Resources\PromptTemplates;

use App\Filament\Content\Forms\Components\PromptCodeEditor;
use App\Filament\Content\Resources\PromptTemplates\Pages\EditPromptTemplate;
use App\Filament\Content\Resources\PromptTemplates\Pages\ListPromptTemplates;
use App\Guide\Llm\TemplateRenderer;
use App\Guide\Models\Central\PromptTemplate;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Prompt-Editor (#20) fuer die versionierten Vorlagen aus #13.
 *
 * Kein Navigationspunkt: die Vorlagen stehen als Reiter "Prompts" unter den
 * Einstellungen (design/content-dashboard.md, §7). Speichern aendert eine
 * Version nie, sondern legt die naechste an und schaltet die vorherige
 * inaktiv — der Text, mit dem ein Artikel erzeugt wurde, bleibt damit
 * nachlesbar.
 */
class PromptTemplateResource extends Resource
{
    protected static ?string $model = PromptTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $slug = 'einstellungen/prompts';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('Prompt-Vorlage');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Prompt-Vorlagen');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canManageContentSettings();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Baender ueber dem Editor (§7b.1 Abschnitt 6): erst "Schema
            // veraltet"/"Schema fehlt", darunter das Wirkungsband aus §7.
            View::make('content.partials.prompt-schema')
                ->viewData(fn (Get $get, ?PromptTemplate $record): array => static::schemaViewData(
                    'banners',
                    $get('key'),
                    $record?->output_schema_json,
                )),

            Section::make(__('Vorlage'))
                ->schema([
                    TextInput::make('key')
                        ->label(__('Schlüssel'))
                        ->required()
                        ->maxLength(64)
                        ->helperText(__('Stabiler Name der Stufe, z. B. outline oder quality_rubric. Wird nicht mit der Version geändert.')),
                    TextInput::make('name')
                        ->label(__('Bezeichnung'))
                        ->required()
                        ->maxLength(255),
                    Select::make('tenant_id')
                        // Ohne __(): "Portal" loest auf die Gruppe lang/de/portal.php auf.
                        ->label('Portal')
                        ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder(__('Für alle Portale'))
                        ->helperText(__('Eine portalspezifische Vorlage schlägt die globale.')),
                    Toggle::make('is_active')
                        ->label(__('Aktiv'))
                        ->default(true),
                ])
                ->columns(2),

            Section::make(__('Prompt'))
                ->description(__('Platzhalter in doppelten geschweiften Klammern, z. B. {{styleguide}}. Der Text wird nicht ausgeführt, nur eingesetzt.'))
                ->schema([
                    // Zweispaltig nach design/content-dashboard.md, §7: links
                    // der Text, rechts dieselben Zeilen mit eingesetzten
                    // Beispielwerten.
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            Group::make([
                                // Eigener Editor (#54): faerbt {{var}} danach
                                // ein, ob variables_json einen Beispielwert
                                // dazu hat. Sonst wie CodeEditor aus #43.
                                PromptCodeEditor::make('system_prompt')
                                    ->label(__('System-Prompt'))
                                    ->wrap()
                                    ->live(onBlur: true),
                                PromptCodeEditor::make('user_prompt')
                                    ->label(__('User-Prompt'))
                                    ->required()
                                    ->wrap()
                                    ->live(onBlur: true),
                            ]),
                            View::make('content.partials.prompt-preview')
                                ->viewData(fn (Get $get): array => [
                                    'result' => static::preview([
                                        'key' => $get('key'),
                                        'name' => $get('name'),
                                        'system_prompt' => $get('system_prompt'),
                                        'user_prompt' => $get('user_prompt'),
                                        'variables_json' => $get('variables_json'),
                                    ]),
                                ]),
                        ]),
                ]),

            Section::make(__('Variablen und Ausgabeschema'))
                ->schema([
                    CodeEditor::make('variables_json')
                        ->label(__('Erwartete Variablen'))
                        ->language(Language::Json)
                        ->helperText(__('JSON-Objekt: Variablenname → Beispielwert. Die Beispielwerte speisen die Vorschau.'))
                        ->formatStateUsing(fn ($state): string => static::encode($state))
                        ->dehydrateStateUsing(fn ($state) => static::decode($state))
                        ->live(onBlur: true)
                        // Der aeussere Aufruf ist die Filament-Auswertung; sie
                        // liefert erst die eigentliche Validierungs-Closure.
                        ->rule(static fn (): \Closure => self::jsonObjectRule()),
                    // Kein Formularfeld mehr (§7b.1 Abschnitt 4): das Schema
                    // ist mit der Pipeline verdrahtet, nicht redaktionell.
                    // Es wird nur gelesen und nie dehydriert — ein gesperrtes
                    // Feld, dessen Wert trotzdem mitgeht, ist eine Sperre bis
                    // zum ersten manipulierten Formular.
                    View::make('content.partials.prompt-schema')
                        ->viewData(fn (Get $get, ?PromptTemplate $record): array => static::schemaViewData(
                            'field',
                            $get('key'),
                            $record?->output_schema_json,
                        )),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tenant'))
            ->columns([
                TextColumn::make('key')
                    ->label(__('Schlüssel'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Bezeichnung'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('tenant.name')
                    ->label('Portal')
                    ->placeholder(__('Alle Portale')),
                TextColumn::make('version')
                    ->label(__('Version'))
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label(__('Zustand'))
                    ->badge()
                    // Zwei Abzeichen nebeneinander, das zweite nur bei
                    // unpassendem Schema (§7b.1 Abschnitt 7). ->wrap() gibt
                    // der Zelle flex-wrap mit Zeilenabstand.
                    ->wrap()
                    ->state(fn (PromptTemplate $record): array => static::stateBadges($record))
                    // Benannte Statusfarben statt generischer Rollen (#30).
                    ->color(fn (string $state): string => match ($state) {
                        'stale' => 'status-failed',
                        'active' => 'status-published',
                        default => 'status-archived',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stale' => __('Schema veraltet'),
                        'active' => __('aktiv'),
                        default => __('abgelöst'),
                    })
                    // Zugabe, keine Information: der Text steht sichtbar in
                    // der Zelle und braucht kein Zeigergeraet.
                    ->extraAttributes(fn (PromptTemplate $record): array => static::isSchemaStale($record)
                        ? ['title' => __('Wird beim Lauf durch das Schema aus dem Code ersetzt.')]
                        : []),
                TextColumn::make('updated_at')
                    ->label(__('Geändert'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label(__('Bearbeiter'))
                    ->placeholder('—'),
            ])
            ->defaultSort('key')
            ->filters([
                SelectFilter::make('key')
                    ->label(__('Schlüssel'))
                    ->options(fn (): array => PromptTemplate::query()
                        ->distinct()
                        ->orderBy('key')
                        ->pluck('key', 'key')
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label(__('Zustand'))
                    ->placeholder(__('Alle'))
                    ->trueLabel(__('Nur aktive'))
                    ->falseLabel(__('Nur abgelöste'))
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make()->label(__('Bearbeiten')),
            ]);
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPromptTemplates::route('/'),
            'edit' => EditPromptTemplate::route('/{record}/bearbeiten'),
        ];
    }

    /**
     * Abzeichen der Spalte "Zustand": immer der Versionszustand, daneben bei
     * Fall A mit unpassendem Schema "Schema veraltet". Ohne Abfrage — die
     * Antwort steht im Vertrag.
     *
     * @return array<int, string>
     */
    public static function stateBadges(PromptTemplate $record): array
    {
        $badges = [$record->is_active ? 'active' : 'superseded'];

        if (static::isSchemaStale($record)) {
            $badges[] = 'stale';
        }

        return $badges;
    }

    /**
     * Fall A (Schemavertrag im Code) gibt es seit dem Rueckbau der alten
     * Pipeline nicht mehr (#35); Fall B und C tragen nie ein Abzeichen.
     */
    public static function isSchemaStale(PromptTemplate $record): bool
    {
        return false;
    }

    /**
     * Daten fuer `content.partials.prompt-schema` (§7b.1 Abschnitt 3).
     *
     * Drei Faelle statt zwei: A — Vertrag vorhanden, B — kein Vertrag und
     * kein Schema, C — Schema ohne Leser (`refresh_update`, der Refresh-Lauf
     * aus #24 ist nicht gebaut). Auch in Fall C bleibt eine Aenderung
     * folgenlos, also darf das Feld nicht aenderbar aussehen.
     *
     * @param  array<string, mixed>|null  $schema
     * @return array{part: string, case: string, json: string, class: ?string, stale: bool, hasSchema: bool}
     */
    public static function schemaViewData(string $part, mixed $key, ?array $schema): array
    {
        $hasSchema = $schema !== null && $schema !== [];

        return [
            'part' => $part,
            'case' => $hasSchema ? 'C' : 'B',
            'json' => $hasSchema ? static::encode($schema) : '',
            'class' => null,
            'stale' => false,
            'hasSchema' => $hasSchema,
        ];
    }

    /**
     * Vorschau der eingesetzten Werte fuer die zweite Spalte des Editors
     * (design/content-dashboard.md, §7).
     *
     * Es wird ausdruecklich kein Modell aufgerufen: die Vorschau beantwortet,
     * ob die Platzhalter aufgehen, nicht, ob die Antwort gut ist. Genau das
     * ist der Fehler, der in der Praxis passiert — und er kostet hier nichts.
     * Die eingesetzten Werte sind im Ergebnis hervorgehoben; der Editor
     * selbst markiert seit #54 die Platzhalter.
     *
     * @param  array<string, mixed>  $state
     * @return array{system: ?HtmlString, user: ?HtmlString, missing: array<int, string>, error: ?string, variables: array<string, mixed>}
     */
    public static function preview(array $state): array
    {
        $template = new PromptTemplate([
            'key' => (string) ($state['key'] ?? ''),
            'name' => (string) ($state['name'] ?? ''),
            'system_prompt' => $state['system_prompt'] ?? null,
            'user_prompt' => (string) ($state['user_prompt'] ?? ''),
            'variables_json' => static::decode($state['variables_json'] ?? null),
        ]);

        $variables = $template->variables_json ?? [];
        $renderer = app(TemplateRenderer::class);
        $missing = $renderer->missingVariables($template, $variables);

        if ($missing !== []) {
            return ['system' => null, 'user' => null, 'missing' => $missing, 'error' => null, 'variables' => $variables];
        }

        try {
            $system = trim((string) $template->system_prompt) === ''
                ? null
                : self::highlight((string) $template->system_prompt, $variables, $renderer);
            $user = self::highlight((string) $template->user_prompt, $variables, $renderer);
        } catch (Throwable $exception) {
            return ['system' => null, 'user' => null, 'missing' => [], 'error' => $exception->getMessage(), 'variables' => $variables];
        }

        return [
            'system' => $system,
            'user' => $user,
            'missing' => [],
            'error' => null,
            'variables' => $variables,
        ];
    }

    /**
     * Setzt die Beispielwerte ein und markiert jeden eingesetzten Wert.
     *
     * Der Text wird vorher escaped, danach kommt nur noch das eigene Markup
     * dazu — Prompttext ist Redaktionsinhalt und darf kein HTML mitbringen.
     *
     * @param  array<string, mixed>  $variables
     */
    private static function highlight(string $text, array $variables, TemplateRenderer $renderer): HtmlString
    {
        return new HtmlString($renderer->renderWith(
            e($text),
            $variables,
            static fn (string $value): string => '<mark class="rounded-content-sm px-content-1" style="background: var(--color-content-100); color: var(--color-content-900)">'.e($value).'</mark>',
        ));
    }

    /**
     * @param  mixed  $state
     */
    public static function encode($state): string
    {
        if ($state === null || $state === '' || $state === []) {
            return '';
        }

        if (is_string($state)) {
            return $state;
        }

        return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  mixed  $state
     * @return array<string, mixed>|null
     */
    public static function decode($state): ?array
    {
        if (is_array($state)) {
            return $state === [] ? null : $state;
        }

        $value = trim((string) $state);

        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Ein Feld, das beim Speichern still zu null wird, weil das JSON kaputt
     * ist, waere die schlimmste Variante — deshalb wird hier abgelehnt.
     */
    private static function jsonObjectRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_array($value) || $value === null || trim((string) $value) === '') {
                return;
            }

            if (! is_array(json_decode((string) $value, true))) {
                $fail(__('Das Feld enthält kein gültiges JSON-Objekt.'));
            }
        };
    }

    /**
     * Zusaetzlich zur JSON-Syntax: ein Ausgabeschema ohne `type` oder
     * `properties` ist kein JSON Schema und wuerde den LlmClient (#6) beim
     * ersten Lauf ins Leere laufen lassen.
     *
     * Bleibt bestehen (§7b.1 Abschnitt 9), obwohl seit #58 kein Formularfeld
     * mehr am Ausgabeschema haengt: sie wird nicht verschaerft und ist wieder
     * anzuhaengen, sobald ein Schema-Feld entsteht. Was sie nicht pruefen
     * kann, prueft das Guide-Schema der nutzenden Klasse zur Laufzeit.
     */
    public static function jsonSchemaRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $decoded = is_array($value) ? $value : json_decode((string) $value, true);

            if ($value === null || (is_string($value) && trim($value) === '')) {
                return;
            }

            if (! is_array($decoded)) {
                $fail(__('Das Feld enthält kein gültiges JSON-Objekt.'));

                return;
            }

            if (! isset($decoded['type']) && ! isset($decoded['properties'])) {
                $fail(__('Das Ausgabeschema braucht mindestens "type" oder "properties".'));
            }
        };
    }
}
