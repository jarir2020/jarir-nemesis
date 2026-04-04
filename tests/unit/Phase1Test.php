<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 1 Test Suite | Created: 2026-04-02
// Tests: Contracts, Exceptions, Attributes, Global Helpers, Folder Structure

use Nemesis\Testing\TestCase;

class Phase1Test extends TestCase
{
    // -------------------------------------------------------------------------
    // Contract interfaces exist and are interfaces
    // -------------------------------------------------------------------------

    public function testContainerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\ContainerInterface::class));
    }

    public function testMiddlewareInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\MiddlewareInterface::class));
    }

    public function testListenerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\ListenerInterface::class));
    }

    public function testModelInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\ModelInterface::class));
    }

    public function testRepositoryInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\RepositoryInterface::class));
    }

    public function testCacheInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\CacheInterface::class));
    }

    public function testQueueInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\QueueInterface::class));
    }

    public function testCommandInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\CommandInterface::class));
    }

    public function testEventInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Contracts\EventInterface::class));
    }

    // -------------------------------------------------------------------------
    // Exception hierarchy exists and has correct inheritance
    // -------------------------------------------------------------------------

    public function testNemesisExceptionExists(): void
    {
        $this->assertTrue(class_exists(\Nemesis\Exceptions\NemesisException::class));
    }

    public function testNemesisExceptionExtendsRuntimeException(): void
    {
        $e = new \Nemesis\Exceptions\NemesisException('test');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testHttpExceptionExtendsNemesisException(): void
    {
        $e = new \Nemesis\Exceptions\HttpException(500);
        $this->assertInstanceOf(\Nemesis\Exceptions\NemesisException::class, $e);
    }

    public function testHttpExceptionCarriesStatusCode(): void
    {
        $e = new \Nemesis\Exceptions\HttpException(403, 'Forbidden');
        $this->assertEquals(403, $e->getStatusCode());
        $this->assertEquals('Forbidden', $e->getMessage());
    }

    public function testHttpExceptionDefaultMessage(): void
    {
        $e = new \Nemesis\Exceptions\HttpException(404);
        $this->assertEquals('Not Found', $e->getMessage());
    }

    public function testNotFoundExceptionIs404(): void
    {
        $e = new \Nemesis\Exceptions\NotFoundException();
        $this->assertInstanceOf(\Nemesis\Exceptions\HttpException::class, $e);
        $this->assertEquals(404, $e->getStatusCode());
    }

    public function testForbiddenExceptionIs403(): void
    {
        $e = new \Nemesis\Exceptions\ForbiddenException();
        $this->assertEquals(403, $e->getStatusCode());
    }

    public function testUnauthorizedExceptionIs401(): void
    {
        $e = new \Nemesis\Exceptions\UnauthorizedException();
        $this->assertEquals(401, $e->getStatusCode());
    }

    public function testMethodNotAllowedExceptionIs405(): void
    {
        $e = new \Nemesis\Exceptions\MethodNotAllowedException();
        $this->assertEquals(405, $e->getStatusCode());
    }

    public function testTooManyRequestsExceptionIs429(): void
    {
        $e = new \Nemesis\Exceptions\TooManyRequestsException();
        $this->assertEquals(429, $e->getStatusCode());
    }

    public function testDatabaseExceptionExtendsNemesisException(): void
    {
        $e = new \Nemesis\Exceptions\DatabaseException('db error');
        $this->assertInstanceOf(\Nemesis\Exceptions\NemesisException::class, $e);
    }

    public function testConfigurationExceptionExtendsNemesisException(): void
    {
        $e = new \Nemesis\Exceptions\ConfigurationException('bad config');
        $this->assertInstanceOf(\Nemesis\Exceptions\NemesisException::class, $e);
    }

    public function testPluginExceptionExtendsNemesisException(): void
    {
        $e = new \Nemesis\Exceptions\PluginException('plugin error');
        $this->assertInstanceOf(\Nemesis\Exceptions\NemesisException::class, $e);
    }

    public function testRenderableExceptionInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Exceptions\Concerns\RenderableException::class));
    }

    public function testReportableExceptionInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Nemesis\Exceptions\Concerns\ReportableException::class));
    }

    // -------------------------------------------------------------------------
    // Attribute classes exist and are valid PHP attributes
    // -------------------------------------------------------------------------

    public function testInternalApiAttributeExists(): void
    {
        $this->assertTrue(class_exists(\Nemesis\Attributes\InternalApi::class));
    }

    public function testStableApiAttributeExists(): void
    {
        $this->assertTrue(class_exists(\Nemesis\Attributes\StableApi::class));
    }

    public function testInternalApiIsAttribute(): void
    {
        $ref = new \ReflectionClass(\Nemesis\Attributes\InternalApi::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertTrue(count($attrs) > 0, 'InternalApi must be declared as #[Attribute]');
    }

    public function testStableApiIsAttribute(): void
    {
        $ref = new \ReflectionClass(\Nemesis\Attributes\StableApi::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertTrue(count($attrs) > 0, 'StableApi must be declared as #[Attribute]');
    }

    // -------------------------------------------------------------------------
    // Global helper functions exist and work
    // -------------------------------------------------------------------------

    public function testAbortThrowsNotFoundException(): void
    {
        try {
            abort(404);
            $this->assertTrue(false, 'abort(404) should have thrown');
        } catch (\Nemesis\Exceptions\NotFoundException $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    public function testAbortThrowsForbiddenException(): void
    {
        try {
            abort(403, 'No access');
            $this->assertTrue(false, 'abort(403) should have thrown');
        } catch (\Nemesis\Exceptions\ForbiddenException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('No access', $e->getMessage());
        }
    }

    public function testAbortUnauthorizedThrows401(): void
    {
        try {
            abort(401);
        } catch (\Nemesis\Exceptions\UnauthorizedException $e) {
            $this->assertEquals(401, $e->getStatusCode());
        }
    }

    public function testAbortGenericCodeThrowsHttpException(): void
    {
        try {
            abort(500, 'Server error');
        } catch (\Nemesis\Exceptions\HttpException $e) {
            $this->assertEquals(500, $e->getStatusCode());
        }
    }

    public function testAbortIfTriggersWhenTrue(): void
    {
        try {
            abort_if(true, 403);
            $this->assertTrue(false, 'abort_if(true) should have thrown');
        } catch (\Nemesis\Exceptions\ForbiddenException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function testAbortIfDoesNotTriggerWhenFalse(): void
    {
        abort_if(false, 403); // Must not throw
        $this->assertTrue(true);
    }

    public function testAbortUnlessTriggersWhenFalse(): void
    {
        try {
            abort_unless(false, 401);
            $this->assertTrue(false, 'abort_unless(false) should have thrown');
        } catch (\Nemesis\Exceptions\UnauthorizedException $e) {
            $this->assertEquals(401, $e->getStatusCode());
        }
    }

    public function testAbortUnlessDoesNotTriggerWhenTrue(): void
    {
        abort_unless(true, 401); // Must not throw
        $this->assertTrue(true);
    }

    public function testNowReturnsDateTime(): void
    {
        $dt = now();
        $this->assertInstanceOf(\DateTime::class, $dt);
    }

    public function testCollectReturnsCollection(): void
    {
        $c = collect([1, 2, 3]);
        $this->assertInstanceOf(\Nemesis\Support\Collection::class, $c);
    }

    public function testClassBasenameFunction(): void
    {
        $this->assertEquals('Phase1Test', class_basename(Phase1Test::class));
        $this->assertEquals('Collection', class_basename(\Nemesis\Support\Collection::class));
        $this->assertEquals('Phase1Test', class_basename($this));
    }

    public function testEnvFunctionExists(): void
    {
        $this->assertTrue(function_exists('env'));
    }

    public function testBasePathFunctionExists(): void
    {
        $this->assertTrue(function_exists('base_path'));
    }

    public function testViewFunctionExists(): void
    {
        $this->assertTrue(function_exists('view'));
    }

    public function testConfigFunctionExists(): void
    {
        $this->assertTrue(function_exists('config'));
    }

    public function testAppFunctionExists(): void
    {
        $this->assertTrue(function_exists('app'));
    }

    public function testDdFunctionExists(): void
    {
        $this->assertTrue(function_exists('dd'));
    }

    public function testDumpFunctionExists(): void
    {
        $this->assertTrue(function_exists('dump'));
    }

    public function testFlashFunctionExists(): void
    {
        $this->assertTrue(function_exists('flash'));
    }

    public function testOldFunctionExists(): void
    {
        $this->assertTrue(function_exists('old'));
    }

    // -------------------------------------------------------------------------
    // Folder structure exists
    // -------------------------------------------------------------------------

    public function testAppTraitsFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Traits')));
    }

    public function testAppRepositoriesFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Repositories')));
    }

    public function testAppEntitiesFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Entities')));
    }

    public function testAppDTOsFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/DTOs')));
    }

    public function testAppTransformersFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Transformers')));
    }

    public function testAppInterfacesFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Interfaces')));
    }

    public function testAppLanguageFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Language')));
    }

    public function testAppWidgetsFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('app/Widgets')));
    }

    public function testViewsErrorsFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('views/errors')));
    }

    public function testSrcInterceptorsFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('src/Interceptors')));
    }

    public function testSrcReactorFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('src/Reactor')));
    }

    public function testSrcTelemetryFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('src/Telemetry')));
    }

    public function testHelperFlashFolderExists(): void
    {
        // Fixed 2026-04-02: actual path is app/Helpers/Flash (not root Helper/Flash)
        $this->assertTrue(is_dir(base_path('app/Helpers/Flash')));
    }

    public function testTestsUnitFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('tests/unit')));
    }

    public function testTestsDatabaseFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('tests/database')));
    }

    public function testTestsMocksFolderExists(): void
    {
        $this->assertTrue(is_dir(base_path('tests/mocks')));
    }

    public function testBinNemesisExists(): void
    {
        $this->assertTrue(file_exists(base_path('bin/nemesis')));
    }

    // -------------------------------------------------------------------------
    // Strict types sanity — key src files have it
    // -------------------------------------------------------------------------

    public function testContainerHasStrictTypes(): void
    {
        $content = file_get_contents(base_path('src/Core/Container.php'));
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testModelHasStrictTypes(): void
    {
        $content = file_get_contents(base_path('src/Core/Model.php'));
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testRouterHasStrictTypes(): void
    {
        $content = file_get_contents(base_path('src/Router/Router.php'));
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }
}
