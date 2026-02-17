<?php

namespace Tests\Unit\Themes;

use App\Themes\ThemeDefinition;
use App\Themes\ThemeManager;
use PHPUnit\Framework\TestCase;

class ThemeManagerTest extends TestCase
{
    private string $themesPath;

    private ThemeManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themesPath = sys_get_temp_dir() . '/themes-test-' . uniqid();
        mkdir($this->themesPath, 0755, true);

        // Create a ThemeManager with our test directory injected
        $this->manager = new ThemeManager($this->themesPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->themesPath);
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

    private function createTheme(string $slug, array $manifest, bool $withLayout = true): void
    {
        $themePath = $this->themesPath . '/' . $slug;
        mkdir($themePath . '/views/layouts', 0755, true);
        mkdir($themePath . '/views/pages', 0755, true);
        mkdir($themePath . '/views/partials', 0755, true);
        mkdir($themePath . '/css', 0755, true);

        $manifest = array_merge([
            'name' => ucfirst($slug) . ' Theme',
            'slug' => $slug,
        ], $manifest);

        file_put_contents($themePath . '/theme.json', json_encode($manifest));

        if ($withLayout) {
            file_put_contents($themePath . '/views/layouts/app.blade.php', '<html>@yield("content")</html>');
        }
    }

    public function test_discovers_themes_from_directory(): void
    {
        $this->createTheme('default', ['description' => 'Default']);
        $this->createTheme('modern', ['description' => 'Modern']);

        $themes = $this->manager->discover();

        $this->assertCount(2, $themes);
        $this->assertTrue($themes->has('default'));
        $this->assertTrue($themes->has('modern'));
    }

    public function test_discover_caches_results(): void
    {
        $this->createTheme('default', []);

        $first = $this->manager->discover();
        $second = $this->manager->discover();

        $this->assertSame($first, $second);
    }

    public function test_discover_returns_empty_for_missing_directory(): void
    {
        $manager = new ThemeManager('/nonexistent/path');

        $themes = $manager->discover();

        $this->assertCount(0, $themes);
    }

    public function test_discover_skips_directories_without_manifest(): void
    {
        mkdir($this->themesPath . '/broken', 0755, true);
        $this->createTheme('default', []);

        $themes = $this->manager->discover();

        $this->assertCount(1, $themes);
        $this->assertTrue($themes->has('default'));
        $this->assertFalse($themes->has('broken'));
    }

    public function test_get_returns_theme_by_slug(): void
    {
        $this->createTheme('default', ['version' => '2.0.0']);

        $theme = $this->manager->get('default');

        $this->assertInstanceOf(ThemeDefinition::class, $theme);
        $this->assertSame('default', $theme->slug);
        $this->assertSame('2.0.0', $theme->version);
    }

    public function test_get_returns_null_for_unknown_theme(): void
    {
        $this->createTheme('default', []);

        $this->assertNull($this->manager->get('nonexistent'));
    }

    public function test_exists_checks_theme_presence(): void
    {
        $this->createTheme('default', []);

        $this->assertTrue($this->manager->exists('default'));
        $this->assertFalse($this->manager->exists('ghost'));
    }

    public function test_validate_checks_theme_structure(): void
    {
        $this->createTheme('default', [], withLayout: true);
        $this->createTheme('broken', [], withLayout: false);

        $this->assertTrue($this->manager->validate('default'));
        $this->assertFalse($this->manager->validate('broken'));
        $this->assertFalse($this->manager->validate('nonexistent'));
    }

    public function test_available_returns_themes_as_arrays(): void
    {
        $this->createTheme('default', ['description' => 'The default']);
        $this->createTheme('modern', ['description' => 'Modern look']);

        $available = $this->manager->available();

        $this->assertCount(2, $available);
        $this->assertSame('The default', $available->get('default')['description']);
        $this->assertSame('Modern look', $available->get('modern')['description']);
    }

    public function test_clear_cache_resets_discovered_themes(): void
    {
        $this->createTheme('default', []);
        $this->manager->discover();

        // Create another theme after initial discovery
        $this->createTheme('late', []);

        // Still cached — only 1 theme
        $this->assertCount(1, $this->manager->discover());

        // Clear and re-discover
        $this->manager->clearCache();
        $this->assertCount(2, $this->manager->discover());
    }

    public function test_active_returns_null_before_activation(): void
    {
        $this->assertNull($this->manager->active());
    }

    public function test_themes_base_path_getter(): void
    {
        $this->assertSame($this->themesPath, $this->manager->getThemesBasePath());
    }
}
