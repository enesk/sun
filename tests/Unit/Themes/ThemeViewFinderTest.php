<?php

namespace Tests\Unit\Themes;

use App\Themes\ThemeDefinition;
use App\Themes\ThemeViewFinder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\TestCase;

class ThemeViewFinderTest extends TestCase
{
    private string $tempDir;

    private FileViewFinder $fileFinder;

    private ThemeViewFinder $viewFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/theme-viewfinder-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Create a real FileViewFinder with a real Filesystem
        $originalPath = $this->tempDir . '/resources/views';
        mkdir($originalPath, 0755, true);

        $this->fileFinder = new FileViewFinder(new Filesystem(), [$originalPath]);
        $this->viewFinder = new ThemeViewFinder($this->fileFinder);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createThemeDir(string $slug): string
    {
        $themePath = $this->tempDir . '/themes/' . $slug;
        mkdir($themePath . '/views/layouts', 0755, true);
        file_put_contents($themePath . '/views/layouts/app.blade.php', '<html></html>');
        file_put_contents($themePath . '/theme.json', json_encode([
            'name' => ucfirst($slug) . ' Theme',
            'slug' => $slug,
        ]));

        return $themePath;
    }

    private function makeTheme(string $slug): ThemeDefinition
    {
        $themePath = $this->createThemeDir($slug);

        return ThemeDefinition::fromJsonFile($themePath . '/theme.json');
    }

    public function test_initialize_captures_original_paths(): void
    {
        $this->viewFinder->initialize();

        $originalPaths = $this->viewFinder->getOriginalPaths();
        $this->assertCount(1, $originalPaths);
        $this->assertStringContainsString('resources/views', $originalPaths[0]);
    }

    public function test_initialize_only_runs_once(): void
    {
        $this->viewFinder->initialize();
        $firstPaths = $this->viewFinder->getOriginalPaths();

        // Modify paths directly on the file finder
        $reflection = new \ReflectionProperty(FileViewFinder::class, 'paths');
        $reflection->setAccessible(true);
        $reflection->setValue($this->fileFinder, ['/some/other/path']);

        // Initialize again — should NOT update original paths
        $this->viewFinder->initialize();
        $this->assertSame($firstPaths, $this->viewFinder->getOriginalPaths());
    }

    public function test_set_theme_paths_prepends_active_theme(): void
    {
        $this->viewFinder->initialize();
        $theme = $this->makeTheme('modern');

        $this->viewFinder->setThemePaths($theme);

        $paths = $this->viewFinder->getPaths();
        $this->assertStringContainsString('themes/modern/views', $paths[0]);
        // Original path should still be present as fallback
        $this->assertStringContainsString('resources/views', end($paths));
    }

    public function test_set_theme_paths_includes_default_fallback(): void
    {
        $this->viewFinder->initialize();
        $active = $this->makeTheme('modern');
        $default = $this->makeTheme('default');

        $this->viewFinder->setThemePaths($active, $default);

        $paths = $this->viewFinder->getPaths();
        $this->assertCount(3, $paths); // modern, default, resources/views
        $this->assertStringContainsString('themes/modern/views', $paths[0]);
        $this->assertStringContainsString('themes/default/views', $paths[1]);
        $this->assertStringContainsString('resources/views', $paths[2]);
    }

    public function test_set_theme_paths_skips_default_when_same_as_active(): void
    {
        $this->viewFinder->initialize();
        $theme = $this->makeTheme('default');

        $this->viewFinder->setThemePaths($theme, $theme);

        $paths = $this->viewFinder->getPaths();
        $this->assertCount(2, $paths); // default, resources/views — NOT duplicated
    }

    public function test_set_theme_paths_resets_cleanly_between_calls(): void
    {
        $this->viewFinder->initialize();
        $modern = $this->makeTheme('modern');
        $classic = $this->makeTheme('classic');

        // First theme
        $this->viewFinder->setThemePaths($modern);
        $this->assertStringContainsString('themes/modern/views', $this->viewFinder->getPaths()[0]);

        // Switch to different theme — no path accumulation
        $this->viewFinder->setThemePaths($classic);
        $paths = $this->viewFinder->getPaths();
        $this->assertCount(2, $paths); // classic + resources/views
        $this->assertStringContainsString('themes/classic/views', $paths[0]);
        // modern should NOT be present anymore
        foreach ($paths as $path) {
            $this->assertStringNotContainsString('themes/modern', $path);
        }
    }

    public function test_reset_restores_original_paths(): void
    {
        $this->viewFinder->initialize();
        $originalPaths = $this->viewFinder->getOriginalPaths();

        $theme = $this->makeTheme('modern');
        $this->viewFinder->setThemePaths($theme);
        $this->assertNotSame($originalPaths, $this->viewFinder->getPaths());

        $this->viewFinder->reset();
        $this->assertSame($originalPaths, $this->viewFinder->getPaths());
    }

    public function test_reset_does_nothing_before_initialize(): void
    {
        // Should not throw
        $this->viewFinder->reset();

        $paths = $this->viewFinder->getPaths();
        $this->assertNotEmpty($paths);
    }

    public function test_set_theme_paths_auto_initializes(): void
    {
        // Don't call initialize() explicitly
        $theme = $this->makeTheme('modern');
        $this->viewFinder->setThemePaths($theme);

        $originalPaths = $this->viewFinder->getOriginalPaths();
        $this->assertCount(1, $originalPaths);
        $this->assertStringContainsString('resources/views', $originalPaths[0]);
    }
}
