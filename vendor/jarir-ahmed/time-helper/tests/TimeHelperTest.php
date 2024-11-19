<?php

use JarirAhmed\TimeHelper\TimeHelper;
use PHPUnit\Framework\TestCase;

class TimeHelperTest extends TestCase
{
    protected $timeHelper;

    protected function setUp(): void
    {
        $this->timeHelper = new TimeHelper();
    }

    public function testGetCurrentTime()
    {
        $time = $this->timeHelper->getCurrentTime('H:i:s', 'UTC');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $time);
    }

    public function testGetCurrentDate()
    {
        $date = $this->timeHelper->getCurrentDate('Y-m-d', 'UTC');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    public function testTimeTravel()
    {
        $futureDate = $this->timeHelper->timeTravel(0, 0, 1, 0, 0, 0);
        $pastDate = $this->timeHelper->timeTravel(0, 0, -1, 0, 0, 0);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $futureDate);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $pastDate);
    }
}
