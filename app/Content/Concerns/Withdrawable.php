<?php

declare(strict_types=1);

namespace App\Content\Concerns;

use App\Content\Enums\DisplayStatus;
use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Events\ContentRepublished;
use App\Content\Events\ContentWithdrawn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;

/**
 * Zurueckziehen (Depublizieren/Verwerfen) eines Entwurfs oder Artikels.
 *
 * Bewusst kein weiterer DraftStatus-Fall: Der Pipeline-Status sagt weiterhin,
 * wie weit die Erstellung gekommen ist, waehrend `withdrawn_at` eine Ebene
 * darueber liegt und jeden Pipeline-Status ueberschreibt. Damit bleibt der
 * Uebergangsgraph aus DraftStatus::allowedTransitions() unveraendert gueltig,
 * und ein zurueckgezogener Artikel behaelt seinen Fingerprint (das Thema wird
 * nicht sofort neu erzeugt).
 *
 * Erwartete Spalten (siehe Migration
 * database/migrations/tenant/..._add_withdrawal_columns_to_content_tables.php):
 * `withdrawn_at`, `withdrawn_reason`, `withdrawn_by`.
 *
 * `withdrawn_by` ist eine `users.id` der Central-DB (der im Content-Panel
 * angemeldete Administrator); der Spaltenkommentar der Migration nennt noch
 * die alte Tabelle `content_users`, die es seit #139 nicht mehr gibt.
 *
 * @property \Illuminate\Support\Carbon|null $withdrawn_at
 * @property string|null $withdrawn_reason
 * @property int|null $withdrawn_by
 * @property-read DisplayStatus $display_status
 */
trait Withdrawable
{
    public function initializeWithdrawable(): void
    {
        $this->mergeCasts(['withdrawn_at' => 'datetime']);
        $this->mergeFillable(['withdrawn_at', 'withdrawn_reason', 'withdrawn_by']);
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /**
     * Anzeige-Status inklusive Zuruecknahme. Einziger Weg, aus dem der
     * Anzeige-Status eines Modells gelesen werden darf (Epic #1, Abschnitt 4).
     */
    protected function displayStatus(): Attribute
    {
        return Attribute::get(function (): DisplayStatus {
            $status = $this->getAttribute('status');

            if ($status instanceof DraftStatus) {
                return DisplayStatus::fromDraft($status, $this->isWithdrawn());
            }

            if ($status instanceof TopicStatus) {
                return $this->isWithdrawn()
                    ? DisplayStatus::ARCHIVED
                    : DisplayStatus::fromTopic($status);
            }

            // Modelle ohne Pipeline-Status (z. B. content_articles) sind
            // veroeffentlicht, solange sie nicht zurueckgezogen wurden.
            return $this->isWithdrawn() ? DisplayStatus::ARCHIVED : DisplayStatus::PUBLISHED;
        })->shouldCache();
    }

    /**
     * Zurueckziehen mit Pflichtgrund im Klartext. Der Grund steht spaeter
     * unveraendert in der Pruefung und in der Artikelliste (#20).
     *
     * @throws InvalidArgumentException wenn der Grund zu kurz ist
     */
    public function withdraw(string $reason, ?int $userId = null): bool
    {
        $reason = trim($reason);
        $minLength = (int) config('content.withdrawal.reason_min_length', 10);

        if (mb_strlen($reason) < $minLength) {
            throw new InvalidArgumentException(
                __('Bitte einen Grund mit mindestens :count Zeichen angeben.', ['count' => $minLength])
            );
        }

        if ($this->isWithdrawn()) {
            return false;
        }

        $this->forceFill([
            'withdrawn_at' => now(),
            'withdrawn_reason' => $reason,
            'withdrawn_by' => $userId ?? auth()->id(),
        ])->save();

        $this->afterWithdraw($reason);

        ContentWithdrawn::dispatch($this, $reason);

        return true;
    }

    /**
     * Zuruecknahme aufheben. Der Pipeline-Status bleibt unberuehrt, weil er
     * waehrend der Zuruecknahme nie veraendert wurde.
     */
    public function republish(): bool
    {
        if (! $this->isWithdrawn()) {
            return false;
        }

        $this->forceFill([
            'withdrawn_at' => null,
            'withdrawn_reason' => null,
            'withdrawn_by' => null,
        ])->save();

        $this->afterRepublish();

        ContentRepublished::dispatch($this);

        return true;
    }

    /**
     * Haken fuer Modelle, die ausser den eigenen Spalten noch etwas
     * mitfuehren muessen (z. B. den veroeffentlichten Beitrag archivieren).
     * Laeuft synchron vor dem Event, damit das Frontend sofort stimmt.
     */
    protected function afterWithdraw(string $reason): void {}

    protected function afterRepublish(): void {}

    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('withdrawn_at'));
    }

    /**
     * Standardfilter fuer Frontend, Sitemap, Feed und Refresh-Loop (#24):
     * zurueckgezogene Inhalte tauchen dort nicht mehr auf.
     */
    public function scopeNotWithdrawn(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('withdrawn_at'));
    }
}
