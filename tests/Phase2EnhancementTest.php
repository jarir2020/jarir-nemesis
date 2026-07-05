<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Core\Model;
use Nemesis\Core\Validator;
use Nemesis\Http\JsonResource;
use Nemesis\Http\Response;
use Nemesis\Testing\TestCase;

Config::load(__DIR__ . '/../');
$config = require __DIR__ . '/../config/config.php';
Database::connect($config['database']);

Database::connect()->exec('CREATE TABLE IF NOT EXISTS phase2_articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    category_id INTEGER NULL,
    price INTEGER NOT NULL DEFAULT 0,
    published_at DATETIME NULL
)');
Database::connect()->exec('DELETE FROM phase2_articles');

Database::connect()->exec("INSERT INTO phase2_articles (name, status, category_id, price, published_at) VALUES
    ('Laravel Blaze', 'draft', 1, 30, '2026-01-01 10:00:00'),
    ('Vue Dashboard', 'published', 1, 80, '2026-01-02 10:00:00'),
    ('React Login', 'published', 2, 120, '2026-01-03 10:00:00'),
    ('Ghost Landing', 'archived', 3, 10, '2025-12-31 10:00:00')
");

class Phase2Article extends Model
{
    protected $table = 'phase2_articles';
}

class Phase2ArticleResource extends JsonResource
{
    public function toArray()
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'status' => $this->resource['status'] ?? null,
        ];
    }
}

class Phase2EnhancementTest extends TestCase
{
    public function testValidatorSupportsExpandedRules(): void
    {
        $validator = new Validator();

        $this->assertTrue($validator->validate([
            'display_name' => null,
            'status' => 'published',
            'publish_at' => '2026-01-01',
            'expiry_at' => '2026-02-01',
            'code' => '12345',
            'pin' => '1234',
            'reference' => '550e8400-e29b-41d4-a716-446655440000',
            'approved' => 'yes',
            'email' => 'ada@example.com',
            'email_confirmation' => 'ada@example.com',
        ], [
            'display_name' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'publish_at' => 'required|date|before:2026-02-01',
            'expiry_at' => 'required|date|after:2026-01-01',
            'code' => 'digits:5',
            'pin' => 'digits_between:4,6',
            'reference' => 'uuid',
            'approved' => 'accepted',
            'email' => 'same:email_confirmation',
        ]));

        $this->assertTrue($validator->validate([], [
            'nickname' => 'sometimes|string',
        ]));

        $this->assertFalse($validator->validate([], [
            'nickname' => 'present|string',
        ]));

        $errors = $validator->errors();
        $this->assertContains('nickname', array_keys($errors));
    }

    public function testValidatorSupportsConditionalAndProhibitiveRules(): void
    {
        $validator = new Validator();

        $this->assertTrue($validator->validate([
            'status' => 'published',
            'publish_note' => 'Ready to go',
            'feature_flag' => 'yes',
            'meta' => ['title' => 'Hello', 'summary' => 'World'],
        ], [
            'publish_note' => 'required_if:status,published',
            'feature_flag' => 'accepted_if:status,published',
            'meta' => 'required_array_keys:title,summary',
        ]));

        $this->assertFalse($validator->validate([
            'status' => 'published',
            'publish_note' => '',
            'feature_flag' => 'no',
            'secret' => 'present',
            'extra' => 'value',
            'meta' => ['title' => 'Hello'],
        ], [
            'publish_note' => 'required_if:status,published',
            'feature_flag' => 'accepted_if:status,published',
            'secret' => 'prohibited_if:status,published',
            'extra' => 'prohibits:secret',
            'meta' => 'required_array_keys:title,summary',
        ]));

        $errorKeys = array_keys($validator->errors());
        $this->assertContains('publish_note', $errorKeys);
        $this->assertContains('feature_flag', $errorKeys);
        $this->assertContains('secret', $errorKeys);
        $this->assertContains('extra', $errorKeys);
        $this->assertContains('meta', $errorKeys);
    }

    public function testBuilderSearchFilterSortAndPaginationHelpers(): void
    {
        $builder = Phase2Article::query()
            ->search(['name', 'status'], 'Vue')
            ->filter([
                'status' => 'published',
                'price' => ['operator' => '>=', 'value' => 50],
            ])
            ->sort([
                'price' => 'desc',
                'name' => 'asc',
            ]);

        $results = $builder->get();
        $this->assertCount(1, $results);
        $this->assertSame('Vue Dashboard', $results->first()->name);

        $this->assertSame(1, $builder->count());

        $blockedByFilter = Phase2Article::query()
            ->search(['name', 'status'], 'Vue')
            ->filter(['status' => 'draft'])
            ->get();
        $this->assertCount(0, $blockedByFilter);

        $_GET['page'] = 1;
        $paginator = Phase2Article::query()->sort('price', 'desc')->paginateFromRequest(2);
        $this->assertSame(2, $paginator->perPage());
        $this->assertSame(1, $paginator->currentPage());
        $this->assertCount(2, $paginator->items());

        unset($_GET['page']);
    }

    public function testBuilderSupportsConditionalAndLikeHelpers(): void
    {
        $builder = Phase2Article::query()
            ->whereLike('name', '%Vue%')
            ->orWhereNotLike('status', '%draft%')
            ->when(true, function ($query): void {
                $query->where('price', '>=', 50);
            })
            ->unless(false, function ($query): void {
                $query->where('status', '=', 'published');
            });

        $results = $builder->get();

        $this->assertCount(2, $results);
        $this->assertSame('Vue Dashboard', $results->first()->name);
    }

    public function testJsonResourceResponseConventions(): void
    {
        $resource = new Phase2ArticleResource([
            'id' => 2,
            'name' => 'Vue Dashboard',
            'status' => 'published',
        ]);

        $response = $resource->response('Article loaded');
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());

        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('Article loaded', $payload['message']);
        $this->assertSame('Vue Dashboard', $payload['data']['name']);

        $collectionResponse = Phase2ArticleResource::collectionResponse([
            ['id' => 1, 'name' => 'Laravel Blaze', 'status' => 'draft'],
            ['id' => 2, 'name' => 'Vue Dashboard', 'status' => 'published'],
        ], 'Articles listed');

        $collectionPayload = json_decode($collectionResponse->getContent(), true);
        $this->assertCount(2, $collectionPayload['data']);
        $this->assertSame('Articles listed', $collectionPayload['message']);
    }
}

$test = new Phase2EnhancementTest();

echo "--- Phase 2 Enhancement Test ---\n";

foreach ([
    'testValidatorSupportsExpandedRules',
    'testValidatorSupportsConditionalAndProhibitiveRules',
    'testBuilderSearchFilterSortAndPaginationHelpers',
    'testBuilderSupportsConditionalAndLikeHelpers',
    'testJsonResourceResponseConventions',
] as $method) {
    echo "Running {$method}... ";
    try {
        $test->setUp();
        $test->{$method}();
        $test->tearDown();
        echo "PASS\n";
    } catch (\Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Phase 2 Enhancement Test Complete ---\n";
