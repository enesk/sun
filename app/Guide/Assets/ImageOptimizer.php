<?php

declare(strict_types=1);

namespace App\Guide\Assets;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Bildbytes -> WebP in mehreren Breiten (#20, uebernommen aus
 * dem alten Content-ImageOptimizer, entfernt mit #35).
 *
 * Schneidet mittig auf 16:9 und kodiert jede Breite aus
 * config('guide.images.widths') mit webp_quality. Die groesste Variante muss
 * unter hero_max_bytes bleiben: reicht die Wunschqualitaet nicht, wird sie
 * schrittweise bis min_webp_quality gesenkt. Schreibt nichts auf die Platte.
 */
class ImageOptimizer
{
    /**
     * @return list<array{width: int, height: int, quality: int, bytes: int, over_limit: bool, binary: string}>
     *                                                                                                          absteigend nach Breite, die erste ist die Hauptvariante
     */
    public function variants(string $binary): array
    {
        $image = $this->decode($binary);
        $aspect = (float) config('guide.images.aspect', 16 / 9);
        $maxBytes = max(0, (int) config('guide.images.hero_max_bytes', 153600));
        $variants = [];

        foreach (self::widths() as $index => $width) {
            $height = (int) round($width / $aspect);

            // cover schneidet mittig zu; ein zu kleines Original wird hochgerechnet.
            $resized = (clone $image)->cover($width, $height);

            $variants[] = $this->encode($resized, $width, $height, $index === 0 ? $maxBytes : 0);
        }

        return $variants;
    }

    /**
     * Konfigurierte Breiten, absteigend.
     *
     * @return list<int>
     */
    public static function widths(): array
    {
        $widths = array_values(array_unique(array_filter(array_map(
            static fn (mixed $width): int => (int) $width,
            (array) config('guide.images.widths', [1200, 800, 400]),
        ), static fn (int $width): bool => $width > 0)));

        rsort($widths);

        return $widths;
    }

    /**
     * @return array{width: int, height: int, quality: int, bytes: int, over_limit: bool, binary: string}
     */
    private function encode(ImageInterface $image, int $width, int $height, int $maxBytes): array
    {
        $quality = max(1, min(100, (int) config('guide.images.webp_quality', 82)));
        $minQuality = max(1, min($quality, (int) config('guide.images.min_webp_quality', 50)));
        $step = max(1, (int) config('guide.images.quality_step', 6));

        while (true) {
            $encoded = (string) $image->toWebp($quality);
            $bytes = strlen($encoded);

            if ($maxBytes <= 0 || $bytes <= $maxBytes || $quality <= $minQuality) {
                return [
                    'width' => $width,
                    'height' => $height,
                    'quality' => $quality,
                    'bytes' => $bytes,
                    'over_limit' => $maxBytes > 0 && $bytes > $maxBytes,
                    'binary' => $encoded,
                ];
            }

            $quality = max($minQuality, $quality - $step);
        }
    }

    private function decode(string $binary): ImageInterface
    {
        if ($binary === '') {
            throw new RuntimeException('Leere Bilddaten.');
        }

        $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;

        return (new ImageManager($driver))->read($binary);
    }
}
