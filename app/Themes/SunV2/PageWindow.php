<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Seitenfenster der Pagination im Theme sun-v2 (x-sun.pagination), gleich
 * fuer Suche und Stadtseite.
 */
final class PageWindow
{
    /**
     * Seitenfenster exakt nach Vorlage (Seite 1 von 8: 1 2 3 … 8): aktuelle
     * Seite, die zwei folgenden, die vorige, erste und letzte; Luecken als
     * null (Auslassungspunkte). Mobil entfallen die vorige und die zweite
     * folgende Seite ("hidden sm:block").
     *
     * @return array<int, array{number: int, url: string, current: bool, desktopOnly: bool}|null>
     */
    public static function for(LengthAwarePaginator $paginator): array
    {
        $last = $paginator->lastPage();

        if ($last <= 1) {
            return [];
        }

        $current = $paginator->currentPage();
        $desktopOnly = [$current - 1, $current + 2];

        $numbers = collect([1, $current - 1, $current, $current + 1, $current + 2, $last])
            ->filter(fn (int $page): bool => $page >= 1 && $page <= $last)
            ->unique()
            ->sort()
            ->values();

        $window = [];
        $previous = 0;

        foreach ($numbers as $page) {
            if ($page - $previous > 1) {
                $window[] = null;
            }

            $window[] = [
                'number' => $page,
                'url' => $paginator->url($page),
                'current' => $page === $current,
                'desktopOnly' => in_array($page, $desktopOnly, true) && ! in_array($page, [1, $last], true),
            ];
            $previous = $page;
        }

        return $window;
    }
}
