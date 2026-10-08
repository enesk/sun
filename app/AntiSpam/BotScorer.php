<?php

declare(strict_types=1);

namespace App\AntiSpam;

use App\AntiSpam\Detectors\Detector;
use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\BotCandidate;
use App\AntiSpam\Support\BotScore;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bewertet einen Datensatz gegen alle eingetragenen Regeln (#10).
 *
 * Die Regeln stehen in `antispam.suspected_bots.detectors` als
 * `Klasse => Gewicht`. Sie werden ueber den Container aufgeloest und je Lauf
 * einmal gebaut; ein neues Muster braucht deshalb nur eine Zeile in der
 * Konfiguration und keine Aenderung an `antispam:scan`.
 *
 * Addiert wird, gedeckelt auf 100. Eine Regel, die einen Fehler wirft, zaehlt
 * als "trifft nicht" und wird protokolliert: ein Lauf ueber 20 Portale darf
 * nicht an einem kaputten Muster scheitern, und ein Fehler darf erst recht
 * nicht als Treffer gelten.
 */
class BotScorer
{
    public const MAX_SCORE = 100;

    /** @var list<array{detector: Detector, weight: int}> */
    private array $rules;

    /**
     * @param  array<class-string<Detector>, int>|null  $detectors  nur fuer Tests; sonst aus der Konfiguration
     */
    public function __construct(?array $detectors = null)
    {
        $this->rules = $this->build($detectors ?? self::configured());
    }

    /** Wirksame Schwelle, Portal-Einstellungen eingerechnet. */
    public static function threshold(): int
    {
        return max(1, AntiSpamConfig::int('suspected_bots.threshold', 70));
    }

    public function score(BotCandidate $candidate): BotScore
    {
        $score = 0;
        $reasons = [];

        foreach ($this->rules as $rule) {
            $detector = $rule['detector'];

            if (! $this->hits($detector, $candidate)) {
                continue;
            }

            $score += $rule['weight'];
            $reasons[] = [
                'code' => $detector->code(),
                'label' => $detector->label(),
                'weight' => $rule['weight'],
            ];
        }

        return new BotScore(min($score, self::MAX_SCORE), $reasons);
    }

    /**
     * Alle eingetragenen Regeln mit Gewicht — fuer die Anzeige im Admin und
     * den Kopf des Berichts.
     *
     * @return array<string, int>
     */
    public function weights(): array
    {
        $weights = [];

        foreach ($this->rules as $rule) {
            $weights[$rule['detector']->code()] = $rule['weight'];
        }

        return $weights;
    }

    private function hits(Detector $detector, BotCandidate $candidate): bool
    {
        try {
            return $detector->supports($candidate) && $detector->matches($candidate);
        } catch (Throwable $exception) {
            Log::warning('antispam: Regel übersprungen', [
                'detector' => $detector::class,
                'kind' => $candidate->kind->value,
                'id' => $candidate->id,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<class-string<Detector>, int>
     */
    private static function configured(): array
    {
        $detectors = AntiSpamConfig::get('suspected_bots.detectors', []);

        return is_array($detectors) ? $detectors : [];
    }

    /**
     * @param  array<class-string<Detector>, int>  $detectors
     * @return list<array{detector: Detector, weight: int}>
     */
    private function build(array $detectors): array
    {
        $rules = [];

        foreach ($detectors as $class => $weight) {
            $detector = app($class);

            if (! $detector instanceof Detector) {
                Log::warning('antispam: Regel ignoriert, kein Detector', ['class' => $class]);

                continue;
            }

            $rules[] = ['detector' => $detector, 'weight' => max(0, (int) $weight)];
        }

        return $rules;
    }
}
