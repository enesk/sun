<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Filament\Concerns\HasHistoryTabs;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Services\TopicDirectory;
use App\Guide\Services\VersionHistory;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Verlauf › Versionen (#16, design/guide-dashboard.md §5.5, §7.2, §8.3).
 *
 * Ohne ?thema: alle in den letzten 30 Tagen veroeffentlichten Fassungen der
 * gewaehlten Portale mit Changelog-Satz. Mit ?thema=<tenant-id>-<topic-id>:
 * Versionshistorie dieses Artikels, Vergleich zweier Fassungen (?von, ?bis)
 * und Rollback. Der Rollback laeuft ueber GuidePublisher::rollback() und
 * verlangt eine Bestaetigung.
 */
class ArticleVersions extends Page
{
    use HasHistoryTabs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $slug = 'verlauf/versionen';

    /** Navigationspunkt "Verlauf" ist RunHistory (Reiter Laeufe, #33). */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'content.guide.article-versions';

    #[Url(as: 'thema')]
    public ?string $topic = null;

    #[Url(as: 'von')]
    public ?int $from = null;

    #[Url(as: 'bis')]
    public ?int $to = null;

    /** @var array<string, mixed>|null */
    protected ?array $history = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    /**
     * Link auf die Historie eines Themas (auch aus dem Thema-Detail).
     */
    public static function topicUrl(string $topicKey): string
    {
        return static::getUrl(['thema' => $topicKey]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->history() !== null ? (string) $this->history()['question'] : __('Versionen');
    }

    public function getSubheading(): ?string
    {
        return $this->history() !== null
            ? __('Alle Fassungen dieses Artikels, neueste zuerst.')
            : __('Veröffentlichte Fassungen der letzten :days Tage.', ['days' => VersionHistory::RECENT_DAYS]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function history(): ?array
    {
        if ($this->history !== null) {
            return $this->history;
        }

        [$tenant, $topicId] = $this->selectedTopic();

        return $this->history = $tenant !== null ? $this->service()->forTopic($tenant, $topicId) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(): array
    {
        return $this->service()->recent(app(TopicDirectory::class)->tenants());
    }

    /**
     * Vergleich der gewaehlten Fassungen; Vorgabe: vorletzte gegen juengste.
     *
     * @return array{from: int, to: int, rows: list<array<string, mixed>>}|null
     */
    public function comparison(): ?array
    {
        $history = $this->history();
        [$tenant, $topicId] = $this->selectedTopic();

        if ($history === null || $tenant === null || count($history['versions']) < 2) {
            return null;
        }

        $ids = array_column($history['versions'], 'id');
        $to = in_array($this->to, $ids, true) ? $this->to : $ids[0];
        $from = in_array($this->from, $ids, true) ? $this->from : ($ids[1] ?? $ids[0]);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $this->service()->compare($tenant, $topicId, $from, $to) ?? [],
        ];
    }

    public function topicDetailUrl(): ?string
    {
        return $this->topic !== null && TopicDirectory::parseKey($this->topic) !== null
            ? TopicResource::detailUrl($this->topic)
            : null;
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('Vorschau'))
            ->link()
            ->color('gray')
            ->action(function (array $arguments): void {
                [$tenant] = $this->selectedTopic();
                $url = $tenant !== null ? $this->service()->previewUrl($tenant, (int) ($arguments['version'] ?? 0)) : null;

                if ($url === null) {
                    Notification::make()->title(__('Für dieses Portal ist keine Domain hinterlegt.'))->warning()->send();

                    return;
                }

                $this->js('window.open('.json_encode($url).', "_blank", "noopener")');
            });
    }

    public function rollbackAction(): Action
    {
        return Action::make('rollback')
            ->label(__('Zurückholen …'))
            ->link()
            ->color('status-failed')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('Fassung :version zurückholen?', ['version' => $this->versionNumber((int) ($arguments['version'] ?? 0))]))
            ->modalDescription(__('Der Artikel zeigt danach wieder den Inhalt dieser Fassung. Sie wird als neue Fassung angelegt, die aktuelle bleibt in der Historie. Das Aktualisiert-Datum ändert sich nur, wenn sich der sichtbare Inhalt ändert.'))
            ->modalSubmitActionLabel(__('Zurückholen'))
            ->schema([
                TextInput::make('note')
                    ->label(__('Grund (optional, erscheint im Verlauf)'))
                    ->maxLength(300),
            ])
            ->action(function (array $data, array $arguments): void {
                [$tenant, $topicId] = $this->selectedTopic();

                try {
                    if ($tenant === null) {
                        throw new \RuntimeException(__('Thema nicht gefunden.'));
                    }

                    $version = $this->service()->rollback($tenant, $topicId, (int) ($arguments['version'] ?? 0), $data['note'] ?? null);
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                app(TopicDirectory::class)->forget();
                $this->history = null;
                $this->from = null;
                $this->to = null;

                Notification::make()
                    ->title(__('Zurückgeholt. Online ist jetzt Fassung :version.', ['version' => $version]))
                    ->success()
                    ->send();
            });
    }

    private function versionNumber(int $id): int
    {
        return (int) (collect($this->history()['versions'] ?? [])->firstWhere('id', $id)['version'] ?? 0);
    }

    /**
     * @return array{0: Tenant|null, 1: int}
     */
    private function selectedTopic(): array
    {
        $parsed = $this->topic !== null ? TopicDirectory::parseKey($this->topic) : null;

        if ($parsed === null) {
            return [null, 0];
        }

        return [app(TopicDirectory::class)->tenant($parsed[0]), $parsed[1]];
    }

    private function service(): VersionHistory
    {
        return app(VersionHistory::class);
    }
}
