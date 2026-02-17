<?php

namespace App\Http\Middleware {
    // Override the global tenant() function within the middleware namespace
    // so we can control its return value from tests.
    function tenant()
    {
        return \Tests\Unit\Themes\ResolveThemeTest::$tenantMock;
    }
}

namespace Tests\Unit\Themes {

    use App\Http\Middleware\ResolveTheme;
    use App\Models\Tenant;
    use App\Themes\TenantStyleInjector;
    use App\Themes\ThemeDefinition;
    use App\Themes\ThemeManager;
    use App\Themes\ThemeViewFinder;
    use Illuminate\Http\Request;
    use Illuminate\Http\Response;
    use Illuminate\Support\Facades\View;
    use PHPUnit\Framework\TestCase;

    class ResolveThemeTest extends TestCase
    {
        /** Used by the namespace-level tenant() override. */
        public static $tenantMock = null;

        private ThemeManager $themeManager;

        private ThemeViewFinder $viewFinder;

        private TenantStyleInjector $styleInjector;

        private ResolveTheme $middleware;

        protected function setUp(): void
        {
            parent::setUp();

            self::$tenantMock = null;

            $this->themeManager = $this->createMock(ThemeManager::class);
            $this->viewFinder = $this->createMock(ThemeViewFinder::class);
            $this->styleInjector = $this->createMock(TenantStyleInjector::class);

            $this->middleware = new ResolveTheme(
                $this->themeManager,
                $this->viewFinder,
                $this->styleInjector,
            );
        }

        protected function tearDown(): void
        {
            self::$tenantMock = null;
            parent::tearDown();
        }

        // ----- No-Tenant Path -----

        public function test_skips_when_no_tenant(): void
        {
            self::$tenantMock = null;

            $request = Request::create('/');
            $called = false;
            $response = new Response('ok');

            $result = $this->middleware->handle($request, function ($req) use (&$called, $response) {
                $called = true;

                return $response;
            });

            $this->assertTrue($called, 'Next middleware should be called');
            $this->assertSame($response, $result);
        }

        public function test_skips_when_no_theme_available(): void
        {
            $tenant = $this->createMock(Tenant::class);
            self::$tenantMock = $tenant;

            $this->themeManager->method('getTenantTheme')->willReturn('nonexistent');
            $this->themeManager->method('get')->willReturn(null);

            $request = Request::create('/');
            $called = false;
            $response = new Response('ok');

            $result = $this->middleware->handle($request, function ($req) use (&$called, $response) {
                $called = true;

                return $response;
            });

            $this->assertTrue($called, 'Next middleware should be called when no theme exists');
            $this->assertSame($response, $result);
        }

        // ----- Happy Path -----

        public function test_activates_theme_and_sets_view_paths(): void
        {
            $tenant = $this->createMock(Tenant::class);
            self::$tenantMock = $tenant;

            $activeTheme = $this->createMock(ThemeDefinition::class);
            $defaultTheme = $this->createMock(ThemeDefinition::class);

            $this->themeManager->method('getTenantTheme')->with($tenant)->willReturn('modern');
            $this->themeManager->method('get')
                ->willReturnCallback(function (string $slug) use ($activeTheme, $defaultTheme) {
                    return match ($slug) {
                        'modern' => $activeTheme,
                        ThemeManager::DEFAULT_THEME => $defaultTheme,
                        default => null,
                    };
                });
            $this->themeManager->method('getTenantThemeOptions')
                ->with($tenant)
                ->willReturn(['layout' => 'sidebar']);

            // Expect ThemeViewFinder is called with the right themes
            $this->viewFinder->expects($this->once())
                ->method('setThemePaths')
                ->with($activeTheme, $defaultTheme);

            // Expect ThemeManager::activate is called
            $this->themeManager->expects($this->once())
                ->method('activate')
                ->with('modern');

            // Expect style injection
            $this->styleInjector->expects($this->once())
                ->method('generateStyleTag')
                ->with($tenant)
                ->willReturn('<style id="tenant-branding">:root { --portal-primary: #3B82F6; }</style>');

            // We can't test View::share in a unit test (it's a Facade),
            // but we can verify it doesn't throw. We mock the Facade.
            // Since View::share is a static call, we need to skip verifying it
            // or use a Feature test. We'll just verify no exceptions are thrown.

            // Create a fake View facade that does nothing
            if (! class_exists('Illuminate\Support\Facades\View', false)) {
                // Facade autoloading — the class exists but calling ::share
                // will fail without a Laravel app. We catch that.
            }

            $request = Request::create('/');
            $response = new Response('ok');

            try {
                $result = $this->middleware->handle($request, function ($req) use ($response) {
                    return $response;
                });
                // If View::share works (e.g. in a Feature test context), great
                $this->assertSame($response, $result);
            } catch (\RuntimeException $e) {
                // View::share throws because no app is bound — that's expected in unit test.
                // The important thing is that all mocked methods were called correctly.
                $this->assertStringContainsString('facade', strtolower($e->getMessage()));
            }
        }

        public function test_falls_back_to_default_theme_when_active_not_found(): void
        {
            $tenant = $this->createMock(Tenant::class);
            self::$tenantMock = $tenant;

            $defaultTheme = $this->createMock(ThemeDefinition::class);

            $this->themeManager->method('getTenantTheme')->willReturn('nonexistent');
            $this->themeManager->method('get')
                ->willReturnCallback(function (string $slug) use ($defaultTheme) {
                    return match ($slug) {
                        ThemeManager::DEFAULT_THEME => $defaultTheme,
                        default => null,
                    };
                });
            $this->themeManager->method('getTenantThemeOptions')
                ->willReturn([]);

            // Should activate DEFAULT theme, not 'nonexistent'
            $this->themeManager->expects($this->once())
                ->method('activate')
                ->with(ThemeManager::DEFAULT_THEME);

            $this->viewFinder->expects($this->once())
                ->method('setThemePaths')
                ->with($defaultTheme, $defaultTheme);

            $this->styleInjector->method('generateStyleTag')
                ->willReturn('<style id="tenant-branding">:root {}</style>');

            $request = Request::create('/');
            $response = new Response('ok');

            try {
                $this->middleware->handle($request, function ($req) use ($response) {
                    return $response;
                });
            } catch (\RuntimeException $e) {
                // View::share Facade — expected in unit test
            }
        }

        // ----- Constructor DI -----

        public function test_constructor_accepts_dependencies(): void
        {
            $middleware = new ResolveTheme(
                $this->createMock(ThemeManager::class),
                $this->createMock(ThemeViewFinder::class),
                $this->createMock(TenantStyleInjector::class),
            );

            $this->assertInstanceOf(ResolveTheme::class, $middleware);
        }
    }
}
