<?php
declare(strict_types=1);

namespace Tests\Unit;

use Nemesis\Core\Database;
use Nemesis\Core\Model;
use Nemesis\Testing\TestCase;

class ProjectionRecord extends Model
{
    protected $table = 'projection_records';
    public bool $timestamps = false;
}

class BuilderProjectionTest extends TestCase
{
    public function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        Database::setPdo($pdo);
        $pdo->exec('CREATE TABLE projection_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT)');
        $pdo->exec("INSERT INTO projection_records (name, status) VALUES ('one', 'active')");
    }

    public function tearDown(): void
    {
        Database::disconnect();
    }

    public function test_get_honors_requested_columns(): void
    {
        $record = ProjectionRecord::query()->get(['id'])->first();

        $this->assertNotNull($record);
        $this->assertSame(['id'], array_keys($record->getAttributes()));
    }

    public function test_first_honors_requested_columns(): void
    {
        $record = ProjectionRecord::query()->first(['name']);

        $this->assertNotNull($record);
        $this->assertSame(['name'], array_keys($record->getAttributes()));
    }
}
