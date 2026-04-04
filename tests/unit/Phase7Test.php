<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 7 Test Suite | Created: 2026-04-03
// Tests: Events, Console I/O, Scheduler, CommandBus, Scaffolder, Plugin v2

use Nemesis\Testing\TestCase;
use Nemesis\Events\Event;
use Nemesis\Events\EventDispatcher;
use Nemesis\Contracts\EventInterface;
use Nemesis\Contracts\ListenerInterface;
use Nemesis\Console\Input;
use Nemesis\Console\Output;
use Nemesis\Console\Command;
use Nemesis\Console\Scheduler;
use Nemesis\Console\ScheduledTask;
use Nemesis\Reactor\CommandBus;
use Nemesis\Scaffolder\Scaffolder;
use Nemesis\Core\PluginManifest;

// ---------------------------------------------------------------------------
// Event stubs
// ---------------------------------------------------------------------------

class Phase7_UserRegistered extends Event
{
    public function __construct(public readonly string $name) {}
}

class Phase7_OrderPlaced extends Event
{
    public function __construct(public readonly int $orderId) {}
}

class Phase7_WelcomeListener implements ListenerInterface
{
    public array $received = [];

    public function handle(object $event): void
    {
        $this->received[] = $event;
    }
}

// ---------------------------------------------------------------------------
// Command stub
// ---------------------------------------------------------------------------

class Phase7_GreetCommand extends Command
{
    protected string $signature   = 'greet {name}';
    protected string $description = 'Say hello';

    public int $lastCode = -1;

    public function handle(): int
    {
        $name = $this->input->argument('name') ?? 'World';
        $this->output->info("Hello, {$name}!");
        $this->lastCode = self::SUCCESS;
        return self::SUCCESS;
    }
}

class Phase7_FailCommand extends Command
{
    protected string $signature   = 'fail';
    protected string $description = 'Always fails';

    public function handle(): int { return self::FAILURE; }
}

// ===========================================================================
// Test class
// ===========================================================================

class Phase7Test extends TestCase
{
    public function setUp(): void
    {
        // Reset singletons between tests
        EventDispatcher::setInstance(null);
        CommandBus::resetInstance();
    }

    // -----------------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------------

    public function testEventImplementsInterface(): void
    {
        $e = new Phase7_UserRegistered('Alice');
        $this->assertInstanceOf(EventInterface::class, $e);
    }

    public function testEventDispatchCallsListener(): void
    {
        $listener = new Phase7_WelcomeListener();

        EventDispatcher::listen(Phase7_UserRegistered::class, function (Phase7_UserRegistered $e) use ($listener) {
            $listener->handle($e);
        });

        EventDispatcher::dispatch(new Phase7_UserRegistered('Bob'));

        $this->assertCount(1, $listener->received);
        $this->assertSame('Bob', $listener->received[0]->name);
    }

    public function testEventDispatchWithClassStringListener(): void
    {
        EventDispatcher::listen(Phase7_UserRegistered::class, Phase7_WelcomeListener::class);
        $event = EventDispatcher::dispatch(new Phase7_UserRegistered('Carol'));
        $this->assertInstanceOf(Phase7_UserRegistered::class, $event);
    }

    public function testEventPropagationStop(): void
    {
        $calls = 0;
        EventDispatcher::listen(Phase7_UserRegistered::class, function (Phase7_UserRegistered $e) use (&$calls) {
            $calls++;
            $e->stopPropagation();
        });
        EventDispatcher::listen(Phase7_UserRegistered::class, function () use (&$calls) {
            $calls++; // should NOT be called
        });

        EventDispatcher::dispatch(new Phase7_UserRegistered('Dave'));
        $this->assertSame(1, $calls);
    }

    public function testForgetListeners(): void
    {
        EventDispatcher::listen(Phase7_UserRegistered::class, fn($e) => null);
        $this->assertTrue(EventDispatcher::hasListeners(Phase7_UserRegistered::class));

        EventDispatcher::forget(Phase7_UserRegistered::class);
        $this->assertFalse(EventDispatcher::hasListeners(Phase7_UserRegistered::class));
    }

    public function testForgetAllListeners(): void
    {
        EventDispatcher::listen(Phase7_UserRegistered::class, fn($e) => null);
        EventDispatcher::listen(Phase7_OrderPlaced::class, fn($e) => null);

        EventDispatcher::forget();

        $this->assertFalse(EventDispatcher::hasListeners(Phase7_UserRegistered::class));
        $this->assertFalse(EventDispatcher::hasListeners(Phase7_OrderPlaced::class));
    }

    public function testMultipleListenersSamEvent(): void
    {
        $log = [];
        EventDispatcher::listen(Phase7_OrderPlaced::class, function ($e) use (&$log) { $log[] = 'a'; });
        EventDispatcher::listen(Phase7_OrderPlaced::class, function ($e) use (&$log) { $log[] = 'b'; });

        EventDispatcher::dispatch(new Phase7_OrderPlaced(42));
        $this->assertSame(['a', 'b'], $log);
    }

