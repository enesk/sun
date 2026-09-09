<?php

declare(strict_types=1);

namespace App\Content\Enums;

/**
 * Anzeige-Status eines Objekts der Content-Pipeline.
 *
 * Diese sieben Zustaende sind die einzige Statusmenge, die im Content-Dashboard,
 * im Redaktionskalender, in Tabellen, in der Leistungsansicht und in E-Mails
 * gezeigt werden darf (Epic #1, Abschnitt 4 des UX-Leitbilds).
 *
 * Die feineren internen Zustaende der Pipeline (siehe TopicStatus und
 * DraftStatus) werden hierauf abgebildet und duerfen nur als Fortschritt
 * *innerhalb* eines Anzeige-Status dargestellt werden. Ein Ticket, das einen
 * weiteren Anzeige-Status einfuehrt, bricht die Klammer des Vorhabens.
 *
 * idea -> scheduled -> generating -> review -> published
 *                                          \-> failed
 *                                          \-> archived (verworfen/depubliziert)
 */
enum DisplayStatus: string
{
    case IDEA = 'idea';
    case SCHEDULED = 'scheduled';
    case GENERATING = 'generating';
    case REVIEW = 'review';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case ARCHIVED = 'archived';

    /**
     * Beschriftung in der Sprache der Redaktion, nicht der Pipeline.
     */
    public function label(): string
    {
        return match ($this) {
            self::IDEA => __('Themenvorschlag'),
            self::SCHEDULED => __('Eingeplant'),
            self::GENERATING => __('In Erstellung'),
            self::REVIEW => __('Zur Prüfung'),
            self::PUBLISHED => __('Veröffentlicht'),
            self::FAILED => __('Fehlgeschlagen'),
            self::ARCHIVED => __('Zurückgezogen'),
        };
    }

    /**
     * Benannte Filament-Farbe des Content-Panels, registriert aus
     * config('content.colors') im ContentPanelProvider (Ticket #30).
     *
     * Bewusst KEINE generischen Rollen: `primary` ist im Content-Panel die
     * Teal-Markenfarbe, ein Status in Markenfarbe verschmilzt mit
     * Schaltflaechen, aktiver Navigation und Fokusring. info/warning/
     * success/danger sind ausserdem frei belegbar und nicht identisch mit
     * den Tokens in resources/css/content/theme.css. Die benannten Farben
     * fuehren Badge und .content-status auf dieselben Werte zurueck
     * (Schattierung 50 = bg-Token, 600 = fg-Token).
     *
     * Farbe ist nie alleiniger Traeger — immer zusammen mit label() zeigen.
     * Status-Badges bekommen kein ->icon(): den Marker traegt der Punkt.
     */
    public function color(): string
    {
        return "status-{$this->value}";
    }

    /**
     * Klasse der Blade-Statuspille. Gleiche Bauform wie das Filament-Badge:
     * 24 px hoch, Radius 6 px, Punkt davor.
     */
    public function cssClass(): string
    {
        return "content-status content-status--{$this->value}";
    }

    /**
     * Praefix der CSS-Variablen des Status, etwa
     * `var(--color-status-review-fill)` fuer Balken und Diagrammreihen.
     */
    public function tokenPrefix(): string
    {
        return "--color-status-{$this->value}";
    }

    /**
     * Symbol neben der Beschriftung, damit der Status auch ohne
     * Farbwahrnehmung erkennbar bleibt (Heroicons, wie im Panel ueblich).
     */
    public function icon(): string
    {
        return match ($this) {
            self::IDEA => 'heroicon-o-light-bulb',
            self::SCHEDULED => 'heroicon-o-calendar-days',
            self::GENERATING => 'heroicon-o-sparkles',
            self::REVIEW => 'heroicon-o-eye',
            self::PUBLISHED => 'heroicon-o-check-circle',
            self::FAILED => 'heroicon-o-exclamation-triangle',
            self::ARCHIVED => 'heroicon-o-archive-box',
        };
    }

    /**
     * Reihenfolge im Pipeline-Board und in Sortierungen.
     */
    public function order(): int
    {
        return match ($this) {
            self::IDEA => 1,
            self::SCHEDULED => 2,
            self::GENERATING => 3,
            self::REVIEW => 4,
            self::PUBLISHED => 5,
            self::FAILED => 6,
            self::ARCHIVED => 7,
        };
    }

