<?php

declare(strict_types=1);

namespace App\Ads\Actions;

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPlacementStatus;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdUnit;
use App\Ads\Observers\AdCacheInvalidator;
use App\Ads\Support\AdSlotCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eine Belegung anlegen oder aendern (Ticket #8, docs/ad-slots.md 4.2/4.3).
 *
 * Alles, was an einem Speichervorgang mehr haengt als `$model->save()`, steht
 * hier — nicht in der Livewire-Komponente:
 *
 * * **Portal-Scope in einem Schritt.** `applies_to_all_tenants` und der Pivot
 *   sind zwei Darstellungen derselben Aussage; wird der Schalter gesetzt,
 *   muss der Pivot leer sein, sonst stehen dort Portale, die niemand mehr
 *   sieht.
 * * **Cache nach dem Pivot.** `ad_placement_tenant` loest kein
 *   Model-Ereignis aus (docs/ad-slots.md 8). Der Observer am Model sieht beim
 *   `saved` die **alte** Portal-Liste; geleert wird deshalb hier noch einmal,
 *   mit den Portalen vor **und** nach der Aenderung — sonst behaelt ein
 *   abgewaehltes Portal seine Anzeige bis zu einer Stunde.
 * * **Konflikt-Hinweis statt Verbot.** Pro Anker, Geraet und Portal sind
 *   mehrere Belegungen erlaubt; welche laeuft, entscheidet `priority`
 *   (docs/ad-slots.md 4.3). Zwei aktive Belegungen mit **gleicher**
 *   Prioritaet sind trotzdem fast immer ein Versehen — dann entscheidet die
 *   groessere `id`, also der Zufall der Eingabereihenfolge. Gemeldet wird das
 *   vor dem Speichern ({@see conflicts()}), geblockt nicht.
 */
final class SavePlacement
{
    /**
     * @param  array<string, mixed>  $data  Formularstand des Panels
     */
    public function execute(string $slotKey, array $data, ?AdPlacement $placement = null): AdPlacement
    {
        $placement ??= new AdPlacement(['created_by' => auth()->id()]);

        // Portale **vor** der Aenderung: ein abgewaehltes Portal taucht nach
        // dem sync() nirgends mehr auf und bekaeme sonst keine Invalidierung.
        $vorher = $placement->exists
            ? $placement->tenants()->pluck('tenants.id')->all()
            : [];

        $alleTenants = (bool) ($data['applies_to_all_tenants'] ?? false);
        $tenantIds = $alleTenants ? [] : $this->tenantIds($data['tenant_ids'] ?? []);

        DB::transaction(function () use ($placement, $slotKey, $data, $alleTenants, $tenantIds): void {
            $placement->fill([
                'ad_unit_id' => (int) $data['ad_unit_id'],
                'slot_key' => $slotKey,
                'device' => (string) ($data['device'] ?? AdDevice::ALL->value),
                'status' => (string) ($data['status'] ?? AdPlacementStatus::DRAFT->value),
                'priority' => (int) ($data['priority'] ?? 0),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'applies_to_all_tenants' => $alleTenants,
            ]);

            // `page_type` und die Geraeteverengung setzt das Model aus dem
            // Katalog; `slot_key` und `device` prueft es dort ebenfalls.
            $placement->save();
            $placement->tenants()->sync($tenantIds);
        });

        $placement->load(['adUnit', 'tenants:id,name']);

        AdCacheInvalidator::forPlacement($placement, $vorher);

        return $placement;
    }

    /**
     * Statuswechsel einer bestehenden Belegung — derselbe Weg wie in der
     * Massen-Ansicht (#10): speichern loest den Observer aus, der Pivot
     * braucht den zusaetzlichen Aufruf.
     */
    public function changeStatus(AdPlacement $placement, AdPlacementStatus $status): AdPlacement
    {
        $placement->status = $status;
        $placement->save();

        AdCacheInvalidator::forPlacement($placement);

        return $placement;
    }

    /**
     * Aktive Belegungen, die sich mit diesem Stand um denselben Anker, dasselbe
     * Geraet und dieselben Portale streiten — bei **gleicher** Prioritaet.
     *
     * Geraete ueberschneiden sich, wenn eine Seite `all` ist. Portale
     * ueberschneiden sich, wenn eine Seite netzweit gilt oder die Pivot-Listen
     * einen gemeinsamen Eintrag haben.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, AdPlacement>
     */
    public function conflicts(string $slotKey, array $data, ?AdPlacement $ignore = null): Collection
    {
        $status = AdPlacementStatus::tryFrom((string) ($data['status'] ?? ''));

        // Ein Entwurf oder eine pausierte Belegung spielt nichts aus und
        // streitet mit niemandem.
        if ($status?->isDeliverable() !== true) {
            return new Collection;
        }

        $device = AdDevice::tryFrom((string) ($data['device'] ?? '')) ?? AdDevice::ALL;
        $device = $device->narrowTo(AdSlotCatalog::deviceOf($slotKey));
        $alleTenants = (bool) ($data['applies_to_all_tenants'] ?? false);
        $tenantIds = $alleTenants ? [] : $this->tenantIds($data['tenant_ids'] ?? []);
        $ignoreId = $ignore?->exists === true ? $ignore->getKey() : null;

        $query = AdPlacement::query()
            ->with(['adUnit', 'tenants:id,name'])
            ->where('slot_key', $slotKey)
            ->active()
            ->where('priority', (int) ($data['priority'] ?? 0))
            ->when(
                $ignoreId !== null,
                fn (Builder $q) => $q->whereKeyNot($ignoreId),
            );

        if ($device !== AdDevice::ALL) {
            $query->whereIn('device', [$device->value, AdDevice::ALL->value]);
        }

        $query->where(function (Builder $q) use ($alleTenants, $tenantIds): void {
            $q->where('applies_to_all_tenants', true);

            // Netzweit trifft jedes Portal — also jede Belegung, die
            // ueberhaupt irgendwo ausgespielt wird.
            $alleTenants
                ? $q->orWhereHas('tenants')
                : $q->orWhereHas('tenants', fn (Builder $t) => $t->whereIn('tenants.id', $tenantIds));
        });

        return $query->resolutionOrder()->get();
    }

    /**
     * Passt das Format des Werbemittels zu dem, was der Anker reserviert?
     *
     * Kein Verbot, sondern ein Satz: die Hoehenreservierung haelt das Layout
     * nur ruhig, solange das Werbemittel ein Format hat, das der Anker
     * zulaesst — ein 300 × 250 in einem 90-px-Banner hat in der Messung CLS
     * 0,0442 erzeugt (docs/ad-slots.md 7). Der Altbestand (#15) ist damit
     * weiter speicherbar, der Fall aber sichtbar.
     */
    public function formatHint(string $slotKey, ?AdUnit $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        $erlaubt = AdSlotCatalog::formatsOf($slotKey);
        $format = $unit->format();

        if ($erlaubt === [] || in_array($format, $erlaubt, true)) {
            return null;
        }

        return __('Das Werbemittel hat das Format :format; dieser Anker reserviert Platz für :erlaubt. Ein größeres Format verschiebt beim Einblenden das Layout.', [
            'format' => (string) config("ad-slots.formats.{$format}.label", $format),
            'erlaubt' => implode(', ', array_map(
                fn (string $key): string => (string) config("ad-slots.formats.{$key}.label", $key),
                $erlaubt,
            )),
        ]);
    }

    /**
     * @return list<int>
     */
    private function tenantIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(
            'intval',
            array_filter((array) $ids, fn ($id): bool => filled($id)),
        )));
    }
}
