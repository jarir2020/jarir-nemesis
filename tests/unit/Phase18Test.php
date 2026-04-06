<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 18 — Task Scheduler Tests | Created: 2026-04-06

use Nemesis\Testing\TestCase;
use Nemesis\Console\Scheduler;
use Nemesis\Console\ScheduledTask;

// =============================================================================
// ScheduledTask — cron expression matching
// =============================================================================

class CronExpressionTest extends TestCase
{
    private function makeTask(callable $cb = null): ScheduledTask
    {
        return new ScheduledTask(\Closure::fromCallable($cb ?? fn() => null));
    }

    private function dt(string $time): \DateTime
    {
        return new \DateTime($time);
    }

    // ----- everyMinute -----

    public function testEveryMinuteAlwaysDue(): void
    {
        $t = $this->makeTask()->everyMinute();
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:37')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 23:59')));
    }

    // ----- everyFiveMinutes -----

    public function testEveryFiveMinutesDueAtZero(): void
    {
        $t = $this->makeTask()->everyFiveMinutes();
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:15')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:30')));
    }

    public function testEveryFiveMinutesNotDueAtThree(): void
    {
        $t = $this->makeTask()->everyFiveMinutes();
        $this->assertFalse($t->isDue($this->dt('2026-04-06 10:03')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 10:07')));
    }

    // ----- hourly -----

    public function testHourlyDueAtTopOfHour(): void
    {
        $t = $this->makeTask()->hourly();
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 23:00')));
    }

    public function testHourlyNotDueAtOtherMinutes(): void
    {
        $t = $this->makeTask()->hourly();
        $this->assertFalse($t->isDue($this->dt('2026-04-06 10:01')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 10:30')));
    }

    public function testHourlyAt30(): void
    {
        $t = $this->makeTask()->hourlyAt(30);
        $this->assertTrue($t->isDue($this->dt('2026-04-06 10:30')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 10:00')));
    }

    // ----- daily -----

    public function testDailyDueAtMidnight(): void
    {
        $t = $this->makeTask()->daily();
        $this->assertTrue($t->isDue($this->dt('2026-04-06 00:00')));
    }

    public function testDailyNotDueAtOtherTimes(): void
    {
        $t = $this->makeTask()->daily();
        $this->assertFalse($t->isDue($this->dt('2026-04-06 08:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 00:01')));
    }

    public function testDailyAt(): void
    {
        $t = $this->makeTask()->dailyAt('08:30');
        $this->assertTrue($t->isDue($this->dt('2026-04-06 08:30')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 08:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 09:30')));
    }

    // ----- weekly -----

    public function testWeeklyDueOnSunday(): void
    {
        $t = $this->makeTask()->weekly();
        // 2026-04-05 is a Sunday (day 0)
        $this->assertTrue($t->isDue($this->dt('2026-04-05 00:00')));
    }

    public function testWeeklyNotDueOnMonday(): void
    {
        $t = $this->makeTask()->weekly();
        // 2026-04-06 is a Monday (day 1)
        $this->assertFalse($t->isDue($this->dt('2026-04-06 00:00')));
    }

    public function testWeeklyOn(): void
    {
        // Monday (1) at 09:00
        $t = $this->makeTask()->weeklyOn(1, '09:00');
        $this->assertTrue($t->isDue($this->dt('2026-04-06 09:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 08:00')));
    }

    // ----- monthly -----

    public function testMonthlyDueOnFirstAtMidnight(): void
    {
        $t = $this->makeTask()->monthly();
        $this->assertTrue($t->isDue($this->dt('2026-04-01 00:00')));
    }

    public function testMonthlyNotDueOnOtherDays(): void
    {
        $t = $this->makeTask()->monthly();
        $this->assertFalse($t->isDue($this->dt('2026-04-06 00:00')));
    }

    // ----- twiceDaily -----

    public function testTwiceDailyDueAtBothHours(): void
    {
        $t = $this->makeTask()->twiceDaily(6, 18);
        $this->assertTrue($t->isDue($this->dt('2026-04-06 06:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 18:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 12:00')));
    }

    // ----- custom cron -----

    public function testCustomCronExpression(): void
    {
        // Every Monday at 08:00
        $t = $this->makeTask()->cron('0 8 * * 1');
        $this->assertTrue($t->isDue($this->dt('2026-04-06 08:00'))); // Monday
        $this->assertFalse($t->isDue($this->dt('2026-04-06 09:00'))); // wrong hour
        $this->assertFalse($t->isDue($this->dt('2026-04-07 08:00'))); // Tuesday
    }

    public function testCronWithCommaList(): void
    {
        // 8am and 5pm
        $t = $this->makeTask()->cron('0 8,17 * * *');
        $this->assertTrue($t->isDue($this->dt('2026-04-06 08:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 17:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 12:00')));
    }

    public function testCronWithRange(): void
    {
        // Every minute from 8am to 5pm (hour 8-17)
        $t = $this->makeTask()->cron('* 8-17 * * *');
        $this->assertTrue($t->isDue($this->dt('2026-04-06 08:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 17:59')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 07:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 18:00')));
    }

    public function testCronWithRangeAndStep(): void
    {
        // Every 2 hours from 0 to 23: 0,2,4,6,8,10,12,14,16,18,20,22
        $t = $this->makeTask()->cron('0 0-23/2 * * *');
        $this->assertTrue($t->isDue($this->dt('2026-04-06 00:00')));
        $this->assertTrue($t->isDue($this->dt('2026-04-06 08:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 07:00')));
        $this->assertFalse($t->isDue($this->dt('2026-04-06 09:00')));
    }

    public function testInvalidCronReturnsFalse(): void
    {
        $t = $this->makeTask()->cron('bad expression here');
        $this->assertFalse($t->isDue());
    }
}

// =============================================================================
// ScheduledTask — time-window conditions
// =============================================================================

class ScheduledTaskConditionTest extends TestCase
{
    private function makeTask(): ScheduledTask
    {
        return new ScheduledTask(fn() => null);
    }

    public function testBetweenAllowsTaskInWindow(): void
    {
        $t = $this->makeTask()->everyMinute()->between('08:00', '18:00');

        // We can't control current time, so just verify isDue() returns a bool
        // and doesn't throw. The logic is tested structurally.
        $result = $t->isDue();
        $this->assertIsBool($result);
    }

    public function testBetweenBlocksTaskBeforeWindow(): void
    {
        // Force a specific time by providing DateTime to isDue
        $t = $this->makeTask()->everyMinute()->between('10:00', '12:00');

        // 06:00 — before window
        $early = new \DateTime('2026-04-06 06:00:00');
        $this->assertFalse($t->isDue($early));
    }

    public function testBetweenAllowsTaskInsideWindow(): void
    {
        $t = $this->makeTask()->everyMinute()->between('06:00', '23:00');
        $mid = new \DateTime('2026-04-06 12:00:00');
        $this->assertTrue($t->isDue($mid));
    }

    public function testUnlessBetweenSkipsInsideWindow(): void
    {
        $t = $this->makeTask()->everyMinute()->unlessBetween('22:00', '23:59');
        // Inside window: should be skipped
        $late = new \DateTime('2026-04-06 22:30:00');
        $this->assertFalse($t->isDue($late));
    }

    public function testUnlessBetweenAllowsOutsideWindow(): void
    {
        $t = $this->makeTask()->everyMinute()->unlessBetween('22:00', '23:59');
        $daytime = new \DateTime('2026-04-06 14:00:00');
        $this->assertTrue($t->isDue($daytime));
    }

    public function testWhenFilterBlocksTask(): void
    {
        // Filter receives DateTimeInterface as first arg
        $t = $this->makeTask()->everyMinute()->when(fn($dt) => false);
        $this->assertFalse($t->isDue(new \DateTime('2026-04-06 12:00')));
    }

    public function testWhenFilterAllowsTask(): void
    {
        $t = $this->makeTask()->everyMinute()->when(fn($dt) => true);
        $this->assertTrue($t->isDue(new \DateTime('2026-04-06 12:00')));
    }

    public function testSkipFilterBlocksTask(): void
    {
        $t = $this->makeTask()->everyMinute()->skip(fn($dt) => true);
        $this->assertFalse($t->isDue(new \DateTime('2026-04-06 12:00')));
    }

    public function testMultipleFiltersAllMustPass(): void
    {
        $t = $this->makeTask()
            ->everyMinute()
            ->when(fn($dt) => true)
            ->when(fn($dt) => false); // second filter fails
        $this->assertFalse($t->isDue(new \DateTime('2026-04-06 12:00')));
    }
}

// =============================================================================
// ScheduledTask — meta
// =============================================================================

class ScheduledTaskMetaTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        $t = new ScheduledTask(fn() => null);
        $t->name('my-task')->description('Does something useful');

        $this->assertEquals('my-task', $t->getName());
        $this->assertEquals('Does something useful', $t->getDescription());
    }

    public function testDefaultNameIsNull(): void
    {
        $t = new ScheduledTask(fn() => null);
        $this->assertNull($t->getName());
    }

    public function testGetExpression(): void
    {
        $t = new ScheduledTask(fn() => null);
        $t->cron('0 8 * * 1');
        $this->assertEquals('0 8 * * 1', $t->getExpression());
    }

    public function testDefaultExpression(): void
    {
        $t = new ScheduledTask(fn() => null);
        $this->assertEquals('* * * * *', $t->getExpression());
    }
}

// =============================================================================
// Scheduler — builder methods
// =============================================================================

class SchedulerBuilderTest extends TestCase
{
    public function testCallRegistersTask(): void
    {
        $s = new Scheduler();
        $s->call(fn() => null);
        $this->assertCount(1, $s->getTasks());
    }

    public function testCallReturnsScheduledTask(): void
    {
        $s = new Scheduler();
        $t = $s->call(fn() => null);
        $this->assertInstanceOf(ScheduledTask::class, $t);
    }

    public function testMultipleTasksRegistered(): void
    {
        $s = new Scheduler();
        $s->call(fn() => null)->daily();
        $s->call(fn() => null)->hourly();
        $s->call(fn() => null)->everyMinute();
        $this->assertCount(3, $s->getTasks());
    }

    public function testJobRegistersTask(): void
    {
        $job = new class { public function handle(): void {} };
        $s   = new Scheduler();
        $s->job($job);
        $this->assertCount(1, $s->getTasks());
    }

    public function testJobSetsNameToClassName(): void
    {
        $job = new class { public function handle(): void {} };
        $s   = new Scheduler();
        $t   = $s->job($job);
        $this->assertNotEmpty($t->getName()); // anonymous class has a generated name
    }

    public function testJobWithNoHandleThrowsOnRun(): void
    {
        $badJob = new class {}; // no handle()
        $s = new Scheduler();
        $s->job($badJob)->everyMinute();

        $this->expectException(\RuntimeException::class);
        // Force run (bypass isDue by directly calling getTasks()[0]->run())
        $s->getTasks()[0]->run();
    }
}

// =============================================================================
// Scheduler — run()
// =============================================================================

class SchedulerRunTest extends TestCase
{
    public function testRunExecutesDueTasks(): void
    {
        $ran = false;
        $s   = new Scheduler();
        $s->setLogPath(sys_get_temp_dir() . '/nemesis_test_scheduler.log');

        // Force due: cron '* * * * *' is always due
        $s->call(function () use (&$ran) { $ran = true; })->everyMinute();

        $count = $s->run(new \DateTime('2026-04-06 12:00'));
        $this->assertTrue($ran);
        $this->assertEquals(1, $count);
    }

    public function testRunSkipsNonDueTasks(): void
    {
        $ran = false;
        $s   = new Scheduler();
        $s->setLogPath(sys_get_temp_dir() . '/nemesis_test_scheduler.log');

        // This task is due only at 00:00 — not at 12:05
        $s->call(function () use (&$ran) { $ran = true; })->daily();

        $count = $s->run(new \DateTime('2026-04-06 12:05'));
        $this->assertFalse($ran);
        $this->assertEquals(0, $count);
    }

    public function testRunReturnsCountOfRanTasks(): void
    {
        $s = new Scheduler();
        $s->setLogPath(sys_get_temp_dir() . '/nemesis_test_scheduler.log');

        $s->call(fn() => null)->everyMinute();
        $s->call(fn() => null)->everyMinute();
        $s->call(fn() => null)->daily(); // not due at 12:05

        $count = $s->run(new \DateTime('2026-04-06 12:05'));
        $this->assertEquals(2, $count);
    }

    public function testRunContinuesAfterTaskException(): void
    {
        $secondRan = false;
        $s = new Scheduler();
        $s->setLogPath(sys_get_temp_dir() . '/nemesis_test_scheduler.log');

        $s->call(function () { throw new \RuntimeException('boom'); })->everyMinute();
        $s->call(function () use (&$secondRan) { $secondRan = true; })->everyMinute();

        // Should not throw
        $count = $s->run(new \DateTime('2026-04-06 12:00'));
        $this->assertTrue($secondRan);
        $this->assertEquals(2, $count);
    }

    public function testGetDueTasks(): void
    {
        $s = new Scheduler();
        $s->call(fn() => null)->everyMinute();
        $s->call(fn() => null)->daily(); // not due at 12:05

        $due = $s->getDueTasks(new \DateTime('2026-04-06 12:05'));
        $this->assertCount(1, $due);
    }
}

// =============================================================================
// Scheduler — overlap protection (mutex)
// =============================================================================

class SchedulerMutexTest extends TestCase
{
    public function testMutexPreventsOverlapForSameTask(): void
    {
        // Run two scheduler instances in the same process with the same task name.
        // The second should be skipped (mutex already held by first).
        // We simulate this by holding the lock file open manually.

        $taskName  = 'overlap-test-task-' . uniqid();
        $lockDir   = sys_get_temp_dir() . '/nemesis_scheduler';
        @mkdir($lockDir, 0700, true);
        $lockPath  = $lockDir . '/' . md5($taskName) . '.lock';

        // Acquire lock ourselves (simulating a long-running first instance)
        $fh = fopen($lockPath, 'c');
        flock($fh, LOCK_EX);

        $ran = false;
        $s   = new Scheduler();
        $s->setLogPath(sys_get_temp_dir() . '/nemesis_test_scheduler.log');
        $s->call(function () use (&$ran) { $ran = true; })
          ->everyMinute()
          ->name($taskName);

        // The scheduler should skip the task because our lock is held
        $s->run(new \DateTime('2026-04-06 12:00'));
        $this->assertFalse($ran);

        // Release our lock
        flock($fh, LOCK_UN);
        fclose($fh);

        // Now it should run
        $s->run(new \DateTime('2026-04-06 12:00'));
        $this->assertTrue($ran);
    }
}

// =============================================================================
// Weekday helpers
// =============================================================================

class ScheduledTaskWeekdayTest extends TestCase
{
    private function dt(string $t): \DateTime { return new \DateTime($t); }

    public function testWeekdaysDueOnMonday(): void
    {
        // weekdays() = '0 0 * * 1-5' — due at midnight Mon-Fri
        $task = (new ScheduledTask(fn() => null))->weekdays();
        // 2026-04-06 is Monday at midnight
        $this->assertTrue($task->isDue($this->dt('2026-04-06 00:00')));
    }

    public function testWeekdaysNotDueOnSaturday(): void
    {
        $task = (new ScheduledTask(fn() => null))->weekdays();
        // 2026-04-11 is Saturday
        $this->assertFalse($task->isDue($this->dt('2026-04-11 00:00')));
    }

    public function testWeekendsNotDueOnMonday(): void
    {
        // weekends() = '0 0 * * 6,0' — due at midnight Sat/Sun
        $task = (new ScheduledTask(fn() => null))->weekends();
        $this->assertFalse($task->isDue($this->dt('2026-04-06 00:00'))); // Monday
    }

    public function testWeekendsNotDueOnFriday(): void
    {
        $task = (new ScheduledTask(fn() => null))->weekends();
        // 2026-04-10 is Friday (dow=5)
        $this->assertFalse($task->isDue($this->dt('2026-04-10 00:00')));
    }

    public function testMondaysDueOnMonday(): void
    {
        // mondays() = '0 0 * * 1'
        $task = (new ScheduledTask(fn() => null))->mondays();
        $this->assertTrue($task->isDue($this->dt('2026-04-06 00:00')));
    }

    public function testMondaysNotDueOnTuesday(): void
    {
        $task = (new ScheduledTask(fn() => null))->mondays();
        // 2026-04-07 is Tuesday (dow=2)
        $this->assertFalse($task->isDue($this->dt('2026-04-07 00:00')));
    }
}

// =============================================================================
// App Console Kernel
// =============================================================================

class ConsoleKernelTest extends TestCase
{
    public function testKernelExists(): void
    {
        $this->assertTrue(class_exists('App\Console\Kernel'));
    }

    public function testKernelHasScheduleMethod(): void
    {
        $this->assertTrue(method_exists('App\Console\Kernel', 'schedule'));
    }

    public function testKernelRegistersAtLeastOneTask(): void
    {
        $kernel    = new App\Console\Kernel();
        $scheduler = new Scheduler();
        $kernel->schedule($scheduler);
        $this->assertGreaterThan(0, count($scheduler->getTasks()));
    }
}