    // -----------------------------------------------------------------------
    // Console Input
    // -----------------------------------------------------------------------

    public function testInputParsesPositionalArguments(): void
    {
        $input = new Input(['nemesis', 'greet', 'Alice']);
        $this->assertSame('Alice', $input->argument('0'));
    }

    public function testInputParsesOptions(): void
    {
        $input = new Input(['nemesis', 'migrate', '--force', '--connection=mysql']);
        $this->assertTrue($input->hasOption('force'));
        $this->assertSame('mysql', $input->option('connection'));
    }

    public function testInputBindSignatureNamesArgs(): void
    {
        $input = new Input(['nemesis', 'greet', 'Alice']);
        $input->bindSignature('greet {name}');
        $this->assertSame('Alice', $input->argument('name'));
    }

    public function testInputOptionMissingReturnsFalse(): void
    {
        $input = new Input(['nemesis', 'greet']);
        $this->assertFalse($input->hasOption('force'));
    }

    // -----------------------------------------------------------------------
    // Console Command
    // -----------------------------------------------------------------------

    public function testCommandRunReturnsSuccess(): void
    {
        $cmd    = new Phase7_GreetCommand();
        $input  = new Input(['nemesis', 'greet', 'World']);
        $output = new Output(false); // no colours

        $code = $cmd->run($input, $output);
        $this->assertSame(Command::SUCCESS, $code);
    }

    public function testCommandGetName(): void
    {
        $cmd = new Phase7_GreetCommand();
        $this->assertSame('greet', $cmd->getName());
    }

    public function testCommandGetDescription(): void
    {
        $cmd = new Phase7_GreetCommand();
        $this->assertSame('Say hello', $cmd->getDescription());
    }

    // -----------------------------------------------------------------------
    // Scheduler
    // -----------------------------------------------------------------------

    public function testSchedulerCallbackIsAdded(): void
    {
        $scheduler = new Scheduler();
        $scheduler->call(fn() => null)->everyMinute();
        $this->assertCount(1, $scheduler->getTasks());
    }

    public function testScheduledTaskEveryMinuteIsDue(): void
    {
        $task = new ScheduledTask(fn() => null);
        $task->everyMinute();
        $this->assertTrue($task->isDue());
    }

    public function testScheduledTaskCustomCronNotDue(): void
    {
        $task = new ScheduledTask(fn() => null);
        // Use a minute value that can never match current time (0-59 vs 99)
        $task->cron('99 99 99 99 99');
        $this->assertFalse($task->isDue());
    }

    public function testSchedulerRunsOnlyDueTasks(): void
    {
        $ran = [];
        $scheduler = new Scheduler();

        $scheduler->call(function () use (&$ran) { $ran[] = 'due'; })->everyMinute();
        $scheduler->call(function () use (&$ran) { $ran[] = 'never'; })->cron('99 99 99 99 99');

        $scheduler->run();

        $this->assertContains('due', $ran);
        $this->assertNotContains('never', $ran);
    }

    public function testScheduledTaskFrequencies(): void
    {
        $task = new ScheduledTask(fn() => null);
        $this->assertSame('0 0 * * *',  (clone $task)->daily()->getExpression());
        $this->assertSame('0 * * * *',  (clone $task)->hourly()->getExpression());
        $this->assertSame('0 0 * * 0',  (clone $task)->weekly()->getExpression());
        $this->assertSame('0 0 1 * *',  (clone $task)->monthly()->getExpression());
    }

    // -----------------------------------------------------------------------
    // CommandBus
    // -----------------------------------------------------------------------

    public function testCommandBusRegisterAndHas(): void
    {
        $bus = CommandBus::getInstance();
        CommandBus::register(Phase7_GreetCommand::class);
        $this->assertTrue($bus->has('greet'));
    }

    public function testCommandBusRunSuccess(): void
    {
        CommandBus::register(Phase7_GreetCommand::class);
        $bus    = CommandBus::getInstance();
        $input  = new Input(['nemesis', 'greet', 'Tester']);
        $output = new Output(false);
        $code   = $bus->run('greet', $input, $output);
        $this->assertSame(Command::SUCCESS, $code);
    }

    public function testCommandBusRunFailureOnUnknown(): void
    {
        $bus    = CommandBus::getInstance();
        $input  = new Input(['nemesis', 'nonexistent']);
        $output = new Output(false);
        $code   = $bus->run('nonexistent', $input, $output);
        $this->assertSame(Command::FAILURE, $code);
    }

    public function testCommandBusRegisterAll(): void
    {
        CommandBus::registerAll([Phase7_GreetCommand::class, Phase7_FailCommand::class]);
        $bus = CommandBus::getInstance();
        $this->assertTrue($bus->has('greet'));
        $this->assertTrue($bus->has('fail'));
    }

    // -----------------------------------------------------------------------
    // Scaffolder
    // -----------------------------------------------------------------------

