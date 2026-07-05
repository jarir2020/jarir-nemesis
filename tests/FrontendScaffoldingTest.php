<?php
declare(strict_types=1);

use Nemesis\Scaffolder\Scaffolder;
use Nemesis\Testing\TestCase;

class FrontendScaffoldingTest extends TestCase
{
    public function testMakeViewCreatesPlainPhpOrBladeViews(): void
    {
        $scaffolder = new Scaffolder();
        $bladePath = $scaffolder->generateView('tmp/frontend-card', true);
        $phpPath   = $scaffolder->generateView('tmp/frontend-card-plain', false);

        $this->assertStringEndsWith('.blade.php', $bladePath);
        $this->assertStringEndsWith('.php', $phpPath);
        $this->assertTrue(file_exists($bladePath));
        $this->assertTrue(file_exists($phpPath));

        @unlink($bladePath);
        @unlink($phpPath);
        @rmdir(dirname($bladePath));
        @rmdir(dirname($phpPath));
        @rmdir(dirname(dirname($bladePath)));
    }

    public function testMakeLayoutCreatesSharedAndFrameworkShells(): void
    {
        $scaffolder = new Scaffolder();
        $plainLayout = $scaffolder->generateLayout(null, 'tmp-shell');
        $frameworkLayout = $scaffolder->generateLayout('react', 'tmp-shell');

        $this->assertStringContainsString('/views/layouts/tmp-shell.blade.php', $plainLayout);
        $this->assertStringContainsString('/resources/views/react/layouts/tmp-shell.blade.php', $frameworkLayout);
        $this->assertTrue(file_exists($plainLayout));
        $this->assertTrue(file_exists($frameworkLayout));

        @unlink($plainLayout);
        @unlink($frameworkLayout);
        @rmdir(dirname($plainLayout));
        @rmdir(dirname($frameworkLayout));
        @rmdir(dirname(dirname($frameworkLayout)));
    }

    public function testFrameworkPageGeneratorsCreateScopedViews(): void
    {
        $scaffolder = new Scaffolder();
        $admin = $scaffolder->generateAdminView('testfw');
        $profile = $scaffolder->generateProfileView('testfw');
        $settings = $scaffolder->generateSettingsView('testfw');

        $this->assertStringContainsString('/resources/views/testfw/admin/dashboard.blade.php', $admin);
        $this->assertStringContainsString('/resources/views/testfw/profile.blade.php', $profile);
        $this->assertStringContainsString('/resources/views/testfw/settings.blade.php', $settings);

        foreach ([$admin, $profile, $settings] as $path) {
            @unlink($path);
        }

        @rmdir(dirname($admin));
        @rmdir(dirname(dirname($admin)));
        @rmdir(dirname($profile));
        @rmdir(dirname($settings));
        @rmdir(dirname(dirname($settings)));
    }

    public function testAdminComponentShortcutCreatesFrameworkFiles(): void
    {
        $scaffolder = new Scaffolder();
        $paths = $scaffolder->generateAdminComponent('react');

        $this->assertCount(2, $paths);
        $this->assertStringContainsString('/resources/js/react/components/Admin.js', $paths[0]);
        $this->assertStringContainsString('/resources/views/react/components/admin.blade.php', $paths[1]);
        $this->assertTrue(file_exists($paths[0]));
        $this->assertTrue(file_exists($paths[1]));

        foreach ($paths as $path) {
            @unlink($path);
        }

        @rmdir(dirname($paths[0]));
        @rmdir(dirname($paths[1]));
    }

    public function testMakeFrontendComponentCreatesFrameworkFiles(): void
    {
        $scaffolder = new Scaffolder();
        $paths = $scaffolder->generateFrontendComponent('react', 'TempCard');

        $this->assertCount(2, $paths);
        $this->assertTrue(file_exists($paths[0]));
        $this->assertTrue(file_exists($paths[1]));

        foreach ($paths as $path) {
            @unlink($path);
        }

        @rmdir(dirname($paths[0]));
        @rmdir(dirname($paths[1]));
    }

    public function testProfileAndSettingsConvenienceGeneratorsWork(): void
    {
        $scaffolder = new Scaffolder();
        $profile = $scaffolder->generateProfileComponent('react');
        $settings = $scaffolder->generateSettingsComponent('vue');

        $this->assertStringContainsString('/components/Profile.js', $profile[0]);
        $this->assertStringContainsString('/components/profile.blade.php', $profile[1]);
        $this->assertStringContainsString('/components/Settings.js', $settings[0]);
        $this->assertStringContainsString('/components/settings.blade.php', $settings[1]);

        foreach ([$profile, $settings] as $group) {
            foreach ($group as $path) {
                @unlink($path);
            }
        }

        @rmdir(dirname($profile[0]));
        @rmdir(dirname($profile[1]));
        @rmdir(dirname($settings[0]));
        @rmdir(dirname($settings[1]));
    }
}
