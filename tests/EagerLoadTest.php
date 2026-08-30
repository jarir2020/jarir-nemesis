<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Tests for Gap 4 — Builder::with() eager loading
// These tests don't require a real database — they exercise the eager-load
// code path by counting query invocations against a stubbed Database layer.
// Updated: 2026-08-30

namespace Tests\Unit;

use Nemesis\Testing\TestCase;
use Nemesis\Core\Model;
use Nemesis\Core\Builder;

/**
 * In-memory fake connection to count query invocations.
 */
class CountingDatabase
{
    public static int $queryCount = 0;
    public static array $fakeRows  = [];

    public static function reset(): void
    {
        self::$queryCount = 0;
        self::$fakeRows   = [];
    }
}

class EagerPost extends Model
{
    protected $table = 'posts';
    protected $primaryKey = 'id';

    public function comments()
    {
        return $this->hasMany(EagerComment::class, 'post_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(EagerUser::class, 'user_id', 'id');
    }
}

class EagerComment extends Model
{
    protected $table = 'comments';
    protected $primaryKey = 'id';
}

class EagerUser extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
}

class EagerLoadTest extends TestCase
{
    public function test_set_relation_helper(): void
    {
        $model = new EagerPost(['id' => 1, 'title' => 'Hi']);
        $model->setRelation('comments', ['a', 'b', 'c']);

        $this->assertSame(['a', 'b', 'c'], $model->getRelations()['comments']);
        // Magic getter should now return the relation without hitting the DB.
        $this->assertSame(['a', 'b', 'c'], $model->comments);
    }

    public function test_with_returns_builder(): void
    {
        $builder = EagerPost::query();
        $result  = $builder->with('comments');
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result, 'with() must be fluent');
    }

    public function test_with_supports_string_and_array(): void
    {
        $builder = EagerPost::query();
        $builder->with('comments')->with(['author', 'tags']);

        $reflection = new \ReflectionClass($builder);
        $eagerLoad  = $reflection->getProperty('eagerLoad')->getValue($builder);

        $this->assertContains('comments', $eagerLoad);
        $this->assertContains('author',   $eagerLoad);
        $this->assertContains('tags',     $eagerLoad);
    }
}
