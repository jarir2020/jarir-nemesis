<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 2 Test Suite | Created: 2026-04-03
// Tests: Collection, Grammar SQL generation, Blueprint DSL, Database helpers

use Nemesis\Testing\TestCase;
use Nemesis\Support\Collection;
use Nemesis\Database\Blueprint;
use Nemesis\Database\Grammars\MySqlGrammar;
use Nemesis\Database\Grammars\PostgresGrammar;
use Nemesis\Database\Grammars\SQLiteGrammar;
use Nemesis\Database\Grammars\GrammarInterface;

// ---------------------------------------------------------------------------
// Helpers used by multiple tests
// ---------------------------------------------------------------------------

function p2_mysql_col(string $name, string $type, array $extra = []): array
{
    return array_merge([
        'name'          => $name,
        'type'          => $type,
        'nullable'      => false,
        'unsigned'      => false,
        'autoIncrement' => false,
        'primary'       => false,
        'unique'        => false,
        'index'         => false,
    ], $extra);
}

// ===========================================================================
// Phase2Test — Collection
// ===========================================================================

class Phase2CollectionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Basics
    // -------------------------------------------------------------------------

    public function testMakeCreatesInstance(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertInstanceOf(Collection::class, $c);
        $this->assertEquals(3, $c->count());
    }

    public function testCountableInterface(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertEquals(3, count($c));
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue((new Collection([]))->isEmpty());
        $this->assertFalse((new Collection([1]))->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $this->assertTrue((new Collection([1]))->isNotEmpty());
        $this->assertFalse((new Collection([]))->isNotEmpty());
    }

    public function testFirstReturnsFirstItem(): void
    {
        $c = new Collection([10, 20, 30]);
        $this->assertEquals(10, $c->first());
    }

    public function testFirstWithCallback(): void
    {
        $c = new Collection([1, 2, 3, 4]);
        $this->assertEquals(3, $c->first(fn($v) => $v > 2));
    }

    public function testLastReturnsLastItem(): void
    {
        $c = new Collection([10, 20, 30]);
        $this->assertEquals(30, $c->last());
    }

    // -------------------------------------------------------------------------
    // Transformation
    // -------------------------------------------------------------------------

    public function testMapTransformsItems(): void
    {
        $c       = new Collection([1, 2, 3]);
        $doubled = $c->map(fn($v) => $v * 2);
        $this->assertEquals([2, 4, 6], array_values($doubled->toArray()));
    }

    public function testFilterRemovesFalsyByDefault(): void
    {
        $c = new Collection([0, 1, '', 2, null, 3]);
        $this->assertEquals([1, 2, 3], array_values($c->filter()->toArray()));
    }

    public function testFilterWithCallback(): void
    {
        $c    = new Collection([1, 2, 3, 4, 5]);
        $even = $c->filter(fn($v) => $v % 2 === 0);
        $this->assertEquals([2, 4], array_values($even->toArray()));
    }

    public function testWhereFiltersByKeyValue(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob',   'age' => 25],
            ['name' => 'Carol', 'age' => 30],
        ];
        $c = new Collection($data);
        $this->assertEquals(2, $c->where('age', 30)->count());
    }

    public function testWhereSupportsOperators(): void
    {
        $data = [['val' => 1], ['val' => 5], ['val' => 10]];
        $this->assertEquals(2, (new Collection($data))->where('val', '>', 3)->count());
    }

    public function testReduceFoldsValues(): void
    {
        $c = new Collection([1, 2, 3, 4]);
        $this->assertEquals(10, $c->reduce(fn($carry, $item) => $carry + $item, 0));
    }

    public function testFlattenFlattensNested(): void
    {
        $c = new Collection([[1, 2], [3, [4, 5]]]);
        $this->assertEquals([1, 2, 3, 4, 5], $c->flatten()->toArray());
    }

    public function testCollapseFlattensOneLevel(): void
    {
        $c = new Collection([[1, 2], [3, 4], [5]]);
        $this->assertEquals([1, 2, 3, 4, 5], $c->collapse()->toArray());
    }

    // -------------------------------------------------------------------------
    // Extraction
    // -------------------------------------------------------------------------

    public function testPluckExtractsValues(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        $c    = new Collection($data);
        $this->assertEquals(['Alice', 'Bob'], $c->pluck('name')->toArray());
    }

    public function testPluckWithKeyBy(): void
    {
        $data = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $c    = new Collection($data);
        $this->assertEquals([1 => 'Alice', 2 => 'Bob'], $c->pluck('name', 'id')->toArray());
    }

    public function testKeysReturnsKeys(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a', 'b'], $c->keys()->toArray());
    }

    public function testValuesResetsKeys(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $this->assertEquals([1, 2], $c->values()->toArray());
    }

    // -------------------------------------------------------------------------
    // Grouping
    // -------------------------------------------------------------------------

    public function testGroupByGroupsItems(): void
    {
        $data = [
            ['type' => 'A', 'val' => 1],
            ['type' => 'B', 'val' => 2],
            ['type' => 'A', 'val' => 3],
        ];
        $groups = (new Collection($data))->groupBy('type');
        $this->assertEquals(2, $groups->count());
        $this->assertEquals(2, $groups->get('A')->count());
        $this->assertEquals(1, $groups->get('B')->count());
    }

    public function testKeyByKeysByField(): void
    {
        $data  = [['id' => 5, 'n' => 'A'], ['id' => 7, 'n' => 'B']];
        $keyed = (new Collection($data))->keyBy('id');
        $this->assertEquals('A', $keyed->get(5)['n']);
        $this->assertEquals('B', $keyed->get(7)['n']);
    }

    // -------------------------------------------------------------------------
    // Slicing
    // -------------------------------------------------------------------------

    public function testChunkSplitsIntoSubCollections(): void
    {
        $c      = new Collection([1, 2, 3, 4, 5]);
        $chunks = $c->chunk(2);
        $this->assertEquals(3, $chunks->count());
        $this->assertInstanceOf(Collection::class, $chunks->first());
    }

    public function testTakeReturnsFirstNItems(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2], $c->take(2)->toArray());
    }

    public function testSkipSkipsFirstNItems(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([4, 5], array_values($c->skip(3)->toArray()));
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public function testSortBySortsAscending(): void
    {
        $c      = new Collection([3, 1, 2]);
        $sorted = $c->sortBy(fn($v) => $v);
        $this->assertEquals([1, 2, 3], array_values($sorted->toArray()));
    }

    public function testSortByDescSortsDescending(): void
    {
        $data   = [['n' => 1], ['n' => 3], ['n' => 2]];
        $sorted = (new Collection($data))->sortByDesc('n');
        $this->assertEquals(3, $sorted->first()['n']);
    }

    public function testReverseReversesOrder(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertEquals([3, 2, 1], array_values($c->reverse()->toArray()));
    }

    // -------------------------------------------------------------------------
    // Uniqueness
    // -------------------------------------------------------------------------

    public function testUniqueDeduplicates(): void
    {
        $c = new Collection([1, 2, 2, 3, 3, 3]);
        $this->assertEquals([1, 2, 3], array_values($c->unique()->toArray()));
    }

    public function testUniqueByKey(): void
    {
        $data = [['id' => 1], ['id' => 1], ['id' => 2]];
        $this->assertEquals(2, (new Collection($data))->unique('id')->count());
    }

    // -------------------------------------------------------------------------
    // Aggregates
    // -------------------------------------------------------------------------

    public function testSumTotalsValues(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals(15, $c->sum());
    }

    public function testSumByKey(): void
    {
        $data = [['price' => 10], ['price' => 20]];
        $this->assertEquals(30, (new Collection($data))->sum('price'));
    }

    public function testAvgCalculatesAverage(): void
    {
        $c = new Collection([10, 20, 30]);
        $this->assertEquals(20.0, $c->avg());
    }

    public function testAvgReturnsZeroForEmpty(): void
    {
        $this->assertEquals(0.0, (new Collection([]))->avg());
    }

    public function testMinMax(): void
    {
        $c = new Collection([3, 1, 4, 1, 5]);
        $this->assertEquals(1, $c->min());
        $this->assertEquals(5, $c->max());
    }

    // -------------------------------------------------------------------------
    // Contains
    // -------------------------------------------------------------------------

    public function testContainsChecksValue(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(99));
    }

    public function testContainsChecksKeyValuePair(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        $c    = new Collection($data);
        $this->assertTrue($c->contains('name', 'Alice'));
        $this->assertFalse($c->contains('name', 'Zara'));
    }

    // -------------------------------------------------------------------------
    // Merge / Concat
    // -------------------------------------------------------------------------

    public function testMergeMergesCollections(): void
    {
        $a = new Collection([1, 2]);
        $b = new Collection([3, 4]);
        $this->assertEquals([1, 2, 3, 4], $a->merge($b)->toArray());
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function testToJsonProducesValidJson(): void
    {
        $c    = new Collection(['a' => 1, 'b' => 2]);
        $json = $c->toJson();
        $this->assertEquals(['a' => 1, 'b' => 2], json_decode($json, true));
    }

    public function testToArrayConvertsNestedCollections(): void
    {
        $inner = new Collection([1, 2]);
        $outer = new Collection([$inner]);
        $arr   = $outer->toArray();
        $this->assertSame([1, 2], $arr[0]);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess / Iterator
    // -------------------------------------------------------------------------

    public function testArrayAccess(): void
    {
        $c = new Collection([10, 20, 30]);
        $this->assertEquals(10, $c[0]);
        $this->assertEquals(20, $c[1]);
        $this->assertTrue(isset($c[2]));
        $this->assertFalse(isset($c[99]));
    }

    public function testForeachIteration(): void
    {
        $c   = new Collection([1, 2, 3]);
        $sum = 0;
        foreach ($c as $v) $sum += $v;
        $this->assertEquals(6, $sum);
    }

    public function testPutIsImmutable(): void
    {
        $c  = new Collection(['x' => 1]);
        $c2 = $c->put('y', 2);
        $this->assertEquals(2, $c2->get('y'));
        $this->assertNull($c->get('y')); // original unchanged
    }
}

// ===========================================================================
// Phase2Test — MySQL Grammar
// ===========================================================================

class Phase2MySqlGrammarTest extends TestCase
{
    private MySqlGrammar $g;

    public function setUp(): void
    {
        $this->g = new MySqlGrammar();
    }

    public function testCompileDrop(): void
    {
        $this->assertEquals('DROP TABLE `users`', $this->g->compileDrop('users'));
    }

    public function testCompileDropIfExists(): void
    {
        $this->assertEquals('DROP TABLE IF EXISTS `users`', $this->g->compileDropIfExists('users'));
    }

    public function testCompileRename(): void
    {
        $this->assertEquals('RENAME TABLE `old` TO `new`', $this->g->compileRename('old', 'new'));
    }

    public function testQuoteIdentifierEscapesBacktick(): void
    {
        $this->assertEquals('`back``tick`', $this->g->quoteIdentifier('back`tick'));
    }

    public function testCompileCreateMinimalTable(): void
    {
        $cols = [p2_mysql_col('id', 'id', ['autoIncrement' => true, 'unsigned' => true, 'primary' => true])];
        $sql  = $this->g->compileCreate('posts', $cols, []);
        $this->assertStringContainsString('CREATE TABLE `posts`', $sql);
        $this->assertStringContainsString('`id` BIGINT UNSIGNED', $sql);
        $this->assertStringContainsString('AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }

    public function testStringColumnNullableWithDefault(): void
    {
        $col = p2_mysql_col('email', 'string', ['length' => 191, 'nullable' => true, 'default' => null]);
        $sql = $this->g->compileColumn($col);
        $this->assertStringContainsString('VARCHAR(191)', $sql);
        $this->assertStringContainsString('NULL', $sql);
        $this->assertStringContainsString('DEFAULT NULL', $sql);
    }

    public function testBooleanColumn(): void
    {
        $col = p2_mysql_col('active', 'boolean');
        $this->assertStringContainsString('TINYINT(1)', $this->g->compileColumn($col));
    }

    public function testDecimalColumn(): void
    {
        $col = p2_mysql_col('price', 'decimal', ['total' => 10, 'places' => 2]);
        $this->assertStringContainsString('DECIMAL(10,2)', $this->g->compileColumn($col));
    }

    public function testTimestampColumn(): void
    {
        $col = p2_mysql_col('created_at', 'timestamp', ['nullable' => true, 'default' => null]);
        $sql = $this->g->compileColumn($col);
        $this->assertStringContainsString('TIMESTAMP', $sql);
        $this->assertStringContainsString('NULL', $sql);
    }

    public function testJsonColumn(): void
    {
        $col = p2_mysql_col('meta', 'json');
        $this->assertStringContainsString('JSON', $this->g->compileColumn($col));
    }

    public function testCompileAddColumn(): void
    {
        $col = p2_mysql_col('bio', 'text', ['nullable' => true]);
        $sql = $this->g->compileAddColumn('users', $col);
        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN', $sql);
    }

    public function testCompileDropColumn(): void
    {
        $this->assertEquals(
            'ALTER TABLE `users` DROP COLUMN `phone`',
            $this->g->compileDropColumn('users', 'phone')
        );
    }

    public function testUniqueKeyInCreate(): void
    {
        $cols = [p2_mysql_col('email', 'string', ['length' => 255])];
        $cmds = [['type' => 'unique', 'columns' => ['email'], 'name' => 'unique_users_email']];
        $sql  = $this->g->compileCreate('users', $cols, $cmds);
        $this->assertStringContainsString('UNIQUE KEY', $sql);
    }

    public function testForeignKeyInCreate(): void
    {
        $cols = [p2_mysql_col('user_id', 'integer', ['unsigned' => true])];
        $cmds = [[
            'type'       => 'foreign',
            'column'     => 'user_id',
            'name'       => 'fk_posts_user_id',
            'references' => 'id',
            'on'         => 'users',
            'onDelete'   => 'CASCADE',
            'onUpdate'   => 'RESTRICT',
        ]];
        $sql = $this->g->compileCreate('posts', $cols, $cmds);
        $this->assertStringContainsString('FOREIGN KEY', $sql);
        $this->assertStringContainsString('CASCADE', $sql);
    }
}

// ===========================================================================
// Phase2Test — Postgres Grammar
// ===========================================================================

class Phase2PostgresGrammarTest extends TestCase
{
    private PostgresGrammar $g;

    public function setUp(): void
    {
        $this->g = new PostgresGrammar();
    }

    public function testQuoteIdentifierUsesDoubleQuotes(): void
    {
        $this->assertEquals('"users"', $this->g->quoteIdentifier('users'));
    }

    public function testCompileCreateNoEngine(): void
    {
        $cols = [p2_mysql_col('id', 'id', ['autoIncrement' => true, 'primary' => true])];
        $sql  = $this->g->compileCreate('orders', $cols, []);
        $this->assertStringContainsString('CREATE TABLE "orders"', $sql);
        $this->assertFalse(str_contains($sql, 'ENGINE'));
    }

    public function testIdColumnIsBigserial(): void
    {
        $col = p2_mysql_col('id', 'id', ['autoIncrement' => true, 'primary' => true]);
        $this->assertStringContainsString('BIGSERIAL', $this->g->compileColumn($col));
    }

    public function testBooleanIsBoolean(): void
    {
        $col = p2_mysql_col('active', 'boolean');
        $this->assertStringContainsString('BOOLEAN', $this->g->compileColumn($col));
    }

    public function testCompileDrop(): void
    {
        $this->assertEquals('DROP TABLE "users"', $this->g->compileDrop('users'));
    }

    public function testDropIfExistsCascade(): void
    {
        $this->assertStringContainsString('CASCADE', $this->g->compileDropIfExists('users'));
    }

    public function testRenameUsesAlterTable(): void
    {
        $this->assertStringContainsString('ALTER TABLE', $this->g->compileRename('a', 'b'));
    }
}

// ===========================================================================
// Phase2Test — SQLite Grammar
// ===========================================================================

class Phase2SQLiteGrammarTest extends TestCase
{
    private SQLiteGrammar $g;

    public function setUp(): void
    {
        $this->g = new SQLiteGrammar();
    }

    public function testIdColumnAutoincrement(): void
    {
        $col = p2_mysql_col('id', 'id', ['autoIncrement' => true, 'primary' => true]);
        $sql = $this->g->compileColumn($col);
        $this->assertStringContainsString('INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    }

    public function testStringIsText(): void
    {
        $col = p2_mysql_col('name', 'string', ['length' => 255]);
        $this->assertStringContainsString('TEXT', $this->g->compileColumn($col));
    }

    public function testTableExistsUseSqliteMaster(): void
    {
        $this->assertStringContainsString('sqlite_master', $this->g->compileTableExists('users'));
    }

    public function testQuoteIdentifierUsesDoubleQuotes(): void
    {
        $this->assertEquals('"posts"', $this->g->quoteIdentifier('posts'));
    }

    public function testDropIfExists(): void
    {
        $this->assertEquals('DROP TABLE IF EXISTS "users"', $this->g->compileDropIfExists('users'));
    }
}

// ===========================================================================
// Phase2Test — Blueprint DSL
// ===========================================================================

class Phase2BlueprintTest extends TestCase
{
    public function testIdColumnAutoIncrementPrimary(): void
    {
        $bp  = new Blueprint('users');
        $bp->id();
        $col = $bp->getColumns()[0];
        $this->assertEquals('id', $col['name']);
        $this->assertEquals('id', $col['type']);
        $this->assertTrue($col['autoIncrement']);
        $this->assertTrue($col['primary']);
        $this->assertTrue($col['unsigned']);
    }

    public function testStringColumn(): void
    {
        $bp = new Blueprint('users');
        $bp->string('email', 100);
        $col = $bp->getColumns()[0];
        $this->assertEquals('string', $col['type']);
        $this->assertEquals(100, $col['length']);
    }

    public function testChainingNullableDefault(): void
    {
        $bp = new Blueprint('posts');
        $bp->string('title')->nullable()->default('Untitled');
        $col = $bp->getColumns()[0];
        $this->assertTrue($col['nullable']);
        $this->assertEquals('Untitled', $col['default']);
    }

    public function testTimestampsAddsCreatedAndUpdated(): void
    {
        $bp = new Blueprint('posts');
        $bp->timestamps();
        $names = array_column($bp->getColumns(), 'name');
        $this->assertContains('created_at', $names);
        $this->assertContains('updated_at', $names);
    }

    public function testSoftDeletesAddsDeletedAt(): void
    {
        $bp = new Blueprint('posts');
        $bp->softDeletes();
        $names = array_column($bp->getColumns(), 'name');
        $this->assertContains('deleted_at', $names);
    }

    public function testBooleanType(): void
    {
        $bp = new Blueprint('users');
        $bp->boolean('is_active');
        $this->assertEquals('boolean', $bp->getColumns()[0]['type']);
    }

    public function testDecimalStoresTotalAndPlaces(): void
    {
        $bp = new Blueprint('orders');
        $bp->decimal('amount', 12, 4);
        $col = $bp->getColumns()[0];
        $this->assertEquals(12, $col['total']);
        $this->assertEquals(4, $col['places']);
    }

    public function testUniqueAddsCommand(): void
    {
        $bp = new Blueprint('users');
        $bp->unique(['email']);
        $cmds = $bp->getCommands();
        $this->assertEquals(1, count($cmds));
        $this->assertEquals('unique', $cmds[0]['type']);
    }

    public function testForeignKeyChain(): void
    {
        $bp = new Blueprint('posts');
        $bp->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        $cmds = $bp->getCommands();
        $this->assertEquals(1, count($cmds));
        $this->assertEquals('foreign',  $cmds[0]['type']);
        $this->assertEquals('id',       $cmds[0]['references']);
        $this->assertEquals('users',    $cmds[0]['on']);
        $this->assertEquals('CASCADE',  $cmds[0]['onDelete']);
    }

    public function testIndexAddsCommand(): void
    {
        $bp = new Blueprint('posts');
        $bp->index('user_id');
        $this->assertEquals('index', $bp->getCommands()[0]['type']);
    }

    public function testFullCreateViaMysqlGrammar(): void
    {
        $bp = new Blueprint('articles');
        $bp->id();
        $bp->string('title');
        $bp->text('body')->nullable();
        $bp->boolean('published')->default(false);
        $bp->timestamps();

        $g   = new MySqlGrammar();
        $sql = $g->compileCreate('articles', $bp->getColumns(), [
            ['type' => 'unique', 'columns' => ['title'], 'name' => 'unique_articles_title'],
        ]);

        $this->assertStringContainsString('CREATE TABLE `articles`', $sql);
        $this->assertStringContainsString('`title` VARCHAR(255)', $sql);
        $this->assertStringContainsString('`body` TEXT', $sql);
        $this->assertStringContainsString('TINYINT(1)', $sql);
        $this->assertStringContainsString('UNIQUE KEY', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }

    public function testDropColumnAddsCommand(): void
    {
        $bp = new Blueprint('users');
        $bp->dropColumn('phone');
        $this->assertEquals('dropColumn', $bp->getCommands()[0]['type']);
        $this->assertEquals('phone', $bp->getCommands()[0]['name']);
    }

    public function testRenameColumnAddsCommand(): void
    {
        $bp = new Blueprint('users');
        $bp->renameColumn('fname', 'first_name');
        $cmds = $bp->getCommands();
        $this->assertEquals('renameColumn', $cmds[0]['type']);
        $this->assertEquals('fname',      $cmds[0]['from']);
        $this->assertEquals('first_name', $cmds[0]['to']);
    }
}

// ===========================================================================
// Phase2Test — GrammarInterface compliance
// ===========================================================================

class Phase2GrammarInterfaceTest extends TestCase
{
    public function testMysqlImplementsInterface(): void
    {
        $this->assertInstanceOf(GrammarInterface::class, new MySqlGrammar());
    }

    public function testPostgresImplementsInterface(): void
    {
        $this->assertInstanceOf(GrammarInterface::class, new PostgresGrammar());
    }

    public function testSqliteImplementsInterface(): void
    {
        $this->assertInstanceOf(GrammarInterface::class, new SQLiteGrammar());
    }

    public function testDatabaseGrammarSetAndGet(): void
    {
        $g = new MySqlGrammar();
        \Nemesis\Core\Database::setGrammar($g);
        $this->assertInstanceOf(MySqlGrammar::class, \Nemesis\Core\Database::getGrammar());

        \Nemesis\Core\Database::setGrammar(new SQLiteGrammar());
        $this->assertInstanceOf(SQLiteGrammar::class, \Nemesis\Core\Database::getGrammar());

        \Nemesis\Core\Database::disconnect(); // clean up
    }
}
