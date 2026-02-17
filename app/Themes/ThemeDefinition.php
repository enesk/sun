<?php

namespace App\Themes;

use InvalidArgumentException;

class ThemeDefinition
{
    public readonly string $name;
    public readonly string $slug;
    public readonly string $version;
    public readonly string $author;
    public readonly string $description;
    public readonly string $screenshot;
    public readonly array $supports;
    public readonly array $options;
    public readonly string $basePath;

    private function __construct(array $data, string $basePath)
    {
        $this->name = $data['name'];
        $this->slug = $data['slug'];
        $this->version = $data['version'] ?? '1.0.0';
        $this->author = $data['author'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->screenshot = $data['screenshot'] ?? 'screenshot.png';
        $this->supports = $data['supports'] ?? [];
        $this->options = $data['options'] ?? [];
        $this->basePath = $basePath;
    }

    /**
     * Create ThemeDefinition from a theme.json file path.
     */
    public static function fromJsonFile(string $jsonPath): self
    {
        if (! file_exists($jsonPath)) {
            throw new InvalidArgumentException("Theme manifest not found: {$jsonPath}");
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid theme.json: " . json_last_error_msg());
        }

        return self::fromArray($data, dirname($jsonPath));
    }

    /**
     * Create ThemeDefinition from an array.
     */
    public static function fromArray(array $data, string $basePath): self
    {
        if (empty($data['name']) || empty($data['slug'])) {
            throw new InvalidArgumentException("Theme manifest must contain 'name' and 'slug'.");
        }

        if (! preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            throw new InvalidArgumentException("Theme slug must be lowercase alphanumeric with hyphens: '{$data['slug']}'");
        }

        return new self($data, $basePath);
    }

    /**
     * Get the path to the views directory.
     */
    public function viewsPath(): string
    {
        return $this->basePath . '/views';
    }

    /**
     * Get the path to the CSS directory.
     */
    public function cssPath(): string
    {
        return $this->basePath . '/css';
    }

    /**
     * Get the path to the JS directory.
     */
    public function jsPath(): string
    {
        return $this->basePath . '/js';
    }

    /**
     * Get the screenshot URL for admin display.
     */
    public function screenshotPath(): string
    {
        return $this->basePath . '/' . $this->screenshot;
    }

    /**
     * Check if this theme supports a specific feature.
     */
    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports);
    }

    /**
     * Get the default value for a theme option.
     */
    public function optionDefault(string $key): mixed
    {
        return $this->options[$key]['default'] ?? null;
    }

    /**
     * Get all option defaults as key => value array.
     */
    public function optionDefaults(): array
    {
        $defaults = [];
        foreach ($this->options as $key => $config) {
            $defaults[$key] = $config['default'] ?? null;
        }

        return $defaults;
    }

    /**
     * Validate a value against a theme option's allowed choices.
     */
    public function validateOptionValue(string $key, mixed $value): bool
    {
        if (! isset($this->options[$key])) {
            return false;
        }

        $option = $this->options[$key];

        return match ($option['type'] ?? 'string') {
            'select' => in_array($value, $option['choices'] ?? []),
            'boolean' => is_bool($value),
            'string' => is_string($value),
            default => true,
        };
    }

    /**
     * Check if the theme directory structure is valid.
     */
    public function isValid(): bool
    {
        return is_dir($this->viewsPath())
            && is_dir($this->viewsPath() . '/layouts')
            && file_exists($this->viewsPath() . '/layouts/app.blade.php');
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'author' => $this->author,
            'description' => $this->description,
            'screenshot' => $this->screenshot,
            'supports' => $this->supports,
            'options' => $this->options,
        ];
    }
}
