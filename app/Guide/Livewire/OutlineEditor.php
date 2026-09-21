<?php

declare(strict_types=1);

namespace App\Guide\Livewire;

use App\Guide\Enums\TopicStatus;
use App\Guide\Models\Topic;
use App\Guide\Services\TopicAdminService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\OutlineDraft;
use App\Guide\Support\Usd;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Gliederungs-Editor im Thema-Detail (#15, design/guide-dashboard.md §5.4).
 *
 * Zwei Zustaende: gesperrt (lesbar, "Entsperren …") und in Bearbeitung (auch
 * outline_pending mit dem Vorschlag des Modells). Bearbeitet wird eine flache
 * Zeilenliste (OutlineDraft::rows); Umbenennen, Verschieben, Ein- und
 * Ausruecken behalten die id einer Ueberschrift, nur neue Zeilen bekommen
 * beim Speichern eine neue. Ziehen nutzt die Sortable-Direktive aus dem
 * Filament-Bundle (x-sortable); jede Verschiebung geht auch per Tastatur
 * (Alt+Pfeiltasten) und wird ueber eine Live-Region angesagt.
 *
 * Gespeichert wird erst mit "Entwurf speichern" oder "Gliederung sperren";
 * bis dahin liegt der Stand nur in der Komponente.
 */
class OutlineEditor extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $tenantId;

    #[Locked]
    public int $topicId;

    /**
     * @var list<array{key: string, id: string|null, level: int, heading: string, original: string|null}>
     */
    public array $rows = [];

    #[Locked]
    public bool $locked = false;

    #[Locked]
    public ?string $lockedAt = null;

    #[Locked]
    public string $status = '';

    #[Locked]
    public bool $hasArticle = false;

    public bool $dirty = false;

    public string $announcement = '';

    public function mount(int $tenantId, int $topicId): void
    {
        $this->tenantId = $tenantId;
        $this->topicId = $topicId;

        $this->load();
    }

    public function load(): void
    {
        $state = $this->tenant()->run(function (): array {
            $topic = Topic::query()->findOrFail($this->topicId);

            return [
                'rows' => OutlineDraft::rows($topic->outline_json),
                'locked' => $topic->isOutlineLocked(),
                'locked_at' => $topic->outline_locked_at?->toIso8601String(),
                'status' => $topic->status->value,
                'has_article' => $topic->article_id !== null,
            ];
        });

        $this->rows = $state['rows'];
        $this->locked = $state['locked'];
        $this->lockedAt = $state['locked_at'];
        $this->status = $state['status'];
        $this->hasArticle = $state['has_article'];
        $this->dirty = false;
    }

    public function updatedRows(): void
    {
        $this->dirty = true;
    }

    public function moveUp(string $key): void
    {
        $this->move($key, -1);
    }

    public function moveDown(string $key): void
    {
        $this->move($key, 1);
    }

    public function indent(string $key): void
    {
        $this->setLevel($key, 3);
    }

    public function outdent(string $key): void
    {
        $this->setLevel($key, 2);
    }

    public function addRow(int $level): void
    {
        $this->ensureEditable();

        $row = OutlineDraft::newRow($this->rows === [] ? 2 : $level);
        $this->rows[] = $row;
        $this->dirty = true;
        $this->announce($row['key'], __('Neue Überschrift'));
    }

    /**
     * Reihenfolge nach dem Ziehen (x-sortable liefert die Zeilenschluessel).
     *
     * @param  array<int, string>  $keys
     */
    public function reorder(array $keys): void
    {
        $this->ensureEditable();

        $byKey = collect($this->rows)->keyBy('key');
        $ordered = collect($keys)->map(fn (string $key): ?array => $byKey->get($key))->filter()->values();

        // Nur uebernehmen, wenn jede Zeile genau einmal vorkommt.
        if ($ordered->count() !== count($this->rows)) {
            return;
        }

        $this->rows = $ordered->all();
        $this->dirty = true;
    }

    /**
     * Neue Zeilen direkt entfernen; vorhandene Ueberschriften ueber
     * removeAction mit Rueckfrage.
     */
    public function removeNewRow(string $key): void
    {
        $this->ensureEditable();

        $this->rows = array_values(array_filter($this->rows, fn (array $row): bool => $row['key'] !== $key || $row['id'] !== null));
        $this->dirty = true;
        $this->announcement = __('Überschrift entfernt.');
    }

    public function saveDraft(): void
    {
        $this->ensureEditable();

        app(TopicAdminService::class)->saveOutline($this->tenant(), $this->topicId, $this->rows);
        $this->load();

        Notification::make()->title(__('Gliederung gespeichert, noch nicht gesperrt.'))->success()->send();
    }

    public function discard(): void
    {
        $this->load();
        $this->announcement = __('Änderungen verworfen.');
    }

    public function lockAction(): Action
    {
        return Action::make('lock')
            ->label(__('Gliederung sperren'))
            ->icon('heroicon-o-lock-closed')
            ->disabled(fn (): bool => OutlineDraft::violations($this->rows) !== [])
            ->requiresConfirmation()
            ->modalHeading(__('Gliederung sperren'))
            ->modalDescription(fn (): string => $this->hasArticle
                ? __('Überschriften und Sprungziele werden fest. Der Artikel wird beim nächsten Lauf entlang dieser Gliederung komplett neu geschrieben (≈ :cost).', ['cost' => Usd::format(Usd::estimate('create'))])
                : __('Überschriften und Sprungziele werden fest. Der Artikel entsteht beim nächsten Lauf entlang dieser Gliederung; die Kosten sind mit der Neuanlage bereits eingeplant.'))
            ->modalSubmitActionLabel(__('Sperren'))
            ->action(function (): void {
                $this->ensureEditable();

                app(TopicAdminService::class)->saveAndLockOutline($this->tenant(), $this->topicId, $this->rows);
                $this->load();
                $this->dispatch('guide-outline-locked');

                Notification::make()->title(__('Gliederung gesperrt.'))->success()->send();
            });
    }

    public function unlockAction(): Action
    {
        return Action::make('unlock')
            ->label(__('Entsperren …'))
            ->icon('heroicon-o-lock-open')
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('status-review')
            ->modalHeading(__('Gliederung entsperren?'))
            ->modalDescription(fn (): string => __('Achtung: Nach dem erneuten Sperren wird der Artikel beim nächsten Lauf komplett neu geschrieben (≈ :cost). Die Sprungziele bestehender Überschriften bleiben erhalten, auch wenn Sie sie umbenennen.', ['cost' => Usd::format(Usd::estimate('create'))])
                .($this->hasArticle ? ' '.__('Bis dahin bleibt die veröffentlichte Fassung unverändert online.') : ''))
            ->modalSubmitActionLabel(__('Entsperren'))
            ->action(function (): void {
                app(TopicAdminService::class)->unlockOutline($this->tenant(), $this->topicId);
                $this->load();

                Notification::make()->title(__('Gliederung entsperrt.'))->warning()->send();
            });
    }

    public function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('Löschen'))
            ->icon('heroicon-m-trash')
            ->iconButton()
            ->size('sm')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Überschrift löschen?'))
            ->modalDescription(fn (array $arguments): string => __('Das Sprungziel #:id entfällt. Verweise darauf landen danach am Artikelanfang. Der Abschnitt wird aus dem Artikel entfernt.', [
                'id' => collect($this->rows)->firstWhere('key', $arguments['key'] ?? null)['id'] ?? '',
            ]))
            ->modalSubmitActionLabel(__('Löschen'))
            ->action(function (array $arguments): void {
                $this->ensureEditable();

                $this->rows = array_values(array_filter($this->rows, fn (array $row): bool => $row['key'] !== ($arguments['key'] ?? null)));
                $this->dirty = true;
                $this->announcement = __('Überschrift entfernt.');
            });
    }

    public function render(): View
    {
        return view('content.guide.outline-editor', [
            'violations' => $this->locked ? [] : OutlineDraft::violations($this->rows),
            'changed' => OutlineDraft::changedCount($this->rows),
            'isProposal' => $this->status === TopicStatus::OUTLINE_PENDING->value && $this->rows !== [],
            'lockedAtLabel' => $this->lockedAt !== null
                ? Carbon::parse($this->lockedAt)->timezone(config('guide.timezone'))->format('d.m.Y')
                : null,
            'maxLength' => OutlineDraft::MAX_HEADING_LENGTH,
        ]);
    }

    private function move(string $key, int $direction): void
    {
        $this->ensureEditable();

        $index = $this->indexOf($key);
        $target = $index + $direction;

        if ($index === null || $target < 0 || $target >= count($this->rows)) {
            return;
        }

        [$this->rows[$index], $this->rows[$target]] = [$this->rows[$target], $this->rows[$index]];
        $this->dirty = true;
        $this->announce($key);
    }

    private function setLevel(string $key, int $level): void
    {
        $this->ensureEditable();

        $index = $this->indexOf($key);

        // Die erste Ueberschrift bleibt H2.
        if ($index === null || ($level === 3 && $index === 0)) {
            return;
        }

        $this->rows[$index]['level'] = $level;
        $this->dirty = true;
        $this->announce($key);
    }

    private function indexOf(string $key): ?int
    {
        foreach ($this->rows as $index => $row) {
            if ($row['key'] === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Live-Region und Fokus nach einer Verschiebung (§5.4, Tastatur).
     */
    private function announce(string $key, ?string $prefix = null): void
    {
        $index = $this->indexOf($key);

        if ($index === null) {
            return;
        }

        $row = $this->rows[$index];

        $this->announcement = trim(($prefix !== null ? "{$prefix}: " : '').__('„:heading“ ist jetzt Überschrift :position von :total, Ebene H:level.', [
            'heading' => OutlineDraft::clean($row['heading']) !== '' ? OutlineDraft::clean($row['heading']) : __('ohne Text'),
            'position' => $index + 1,
            'total' => count($this->rows),
            'level' => $row['level'],
        ]));

        $this->dispatch('guide-outline-focus', key: $key);
    }

    private function ensureEditable(): void
    {
        abort_if($this->locked, 409, __('Die Gliederung ist gesperrt.'));
    }

    private function tenant(): Tenant
    {
        return app(TopicDirectory::class)->tenant($this->tenantId) ?? abort(403);
    }
}
