<?php

declare(strict_types=1);

namespace App\Content\Assets;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Bildbytes -> WebP in mehreren Breiten (#16).
 *
 * Der Optimizer bekommt Rohbytes und liefert fertige WebP-Dateien in den
 * Breiten aus config('content.assets.widths'), zugeschnitten auf 16:9. Er
 * schreibt nichts auf die Platte — das macht der AssetStorage, damit die
 * Pfadlogik an einer Stelle bleibt.
 *
 * Die Groessengrenze der groessten Variante (Abnahme #16: Hero <= 150 KB) ist
 * hart: bringt die Wunschqualitaet die Datei nicht darunter, wird die
 * Qualitaet in Schritten gesenkt, bis sie passt oder die Untergrenze erreicht
 * ist. Ein etwas weicheres Bild ist besser als ein Titelbild, das die
 * Ladezeit der Ratgeberseite (#17) verdirbt.
 */
class ImageOptimizer
{
    /**
     * Kodiert die Vorlage in alle konfigurierten Breiten.
     *
     * @return array<int, array{width: int, height: int, quality: int, bytes: int, over_limit: bool, binary: string}>
     *                                                                                                                absteigend nach Breite, die erste ist die Hauptvariante
     */
    public function variants(string $binary): array
    {
        $image = $this->decode($binary);
        $aspect = (float) config('content.assets.hero_aspect', 16 / 9);

        $widths = array_values(array_unique(array_filter(array_map(
            static fn ($width): int => (int) $width,
            (array) config('content.assets.widths', [1200, 800, 400]),
        ))));

        rsort($widths);

        $maxBytes = max(0, (int) config('content.assets.hero_max_bytes', 153600));
        $variants = [];

        foreach ($widths as $index => $width) {
            $height = (int) round($width / $aspect);

            // cover schneidet mittig zu und faellt nie unter die Zielgroesse;
            // ein zu kleines Original wird dabei hochgerechnet.
            $resized = (clone $image)->cover($width, $height);

            // Die Groessengrenze gilt der Hauptvariante. Die kleineren liegen
            // ohnehin darunter und behalten die volle Qualitaet.
            $variants[] = $this->encode($resized, $width, $height, $index === 0 ? $maxBytes : 0);
        }

        return $variants;
    }

    /**
     * Kodiert ein einzelnes Bild als WebP und senkt die Qualitaet, bis die
     * Datei unter $maxBytes liegt (0 = keine Grenze).
     *
     * @return array{width: int, height: int, quality: int, bytes: int, over_limit: bool, binary: string}
     */
    private function encode(ImageInterface $image, int $width, int $height, int $maxBytes): array
    {
        $quality = max(1, min(100, (int) config('content.assets.webp_quality', 82)));
        $minQuality = max(1, min($quality, (int) config('content.assets.min_webp_quality', 50)));
        $step = max(1, (int) config('content.assets.quality_step', 6));

        while (true) {
            $encoded = (string) $image->toWebp($quality);
            $bytes = strlen($encoded);

            if ($maxBytes <= 0 || $bytes <= $maxBytes || $quality <= $minQuality) {
                return [
                    'width' => $width,
                    'height' => $height,
                    'quality' => $quality,
                    'bytes' => $bytes,
                    // Nur eine Vorlage mit extrem hoher Detaildichte reisst die
                    // Grenze auch bei der Mindestqualitaet. Der Job meldet das,
                    // statt das Bild stillschweigend zu behalten.
                    'over_limit' => $maxBytes > 0 && $bytes > $maxBytes,
                    'binary' => $encoded,
                ];
            }

            $quality = max($minQuality, $quality - $step);
        }
    }

    /**
     * Intervention Image v3 mit Imagick, sonst GD. Beide koennen WebP; GD
     * braucht dafuer eine Installation mit --with-webp, die hier vorausgesetzt
     * wird (sonst gaebe es auch keine Bilder im Portal).
     */
    private function decode(string $binary): ImageInterface
    {
        if ($binary === '') {
            throw new RuntimeException('Leere Bilddaten.');
        }

        $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;

        return (new ImageManager($driver))->read($binary);
    }
}
