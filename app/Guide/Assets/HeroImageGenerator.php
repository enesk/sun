<?php

declare(strict_types=1);

namespace App\Guide\Assets;

use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Llm\LlmClient;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Models\Topic;
use App\Guide\Providers\FalClient;
use App\Guide\Support\BranchResolver;
use App\Guide\Support\TenantPromptVars;
use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Titelbild eines Themas (#20): Quelle beschaffen, in WebP-Breiten rechnen,
 * ablegen, Alt-Text formulieren.
 *
 * Quelle ist fal.ai (Flux) mit einem Prompt aus Frage und Branche samt
 * Negativ-Vorgaben (config('guide.images.negatives')). Scheitert der Aufruf,
 * ist FAL_API_KEY leer oder das Budget erschoepft, greift das
 * Branchen-Standardbild aus resources/guide/fallback/<branch>.webp. Ein
 * Upload aus dem Dashboard nimmt denselben Weg ab dem Optimizer.
 *
 * Ablage: guide/<tenant-uuid>/<topic-slug>/hero-<hash>-<breite>.webp auf der
 * Platte 'public' (mandantengetrennt). Der Hash im Dateinamen sorgt dafuer,
 * dass ein neues Bild nicht hinter einer gecachten URL haengen bleibt; die
 * Dateien des vorigen Bilds werden danach entfernt.
 *
 * Muss im Tenant-Kontext laufen.
 */
class HeroImageGenerator
{
    public const SOURCE_FAL = 'fal';

    public const SOURCE_FALLBACK = 'fallback';

    public const SOURCE_UPLOAD = 'upload';

    public const ALT_TEMPLATE = 'guide.image_alt';

    public function __construct(
        private readonly FalClient $fal,
        private readonly ImageOptimizer $optimizer,
        private readonly LlmClient $llm,
    ) {}

    /**
     * Erzeugt das Bild ueber fal.ai, sonst aus dem Branchen-Standardbild.
     *
     * @return array{path: string, alt: string, width: int, height: int, source: string, bytes: int, over_limit: bool}
     *
     * @throws RuntimeException wenn weder fal.ai noch ein Standardbild ein Bild liefern
     */
    public function generate(Topic $topic, LlmCallContext $context): array
    {
        $branch = TenantPromptVars::current()['branch'];
        $prompt = $this->prompt($topic, $branch);
        $binary = $this->fromFal($topic, $prompt, $context);
        $source = self::SOURCE_FAL;

        if ($binary === null) {
            $binary = $this->fromFallback();
            $source = self::SOURCE_FALLBACK;
        }

        $alt = $source === self::SOURCE_FAL
            ? $this->altText($topic, $branch, $prompt, $context)
            : $this->fallbackAlt($topic, $branch);

        return [...$this->store($topic, $binary), 'alt' => $alt, 'source' => $source];
    }

    /**
     * Ersetzt das Bild durch einen Upload. Ohne eigenen Alt-Text bleibt der
     * bisherige stehen, sonst der neutrale Ersatztext.
     *
     * @return array{path: string, alt: string, width: int, height: int, source: string, bytes: int, over_limit: bool}
     */
    public function fromUpload(Topic $topic, string $binary, ?string $alt, ?string $previousAlt = null): array
    {
        $alt = $this->cleanAlt((string) $alt);

        if ($alt === '') {
            $alt = $this->cleanAlt((string) $previousAlt) ?: $this->fallbackAlt($topic, TenantPromptVars::current()['branch']);
        }

        return [...$this->store($topic, $binary), 'alt' => $alt, 'source' => self::SOURCE_UPLOAD];
    }

    /**
     * Bild-Prompt: Szene aus Branche und Thema, Stil- und Negativ-Vorgaben.
     * Die Frage geht ohne Fragezeichen, Jahreszahlen und Anfuehrungszeichen
     * hinein — Flux versucht sonst, sie ins Bild zu schreiben.
     */
    public function prompt(Topic $topic, string $branch): string
    {
        $subject = (string) $topic->question;
        $subject = preg_replace('/\b(19|20)\d{2}\b/u', '', $subject) ?? $subject;
        $subject = str_replace(['?', '!', '"', '„', '“', '‚', '‘'], '', $subject);
        $subject = Str::limit(trim(preg_replace('/\s+/u', ' ', $subject) ?? ''), 120, '');

        $style = trim((string) config('guide.images.style', 'fotorealistisch'));
        $negatives = implode(', ', array_filter(array_map(
            static fn (mixed $negative): string => trim((string) $negative),
            (array) config('guide.images.negatives', []),
        )));

        return trim(
            "Fotorealistische Aufnahme aus dem Arbeitsalltag der Branche {$branch} in Deutschland. "
            ."Motiv: eine typische Szene zum Thema {$subject}."
            ."\nStil: {$style}."
            .($negatives !== '' ? "\nAusdrücklich nicht im Bild: {$negatives}." : '')
        );
    }

    /**
     * Pfad einer kleineren Variante neben der Hauptvariante.
     */
    public static function variantPath(string $path, int $width): string
    {
        return (string) preg_replace('/-\d+\.webp$/', "-{$width}.webp", $path);
    }

    /**
     * Titelbild fuer die Ausgabe (Artikelseite, Dashboard). URLs werden erst
     * hier gebildet, weil die Platte je Tenant eine eigene URL hat.
     *
     * @return array{src: string, srcset: ?string, webp: null, width: int, height: int, alt: string, credit: null, credit_html: null, source: string}|null
     */
    public static function presentation(?ArticleDetail $detail, string $fallbackAlt = ''): ?array
    {
        $path = trim((string) $detail?->hero_image_path);

        if ($path === '' || (int) $detail->hero_image_width <= 0) {
            return null;
        }

        $disk = self::disk();
        $candidates = [];

        foreach (ImageOptimizer::widths() as $width) {
            $variant = self::variantPath($path, $width);

            if ($variant === $path || $width < (int) $detail->hero_image_width && $disk->exists($variant)) {
                $candidates[$width] = $disk->url($variant).' '.$width.'w';
            }
        }

        return [
            'src' => $disk->url($path),
            'srcset' => count($candidates) > 1 ? implode(', ', $candidates) : null,
            'webp' => null,
            'width' => (int) $detail->hero_image_width,
            'height' => (int) $detail->hero_image_height,
            'alt' => trim((string) $detail->hero_image_alt) ?: $fallbackAlt,
            'credit' => null,
            'credit_html' => null,
            'source' => 'guide',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function altSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['alt_text'],
            'properties' => [
                'alt_text' => ['type' => 'string', 'minLength' => 15, 'maxLength' => self::altMax()],
            ],
        ];
    }

    private function fromFal(Topic $topic, string $prompt, LlmCallContext $context): ?string
    {
        if (! $this->fal->isConfigured()) {
            return null;
        }

        try {
            return $this->fal->generate($prompt, $context);
        } catch (BudgetExceededException $exception) {
            Log::info('Titelbild: Guide-Budget erschoepft, Branchen-Standardbild verwendet.', [
                'topic_id' => $topic->getKey(),
                'scope' => $exception->scope,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Titelbild ueber fal.ai nicht erzeugbar, Branchen-Standardbild verwendet.', [
                'topic_id' => $topic->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Branchen-Standardbild, sonst default.webp.
     *
     * @throws RuntimeException
     */
    private function fromFallback(): string
    {
        $directory = rtrim((string) config('guide.images.fallback.path', resource_path('guide/fallback')), '/');
        $default = (string) config('guide.images.fallback.default', 'default');
        /** @var Tenant $tenant */
        $tenant = tenant();

        foreach (array_filter([BranchResolver::resolve($tenant), $default]) as $key) {
            $path = "{$directory}/{$key}.webp";
            $binary = is_file($path) && is_readable($path) ? (string) file_get_contents($path) : '';

            if ($binary !== '') {
                return $binary;
            }
        }

        throw new RuntimeException("Kein Branchen-Standardbild in {$directory} gefunden.");
    }

    /**
     * Rechnet die Varianten, legt sie ab und raeumt das vorige Bild weg.
     *
     * @return array{path: string, width: int, height: int, bytes: int, over_limit: bool}
     */
    private function store(Topic $topic, string $binary): array
    {
        $variants = $this->optimizer->variants($binary);

        if ($variants === []) {
            throw new RuntimeException('Keine Bildbreiten konfiguriert (guide.images.widths).');
        }

        $disk = self::disk();
        $directory = $this->directory($topic);
        $token = substr(sha1($variants[0]['binary']), 0, 10);
        $written = [];

        foreach ($variants as $variant) {
            $path = "{$directory}/hero-{$token}-{$variant['width']}.webp";
            $disk->put($path, $variant['binary']);
            $written[] = $path;
        }

        foreach ($disk->files($directory) as $file) {
            if (str_starts_with(basename($file), 'hero-') && ! in_array($file, $written, true)) {
                $disk->delete($file);
            }
        }

        $main = $variants[0];

        if ($main['over_limit']) {
            Log::warning('Titelbild ueberschreitet hero_max_bytes auch bei Mindestqualitaet.', [
                'topic_id' => $topic->getKey(),
                'bytes' => $main['bytes'],
            ]);
        }

        return [
            'path' => $written[0],
            'width' => $main['width'],
            'height' => $main['height'],
            'bytes' => $main['bytes'],
            'over_limit' => $main['over_limit'],
        ];
    }

    private function directory(Topic $topic): string
    {
        $base = trim((string) config('guide.images.base_path', 'guide'), '/');
        $tenantSegment = Str::slug((string) tenant()?->getTenantKey()) ?: 'central';
        $slug = Str::slug((string) $topic->slug) ?: "thema-{$topic->getKey()}";

        return "{$base}/{$tenantSegment}/{$slug}";
    }

    /**
     * Alt-Text ueber das Template guide.image_alt. Fehlt das Template oder
     * scheitert der Aufruf, greift der neutrale Ersatztext — ein Bild ohne
     * alt-Attribut darf kein Providerausfall verursachen.
     */
    private function altText(Topic $topic, string $branch, string $prompt, LlmCallContext $context): string
    {
        try {
            $tenantId = tenancy()->initialized ? (int) tenant()?->getKey() : null;
            $template = PromptTemplate::query()->resolve(self::ALT_TEMPLATE, $tenantId)->first();

            if ($template === null) {
                throw new RuntimeException('Prompt-Template '.self::ALT_TEMPLATE.' fehlt (GuidePromptTemplateSeeder).');
            }

            $result = $this->llm->structured($template, [
                ...TenantPromptVars::current(),
                'question' => (string) $topic->question,
                'notes' => $prompt,
            ], self::altSchema(), $context);

            $alt = $this->cleanAlt((string) ($result['alt_text'] ?? ''));

            if ($alt !== '') {
                return $alt;
            }
        } catch (Throwable $exception) {
            Log::warning('Alt-Text des Titelbilds nicht erzeugbar, Ersatztext verwendet.', [
                'topic_id' => $topic->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->fallbackAlt($topic, $branch);
    }

    private function fallbackAlt(Topic $topic, string $branch): string
    {
        $question = rtrim(trim((string) $topic->question), '?');

        return $this->cleanAlt("Symbolbild zum Thema {$question} aus dem Bereich {$branch}");
    }

    private function cleanAlt(string $alt): string
    {
        $alt = trim((string) preg_replace('/\s+/u', ' ', strip_tags($alt)));

        return Str::limit(rtrim($alt, '.'), self::altMax(), '');
    }

    private static function altMax(): int
    {
        return max(60, (int) config('guide.images.alt_max_chars', 125));
    }

    private static function disk(): Filesystem
    {
        return Storage::disk((string) config('guide.images.disk', 'public'));
    }
}
