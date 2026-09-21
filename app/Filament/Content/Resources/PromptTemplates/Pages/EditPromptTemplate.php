<?php

declare(strict_types=1);

namespace App\Filament\Content\Resources\PromptTemplates\Pages;

use App\Filament\Content\Resources\PromptTemplates\PromptTemplateResource;
use App\Guide\Models\Central\PromptTemplate;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Prompt-Editor (#20).
 *
 * Speichern aendert die geoeffnete Zeile nicht, sondern legt die naechste
 * Version an und schaltet die bisherige inaktiv. Damit bleibt nachlesbar,
 * mit welchem Text ein Artikel erzeugt wurde — bei einem ueberschriebenen
 * Prompt waere jede Rueckfrage zu einem alten Artikel unbeantwortbar.
 *
 * Die Vorschau der eingesetzten Werte steht seit #43 als zweite Spalte im
 * Formular (design/content-dashboard.md, §7) und nicht mehr als Dialog —
 * PromptTemplateResource::preview() liefert sie.
 */
class EditPromptTemplate extends EditRecord
{
    protected static string $resource = PromptTemplateResource::class;

    /**
     * Die beim Speichern erzeugte neue Version. Bestimmt das Ziel der
     * Weiterleitung.
     */
    private ?PromptTemplate $createdVersion = null;

    public function getHeading(): string|Htmlable
    {
        /** @var PromptTemplate $record */
        $record = $this->getRecord();

        return __(':name (Version :version)', ['name' => $record->name, 'version' => $record->version]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Speichern erzeugt Version :next. Die aktuelle Fassung bleibt abrufbar.', [
            'next' => $this->nextVersion(),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->historyAction(),
        ];
    }

    /**
     * Historie desselben Schluessels — mit der Moeglichkeit, eine aeltere
     * Fassung als neue Version zurueckzuholen.
     */
    private function historyAction(): Action
    {
        return Action::make('history')
            ->label(__('Historie'))
            ->color('gray')
            ->icon('heroicon-o-clock')
            ->modalHeading(__('Frühere Versionen'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Schließen'))
            ->modalContent(fn (): View => view('content.partials.prompt-history', [
                'versions' => $this->versions(),
                'current' => (int) $this->getRecord()->getKey(),
                'resource' => static::getResource(),
            ]));
    }

    /**
     * Statt zu aktualisieren wird die naechste Version angelegt.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->createdVersion = $this->storeVersion($data);

        // Filament erwartet den bearbeiteten Datensatz zurueck. Der bleibt
        // unveraendert stehen — nur nicht mehr aktiv.
        return $record->refresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->createdVersion !== null
            ? __('Version :version gespeichert.', ['version' => $this->createdVersion->version])
            : __('Gespeichert.');
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->createdVersion !== null
            ? static::getResource()::getUrl('edit', ['record' => $this->createdVersion])
            : null;
    }

    /**
     * Neue Version anlegen und die bisherigen desselben Schluessels und
     * Portals inaktiv schalten. Nur eine Fassung je Schluessel ist aktiv —
     * PromptTemplate::scopeResolve() verlaesst sich darauf.
     *
     * @param  array<string, mixed>  $data
     */
    private function storeVersion(array $data): PromptTemplate
    {
        /** @var PromptTemplate $record */
        $record = $this->getRecord();

        $key = (string) ($data['key'] ?? $record->key);
        $tenantId = $data['tenant_id'] ?? $record->tenant_id;

        $version = (int) PromptTemplate::query()
            ->where('key', $key)
            ->where(fn ($query) => $tenantId === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $tenantId))
            ->max('version') + 1;

        $isActive = (bool) ($data['is_active'] ?? true);

        if ($isActive) {
            PromptTemplate::query()
                ->where('key', $key)
                ->where(fn ($query) => $tenantId === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $tenantId))
                ->update(['is_active' => false]);
        }

        return PromptTemplate::query()->create([
            'key' => $key,
            'tenant_id' => $tenantId,
            'version' => $version,
            'name' => (string) ($data['name'] ?? $record->name),
            'system_prompt' => $data['system_prompt'] ?? $record->system_prompt,
            'user_prompt' => (string) ($data['user_prompt'] ?? $record->user_prompt),
            'variables_json' => $data['variables_json'] ?? $record->variables_json,
            // Ausschliesslich vom Datensatz, nie aus $data (§7b.1 Abschnitt
            // 4): das Schema ist kein Formularfeld mehr. Ein gesperrtes Feld,
            // dessen Wert trotzdem uebernommen wird, ist eine Sperre bis zum
            // ersten manipulierten Formular.
            'output_schema_json' => $record->output_schema_json,
            'is_active' => $isActive,
            'created_by' => filament()->auth()->id(),
        ]);
    }

    private function nextVersion(): int
    {
        /** @var PromptTemplate $record */
        $record = $this->getRecord();

        return (int) PromptTemplate::query()
            ->where('key', $record->key)
            ->where(fn ($query) => $record->tenant_id === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $record->tenant_id))
            ->max('version') + 1;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PromptTemplate>
     */
    private function versions()
    {
        /** @var PromptTemplate $record */
        $record = $this->getRecord();

        return PromptTemplate::query()
            ->with('author')
            ->where('key', $record->key)
            ->where(fn ($query) => $record->tenant_id === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $record->tenant_id))
            ->orderByDesc('version')
            ->get();
    }
}