    /**
     * Nur "Zur Prüfung" darf eine Zaehlmarke in der Navigation erzeugen.
     * Alles andere meldet sich ueber die Stoerungsliste der Uebersicht.
     */
    public function badgesNavigation(): bool
    {
        return $this === self::REVIEW;
    }

    /**
     * Der Status wartet auf einen Menschen.
     */
    public function needsHumanAction(): bool
    {
        return in_array($this, [self::REVIEW, self::FAILED], true);
    }

    /**
     * Die Pipeline arbeitet gerade — Statuspille darf sich bewegen,
     * sofern prefers-reduced-motion es zulaesst.
     */
    public function isAnimated(): bool
    {
        return $this === self::GENERATING;
    }

    /**
     * Darf aus diesem Status heraus zurueckgezogen werden? Steuert die
     * Sichtbarkeit der Aktion "Zurueckziehen" in Pruefung und Artikelliste
     * (#20). Was noch erzeugt wird oder blosse Idee ist, wird nicht
     * zurueckgezogen, sondern verworfen bzw. abgebrochen.
     */
    public function allowsWithdrawal(): bool
    {
        return in_array($this, [self::SCHEDULED, self::REVIEW, self::PUBLISHED, self::FAILED], true);
    }

    /**
     * Zustaende, aus denen heraus nichts mehr passiert.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PUBLISHED, self::FAILED, self::ARCHIVED], true);
    }

    /**
     * Abbildung eines Themenkandidaten auf den Anzeige-Status.
     */
    public static function fromTopic(TopicStatus $status): self
    {
        return match ($status) {
            TopicStatus::DISCOVERED, TopicStatus::SCORED, TopicStatus::RESERVE => self::IDEA,
            TopicStatus::SELECTED => self::SCHEDULED,
            TopicStatus::REJECTED => self::ARCHIVED,
        };
    }

    /**
     * Abbildung eines Artikelentwurfs auf den Anzeige-Status.
     *
     * Den zweiten Parameter liefert das Modell selbst: Entwuerfe und Artikel
     * verwenden App\\Content\\Concerns\\Withdrawable, dessen Accessor
     * `display_status` genau diese Methode mit `withdrawn_at !== null`
     * aufruft. Direkt sollte fromDraft() nur dort verwendet werden, wo kein
     * Modell vorliegt.
     *
     * @param  bool  $withdrawn  Entwurf wurde depubliziert oder verworfen
     *                           (Spalte `withdrawn_at`); ueberschreibt den
     *                           Pipeline-Status.
     */
    public static function fromDraft(DraftStatus $status, bool $withdrawn = false): self
    {
        if ($withdrawn) {
            return self::ARCHIVED;
        }

        return match ($status) {
            DraftStatus::GENERATING, DraftStatus::GENERATED, DraftStatus::CHECKING => self::GENERATING,
            DraftStatus::APPROVED, DraftStatus::SCHEDULED => self::SCHEDULED,
            DraftStatus::REVIEW => self::REVIEW,
            DraftStatus::PUBLISHED => self::PUBLISHED,
            DraftStatus::FAILED => self::FAILED,
        };
    }

    /**
     * Feinschritt innerhalb des Anzeige-Status — nur als Fortschrittstext
     * unterhalb der Statuspille verwenden, nie als eigener Status.
     */
    public static function progressLabel(DraftStatus $status): ?string
    {
        return match ($status) {
            DraftStatus::GENERATING => __('Text wird erstellt'),
            DraftStatus::GENERATED => __('Wartet auf Qualitätsprüfung'),
            DraftStatus::CHECKING => __('Qualitätsprüfung läuft'),
            DraftStatus::APPROVED => __('Freigegeben, wartet auf Termin'),
            DraftStatus::SCHEDULED => __('Termin steht fest'),
            default => null,
        };
    }

    /**
     * Spalten des Pipeline-Boards in fester Reihenfolge.
     *
     * @return array<int, self>
     */
    public static function board(): array
    {
        return [self::IDEA, self::SCHEDULED, self::GENERATING, self::REVIEW, self::PUBLISHED];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->sortBy(fn (self $case) => $case->order())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