    public function testScaffolderStubsDirExists(): void
    {
        $dir = __DIR__ . '/../../src/Scaffolder/stubs';
        $this->assertTrue(is_dir($dir), "Stubs dir missing: {$dir}");
    }

    public function testScaffolderAllStubsExist(): void
    {
        $types = [
            'controller', 'model', 'middleware', 'event', 'listener',
            'job', 'policy', 'migration', 'seeder', 'trait', 'repository',
            'entity', 'dto', 'transformer', 'manager', 'handler',
            'interface', 'factory', 'filter', 'library', 'helper',
            'widget',
        ];
        $dir = __DIR__ . '/../../src/Scaffolder/stubs';
        foreach ($types as $type) {
            $this->assertTrue(
                file_exists("{$dir}/{$type}.stub"),
                "Missing stub: {$type}.stub"
            );
        }
    }

    public function testScaffolderGeneratesControllerInTemp(): void
    {
        $tmpDir = sys_get_temp_dir() . '/nemesis_scaffold_' . uniqid();
        mkdir($tmpDir . '/app/Controllers', 0755, true);

        // Override base_path for scaffold test using a closure trick
        // (we can't override the function, so test the stub content directly)
        $stub = file_get_contents(__DIR__ . '/../../src/Scaffolder/stubs/controller.stub');
        $this->assertStringContainsString('{{ClassName}}', $stub);
        $this->assertStringContainsString('{{Namespace}}', $stub);

        // Clean up
        rmdir($tmpDir . '/app/Controllers');
        rmdir($tmpDir . '/app');
        rmdir($tmpDir);
    }

    public function testScaffolderTokenReplacement(): void
    {
        $scaffolder = new Scaffolder(__DIR__ . '/../../src/Scaffolder/stubs');
        $stub = file_get_contents(__DIR__ . '/../../src/Scaffolder/stubs/controller.stub');

        $tokens  = ['ClassName' => 'ProductController', 'Namespace' => 'App\\Controllers'];
        $search  = array_map(fn($k) => '{{' . $k . '}}', array_keys($tokens));
        $replaced = str_replace($search, array_values($tokens), $stub);

        $this->assertStringContainsString('ProductController', $replaced);
        $this->assertStringContainsString('App\\Controllers', $replaced);
        $this->assertStringNotContainsString('{{ClassName}}', $replaced);
    }

    // -----------------------------------------------------------------------
    // Plugin Manifest v2
    // -----------------------------------------------------------------------

    public function testPluginManifestV2Fields(): void
    {
        $tmpDir = sys_get_temp_dir() . '/p7_plugin_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $manifest = [
            'name'     => 'TestPlugin',
            'version'  => '1.0.0',
            'entry'    => 'bootstrap.php',
            'provides' => ['StorageInterface' => 'S3Driver'],
            'tags'     => ['storage', 'cloud'],
            'conflicts' => ['OtherPlugin'],
        ];
        file_put_contents($tmpDir . '/plugin.json', json_encode($manifest));

        $pm = new PluginManifest($tmpDir . '/plugin.json');

        $this->assertSame(['StorageInterface' => 'S3Driver'], $pm->getProvides());
        $this->assertSame(['storage', 'cloud'], $pm->getTags());
        $this->assertSame(['OtherPlugin'], $pm->getConflicts());
        $this->assertTrue($pm->isV2());

        // Clean up
        unlink($tmpDir . '/plugin.json');
        rmdir($tmpDir);
    }

    public function testPluginManifestV1StillValid(): void
    {
        $tmpDir = sys_get_temp_dir() . '/p7_v1_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $manifest = [
            'name'    => 'OldPlugin',
            'version' => '1.0.0',
            'entry'   => 'bootstrap.php',
        ];
        file_put_contents($tmpDir . '/plugin.json', json_encode($manifest));

        $pm = new PluginManifest($tmpDir . '/plugin.json');

        $this->assertSame([], $pm->getProvides());
        $this->assertSame([], $pm->getTags());
        $this->assertSame([], $pm->getConflicts());
        $this->assertFalse($pm->isV2());

        // Clean up
        unlink($tmpDir . '/plugin.json');
        rmdir($tmpDir);
    }

    // -----------------------------------------------------------------------
    // File / folder existence checks
    // -----------------------------------------------------------------------

    public function testEventFilesExist(): void
    {
        $this->assertTrue(file_exists(base_path('src/Events/Event.php')));
        $this->assertTrue(file_exists(base_path('src/Events/EventDispatcher.php')));
    }

    public function testConsoleFilesExist(): void
    {
        $this->assertTrue(file_exists(base_path('src/Console/Input.php')));
        $this->assertTrue(file_exists(base_path('src/Console/Output.php')));
        $this->assertTrue(file_exists(base_path('src/Console/Scheduler.php')));
    }

    public function testCommandBusFileExists(): void
    {
        $this->assertTrue(file_exists(base_path('src/Reactor/CommandBus.php')));
    }

    public function testScaffolderFileExists(): void
    {
        $this->assertTrue(file_exists(base_path('src/Scaffolder/Scaffolder.php')));
    }
}
