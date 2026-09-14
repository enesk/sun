<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Services\Seo\SeoService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Security-Abnahme #14: JSON-LD darf mit praeparierten Firmennamen oder
 * Bewertungstexten den <script>-Block nicht verlassen. JSON_UNESCAPED_SLASHES
 * laesst "</script>" roh stehen, erst JSON_HEX_TAG kodiert < und > als </>.
 */
class JsonLdEscapingTest extends TestCase
{
    public function test_seo_json_ld_component_escapes_script_tags(): void
    {
        $name = 'Evil </script><script>alert(1)</script> GmbH';

        $seo = $this->app->make(SeoService::class);
        $seo->addJsonLd([
            '@type' => 'Electrician',
            'name' => $name,
            'review' => [['reviewBody' => '<!-- </SCRIPT > x']],
            'url' => 'https://example.test/a/b',
        ]);

        $html = trim(view('components.seo.json-ld')->render());

        $this->assertStringStartsWith('<script type="application/ld+json">', $html);
        $this->assertStringEndsWith('</script>', $html);

        $body = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));

        $this->assertStringNotContainsString('<', $body);
        $this->assertSame($name, json_decode($body, true, flags: JSON_THROW_ON_ERROR)['name']);
        $this->assertStringContainsString('https://example.test/a/b', $body);
    }

    public function test_every_json_ld_template_uses_hex_tag(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = $file->getContents();

            if (! str_contains($contents, 'application/ld+json')) {
                continue;
            }

            preg_match_all('/JSON_UNESCAPED_SLASHES[^)]*\)/', $contents, $matches);

            foreach ($matches[0] as $flags) {
                if (! str_contains($flags, 'JSON_HEX_TAG')) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)));
    }
}
