<?php

namespace App\Themes;

use Illuminate\View\FileViewFinder;

/**
 * Custom ViewFinder that manages a theme-based view resolution chain.
 *
 * Priority order:
 * 1. Active theme views (e.g. themes/modern/views/)
 * 2. Default theme views (e.g. themes/default/views/) — always as fallback
 * 3. Laravel's standard view paths (resources/views/)
 *
 * Unlike raw prependLocation(), this finder resets theme paths cleanly
 * per-request to prevent path accumulation across requests.
 */
class ThemeViewFinder
{
    private FileViewFinder $finder;

    /** Original Laravel view paths before any theme paths were added. */
    private array $originalPaths = [];

    private bool $initialized = false;

    public function __construct(FileViewFinder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * Capture the original (non-theme) view paths.
     * Must be called once before any theme activation.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->originalPaths = $this->finder->getPaths();
        $this->initialized = true;
    }

    /**
     * Set the view resolution chain for the given theme.
     *
     * Resets any previously registered theme paths and builds a fresh chain:
     * [active theme] → [default theme fallback] → [original Laravel paths]
     */
    public function setThemePaths(ThemeDefinition $activeTheme, ?ThemeDefinition $defaultTheme = null): void
    {
        $this->initialize();

        $paths = [];

        // 1. Active theme views — highest priority
        $activeViewsPath = $activeTheme->viewsPath();
        if (is_dir($activeViewsPath)) {
            $paths[] = $activeViewsPath;
        }

        // 2. Default theme as fallback (only if different from active)
        if ($defaultTheme && $defaultTheme->slug !== $activeTheme->slug) {
            $defaultViewsPath = $defaultTheme->viewsPath();
            if (is_dir($defaultViewsPath)) {
                $paths[] = $defaultViewsPath;
            }
        }

        // 3. Original Laravel paths (resources/views, vendor paths, etc.)
        $paths = array_merge($paths, $this->originalPaths);

        // Replace all paths at once — no accumulation
        $this->replacePaths($paths);

        // Flush the finder's view cache so changed paths take effect
        $this->finder->flush();
    }

    /**
     * Reset to original paths (no theme).
     */
    public function reset(): void
    {
        if (! $this->initialized) {
            return;
        }

        $this->replacePaths($this->originalPaths);
        $this->finder->flush();
    }

    /**
     * Get the currently registered view paths.
     */
    public function getPaths(): array
    {
        return $this->finder->getPaths();
    }

    /**
     * Get the original (pre-theme) paths.
     */
    public function getOriginalPaths(): array
    {
        return $this->originalPaths;
    }

    /**
     * Replace all view paths on the underlying FileViewFinder.
     *
     * FileViewFinder doesn't have a setPaths() method, so we use
     * Reflection to replace the $paths property directly. This is
     * the same approach used by Laravel packages like Livewire.
     */
    private function replacePaths(array $paths): void
    {
        $reflection = new \ReflectionProperty(FileViewFinder::class, 'paths');
        $reflection->setAccessible(true);
        $reflection->setValue($this->finder, $paths);
    }
}
