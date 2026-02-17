<?php

namespace Tests\Unit\Themes;

use App\Themes\ThemeDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ThemeDefinitionTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = sys_get_temp_dir() . '/theme-test-' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
        mkdir($this->fixturesPath . '/views/layouts', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
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

    private function createThemeJson(array $data): string
    {
        $path = $this->fixturesPath . '/theme.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }

    public function test_creates_from_valid_json_file(): void
    {
        $path = $this->createThemeJson([
            'name' => 'Test Theme',
            'slug' => 'test-theme',
            'version' => '2.0.0',
            'author' => 'Tester',
            'description' => 'A test theme',
        ]);

        $theme = ThemeDefinition::fromJsonFile($path);

        $this->assertSame('Test Theme', $theme->name);
        $this->assertSame('test-theme', $theme->slug);
        $this->assertSame('2.0.0', $theme->version);
        $this->assertSame('Tester', $theme->author);
        $this->assertSame('A test theme', $theme->description);
    }

    public function test_uses_defaults_for_optional_fields(): void
    {
        $theme = ThemeDefinition::fromArray(
            ['name' => 'Minimal', 'slug' => 'minimal'],
            $this->fixturesPath
        );

        $this->assertSame('1.0.0', $theme->version);
        $this->assertSame('', $theme->author);
        $this->assertSame('', $theme->description);
        $this->assertSame('screenshot.png', $theme->screenshot);
        $this->assertSame([], $theme->supports);
        $this->assertSame([], $theme->options);
    }

    public function test_throws_on_missing_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must contain 'name' and 'slug'");

        ThemeDefinition::fromArray(['slug' => 'test'], $this->fixturesPath);
    }

    public function test_throws_on_missing_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ThemeDefinition::fromArray(['name' => 'Test'], $this->fixturesPath);
    }

    public function test_throws_on_invalid_slug_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lowercase alphanumeric');

        ThemeDefinition::fromArray(
            ['name' => 'Test', 'slug' => 'Invalid_Slug!'],
            $this->fixturesPath
        );
    }

    public function test_throws_on_nonexistent_json_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ThemeDefinition::fromJsonFile('/nonexistent/theme.json');
    }

    public function test_throws_on_invalid_json(): void
    {
        $path = $this->fixturesPath . '/bad.json';
        file_put_contents($path, '{invalid json}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid theme.json');

        ThemeDefinition::fromJsonFile($path);
    }

    public function test_views_path(): void
    {
        $theme = ThemeDefinition::fromArray(
            ['name' => 'Test', 'slug' => 'test'],
            '/some/path'
        );

        $this->assertSame('/some/path/views', $theme->viewsPath());
    }

    public function test_css_path(): void
    {
        $theme = ThemeDefinition::fromArray(
            ['name' => 'Test', 'slug' => 'test'],
            '/some/path'
        );

        $this->assertSame('/some/path/css', $theme->cssPath());
    }

    public function test_supports_feature_check(): void
    {
        $theme = ThemeDefinition::fromArray(
            ['name' => 'Test', 'slug' => 'test', 'supports' => ['companies', 'reviews']],
            $this->fixturesPath
        );

        $this->assertTrue($theme->supports('companies'));
        $this->assertTrue($theme->supports('reviews'));
        $this->assertFalse($theme->supports('search'));
    }

    public function test_option_defaults(): void
    {
        $theme = ThemeDefinition::fromArray([
            'name' => 'Test',
            'slug' => 'test',
            'options' => [
                'layout' => ['type' => 'select', 'choices' => ['grid', 'list'], 'default' => 'grid'],
                'sidebar' => ['type' => 'boolean', 'default' => true],
            ],
        ], $this->fixturesPath);

        $this->assertSame('grid', $theme->optionDefault('layout'));
        $this->assertTrue($theme->optionDefault('sidebar'));
        $this->assertNull($theme->optionDefault('nonexistent'));

        $this->assertSame(['layout' => 'grid', 'sidebar' => true], $theme->optionDefaults());
    }

    public function test_validate_option_value_select(): void
    {
        $theme = ThemeDefinition::fromArray([
            'name' => 'Test',
            'slug' => 'test',
            'options' => [
                'layout' => ['type' => 'select', 'choices' => ['grid', 'list'], 'default' => 'grid'],
            ],
        ], $this->fixturesPath);

        $this->assertTrue($theme->validateOptionValue('layout', 'grid'));
        $this->assertTrue($theme->validateOptionValue('layout', 'list'));
        $this->assertFalse($theme->validateOptionValue('layout', 'table'));
        $this->assertFalse($theme->validateOptionValue('unknown', 'value'));
    }

    public function test_validate_option_value_boolean(): void
    {
        $theme = ThemeDefinition::fromArray([
            'name' => 'Test',
            'slug' => 'test',
            'options' => [
                'sidebar' => ['type' => 'boolean', 'default' => true],
            ],
        ], $this->fixturesPath);

        $this->assertTrue($theme->validateOptionValue('sidebar', true));
        $this->assertTrue($theme->validateOptionValue('sidebar', false));
        $this->assertFalse($theme->validateOptionValue('sidebar', 'yes'));
    }

    public function test_is_valid_with_proper_structure(): void
    {
        // Create required directory structure
        file_put_contents($this->fixturesPath . '/views/layouts/app.blade.php', '<html></html>');

        $theme = ThemeDefinition::fromArray(
            ['name' => 'Test', 'slug' => 'test'],
            $this->fixturesPath
        );

        $this->assertTrue($theme->isValid());
    }

    public function test_is_valid_fails_without_layout(): void
    {
        $theme = ThemeDefinition::fromArray(
            ['name' => 'Test', 'slug' => 'test'],
            '/tmp/nonexistent-theme'
        );

        $this->assertFalse($theme->isValid());
    }

    public function test_to_array(): void
    {
        $theme = ThemeDefinition::fromArray([
            'name' => 'Test',
            'slug' => 'test',
            'version' => '1.0.0',
            'supports' => ['companies'],
        ], $this->fixturesPath);

        $array = $theme->toArray();

        $this->assertSame('Test', $array['name']);
        $this->assertSame('test', $array['slug']);
        $this->assertSame(['companies'], $array['supports']);
        $this->assertArrayNotHasKey('basePath', $array);
    }
}
