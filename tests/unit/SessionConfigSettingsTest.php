<?php
declare(strict_types=1);

namespace Tests\Unit;

use Nemesis\Config\SessionConfig;
use Nemesis\Core\Config;
use Nemesis\Testing\TestCase;

class SessionConfigSettingsTest extends TestCase
{
    private string $configDirectory;
    private array $environment = [];

    private const ENV_KEYS = [
        'SESSION_DRIVER',
        'SESSION_LIFETIME',
        'SESSION_COOKIE',
        'SESSION_SECURE_COOKIE',
        'SESSION_SECURE',
        'SESSION_SAME_SITE',
        'SESSION_PATH',
        'SESSION_SAVE_PATH',
    ];

    public function setUp(): void
    {
        foreach (self::ENV_KEYS as $key) {
            $this->environment[$key] = getenv($key);
            putenv($key . '=');
        }

        $this->configDirectory = sys_get_temp_dir() . '/nemesis-session-config-' . uniqid('', true);
        mkdir($this->configDirectory . '/config', 0755, true);
        file_put_contents(
            $this->configDirectory . '/config/session.php',
            "<?php\nreturn " . var_export([
                'driver'    => 'array',
                'lifetime'  => 45,
                'cookie'    => 'configured_session',
                'secure'    => true,
                'same_site' => 'strict',
                'path'      => 'custom/session',
            ], true) . ";\n"
        );

        Config::load($this->configDirectory);
    }

    public function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }

        Config::load(base_path());
        @unlink($this->configDirectory . '/config/session.php');
        @rmdir($this->configDirectory . '/config');
        @rmdir($this->configDirectory);
    }

    public function test_from_env_uses_loaded_session_settings_as_fallbacks(): void
    {
        $config = SessionConfig::fromEnv();

        $this->assertSame('array', $config->driver);
        $this->assertSame(45, $config->lifetime);
        $this->assertSame('configured_session', $config->cookieName);
        $this->assertTrue($config->secure);
        $this->assertSame('strict', $config->sameSite);
        $this->assertSame('custom/session', $config->path);
    }
}
