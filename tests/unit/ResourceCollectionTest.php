<?php
declare(strict_types=1);

use Nemesis\Core\Paginator;
use Nemesis\Http\JsonResource;
use Nemesis\Http\ResourceCollection;
use Nemesis\Http\Response;
use Nemesis\Support\Collection;
use Nemesis\Testing\TestCase;

class Phase2ResourceItem extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'label' => strtoupper((string) $this->resource['label']),
        ];
    }
}

class Phase2ResourceItemSource
{
    public function toArray(): array
    {
        return [
            ['id' => 9, 'label' => 'ninth'],
        ];
    }
}

class ResourceCollectionTest extends TestCase
{
    public function testResourceCollectionMapsArraysAndRemainsCountableAndIterable(): void
    {
        $collection = Phase2ResourceItem::resourceCollection([
            ['id' => 1, 'label' => 'first'],
            ['id' => 2, 'label' => 'second'],
        ]);

        $this->assertInstanceOf(ResourceCollection::class, $collection);
        $this->assertSame([
            ['id' => 1, 'label' => 'FIRST'],
            ['id' => 2, 'label' => 'SECOND'],
        ], $collection->toArray());
        $this->assertSame($collection->resolve(), json_decode(json_encode($collection), true));
        $this->assertSame(2, count($collection));

        $resources = iterator_to_array($collection);
        $this->assertInstanceOf(Phase2ResourceItem::class, $resources[0]);
        $this->assertSame('SECOND', $resources[1]->toArray()['label']);
    }

    public function testEmptyCollectionResolvesWithoutItems(): void
    {
        $collection = Phase2ResourceItem::resourceCollection([]);

        $this->assertSame([], $collection->toArray());
        $this->assertSame(['data' => []], $collection->resolve());
        $this->assertSame(0, count($collection));
    }

    public function testJsonResourceCollectionKeepsHistoricalArrayReturnType(): void
    {
        $collection = Phase2ResourceItem::collection([
            ['id' => 1, 'label' => 'first'],
        ]);

        $this->assertIsArray($collection);
        $this->assertSame([['id' => 1, 'label' => 'FIRST']], $collection);
    }

    public function testCollectionObjectIsAcceptedAsInput(): void
    {
        $items = new Collection([
            ['id' => 3, 'label' => 'third'],
            ['id' => 4, 'label' => 'fourth'],
        ]);

        $collection = new ResourceCollection($items, Phase2ResourceItem::class);

        $this->assertSame(2, count($collection));
        $this->assertSame('FOURTH', $collection->toArray()[1]['label']);
    }

    public function testObjectWithToArrayIsAcceptedAsInput(): void
    {
        $collection = new ResourceCollection(new Phase2ResourceItemSource(), Phase2ResourceItem::class);

        $this->assertSame([['id' => 9, 'label' => 'NINTH']], $collection->toArray());
    }

    public function testPaginatorMetadataIsResolvedAlongsideMappedItems(): void
    {
        $paginator = new Paginator([
            ['id' => 5, 'label' => 'fifth'],
            ['id' => 6, 'label' => 'sixth'],
        ], 6, 2, 2);

        $resolved = Phase2ResourceItem::resourceCollection($paginator)->resolve();

        $this->assertSame([
            ['id' => 5, 'label' => 'FIFTH'],
            ['id' => 6, 'label' => 'SIXTH'],
        ], $resolved['data']);
        $this->assertSame(6, $resolved['meta']['total']);
        $this->assertSame(2, $resolved['meta']['current_page']);
        $this->assertSame(3, $resolved['meta']['last_page']);
        $this->assertSame(3, $resolved['meta']['from']);
        $this->assertSame(4, $resolved['meta']['to']);
    }

    public function testAdditionalMetadataAndResponseEnvelopeArePreserved(): void
    {
        $response = Phase2ResourceItem::resourceCollection([
            ['id' => 7, 'label' => 'seventh'],
        ])->additional([
            'trace_id' => 'trace-7',
        ])->toResponse('Items listed', 206);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(206, $response->getStatus());

        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('Items listed', $payload['message']);
        $this->assertSame('trace-7', $payload['trace_id']);
        $this->assertSame('SEVENTH', $payload['data'][0]['label']);
    }

    public function testCollectionResponseUsesResourceCollectionForPagination(): void
    {
        $paginator = new Paginator([
            ['id' => 8, 'label' => 'eighth'],
        ], 4, 1, 1);

        $response = Phase2ResourceItem::collectionResponse($paginator, 'Items listed');
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Items listed', $payload['message']);
        $this->assertSame('EIGHTH', $payload['data'][0]['label']);
        $this->assertSame(4, $payload['meta']['total']);
    }
}
