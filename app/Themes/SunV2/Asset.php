<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

/**
 * Adressen der statischen Theme-Dateien unter public/themes/sun-v2/
 * (Icon-Sprite, Platzhalterbilder) mit Aenderungsstempel als Cache-Buster.
 *
 * CSS, JS und Schrift laufen dagegen ueber Vite (resources/views/themes/sun-v2/).
 * Die SVGs liegen bewusst nicht dort: Vite wuerde Dateien unter 4 KB inline
 * einbetten, und dann fehlen sie im Manifest.
 */
final class Asset
{
    private const BASE = 'themes/sun-v2/';

    /** @var array<string, string> */
    private static array $urls = [];

    public static function url(string $path): string
    {
        return self::$urls[$path] ??= self::build($path);
    }

    public static function icon(string $name): string
    {
        return self::url('images/icons.svg').'#'.$name;
    }

    private static function build(string $path): string
    {
        $relative = self::BASE.ltrim($path, '/');
        $file = public_path($relative);

        $version = is_file($file) ? (string) filemtime($file) : null;

        return asset($relative).($version ? "?v={$version}" : '');
    }
}
