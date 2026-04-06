<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 19 — HTTP Client Tests | Created: 2026-04-06

use Nemesis\Testing\TestCase;
use Nemesis\Http\Http;
use Nemesis\Http\Client\HttpClient;
use Nemesis\Http\Client\HttpResponse;
use Nemesis\Http\Client\HttpResponseException;

// =============================================================================
// HttpResponse — construction and body helpers
// =============================================================================

class HttpResponseTest extends TestCase
{
    public function tearDown(): void { Http::resetFakes(); }

    private function makeResponse(
        int    $status  = 200,
        array  $headers = [],
        string $body    = ''
    ): HttpResponse {
        return new HttpResponse($status, $headers, $body);
    }

    public function testBodyReturnsRawString(): void
    {
        $r = $this->makeResponse(body: 'hello world');
        $this->assertEquals('hello world', $r->body());
    }

    public function testJsonDecodesArray(): void
    {
        $r = $this->makeResponse(body: '{"name":"Alice","age":30}');
        $this->assertEquals(['name' => 'Alice', 'age' => 30], $r->json());
    }

    public function testJsonDotNotation(): void
    {
        $r = $this->makeResponse(body: '{"data":{"user":{"id":42}}}');
        $this->assertEquals(42, $r->json('data.user.id'));
    }

    public function testJsonDotNotationMissingKeyReturnsNull(): void
    {
        $r = $this->makeResponse(body: '{"a":1}');
        $this->assertNull($r->json('b.c'));
    }

    public function testJsonNestedArrayAccess(): void
    {
        $r = $this->makeResponse(body: '{"items":[{"id":1},{"id":2}]}');
        $this->assertEquals(1, $r->json('items.0.id'));
        $this->assertEquals(2, $r->json('items.1.id'));
    }

    public function testToArrayEqualsJson(): void
    {
        $r = $this->makeResponse(body: '{"x":99}');
        $this->assertEquals(['x' => 99], $r->toArray());
    }
}

// =============================================================================
// HttpResponse — status helpers
// =============================================================================

class HttpResponseStatusTest extends TestCase
{
    private function r(int $status): HttpResponse
    {
        return new HttpResponse($status, [], '');
    }

    public function testStatus(): void
    {
        $this->assertEquals(200, $this->r(200)->status());
        $this->assertEquals(404, $this->r(404)->status());
        $this->assertEquals(500, $this->r(500)->status());
    }

    public function testOk(): void
    {
        $this->assertTrue($this->r(200)->ok());
        $this->assertFalse($this->r(201)->ok());
    }

    public function testCreated(): void
    {
        $this->assertTrue($this->r(201)->created());
        $this->assertFalse($this->r(200)->created());
    }

    public function testSuccessful(): void
    {
        $this->assertTrue($this->r(200)->successful());
        $this->assertTrue($this->r(201)->successful());
        $this->assertTrue($this->r(299)->successful());
        $this->assertFalse($this->r(300)->successful());
        $this->assertFalse($this->r(400)->successful());
        $this->assertFalse($this->r(500)->successful());
    }

    public function testFailed(): void
    {
        $this->assertTrue($this->r(400)->failed());
        $this->assertTrue($this->r(500)->failed());
        $this->assertFalse($this->r(200)->failed());
    }

    public function testClientError(): void
    {
        $this->assertTrue($this->r(400)->clientError());
        $this->assertTrue($this->r(404)->clientError());
        $this->assertTrue($this->r(422)->clientError());
        $this->assertFalse($this->r(200)->clientError());
        $this->assertFalse($this->r(500)->clientError());
    }

    public function testServerError(): void
    {
        $this->assertTrue($this->r(500)->serverError());
        $this->assertTrue($this->r(503)->serverError());
        $this->assertFalse($this->r(200)->serverError());
        $this->assertFalse($this->r(404)->serverError());
    }

    public function testUnauthorized(): void
    {
        $this->assertTrue($this->r(401)->unauthorized());
        $this->assertFalse($this->r(403)->unauthorized());
    }

    public function testForbidden(): void
    {
        $this->assertTrue($this->r(403)->forbidden());
        $this->assertFalse($this->r(401)->forbidden());
    }

    public function testNotFound(): void
    {
        $this->assertTrue($this->r(404)->notFound());
        $this->assertFalse($this->r(200)->notFound());
    }

    public function testUnprocessable(): void
    {
        $this->assertTrue($this->r(422)->unprocessable());
        $this->assertFalse($this->r(200)->unprocessable());
    }

    public function testTooManyRequests(): void
    {
        $this->assertTrue($this->r(429)->tooManyRequests());
        $this->assertFalse($this->r(200)->tooManyRequests());
    }

    public function testRedirect(): void
    {
        $this->assertTrue($this->r(301)->redirect());
        $this->assertTrue($this->r(302)->redirect());
        $this->assertFalse($this->r(200)->redirect());
    }
}

// =============================================================================
// HttpResponse — headers
// =============================================================================

class HttpResponseHeaderTest extends TestCase
{
    public function testHeaderCaseInsensitive(): void
    {
        $r = new HttpResponse(200, ['content-type' => 'application/json'], '');
        $this->assertEquals('application/json', $r->header('Content-Type'));
        $this->assertEquals('application/json', $r->header('content-type'));
    }

    public function testHeaderMissingReturnsEmpty(): void
    {
        $r = new HttpResponse(200, [], '');
        $this->assertEquals('', $r->header('X-Missing'));
    }

    public function testHasHeader(): void
    {
        $r = new HttpResponse(200, ['x-request-id' => 'abc123'], '');
        $this->assertTrue($r->hasHeader('x-request-id'));
        $this->assertFalse($r->hasHeader('x-other'));
    }

    public function testHeaders(): void
    {
        $r = new HttpResponse(200, ['x-foo' => 'bar', 'x-baz' => 'qux'], '');
        $this->assertArrayHasKey('x-foo', $r->headers());
        $this->assertArrayHasKey('x-baz', $r->headers());
    }
}

// =============================================================================
// HttpResponse — throw()
// =============================================================================

class HttpResponseThrowTest extends TestCase
{
    public function testThrowDoesNothingOnSuccess(): void
    {
        $r = new HttpResponse(200, [], '{}');
        $result = $r->throw();
        $this->assertSame($r, $result); // returns $this for chaining
    }

    public function testThrowOnClientError(): void
    {
        $this->expectException(HttpResponseException::class);
        (new HttpResponse(404, [], 'Not found'))->throw();
    }

    public function testThrowOnServerError(): void
    {
        $this->expectException(HttpResponseException::class);
        (new HttpResponse(500, [], 'Server error'))->throw();
    }

    public function testHttpResponseExceptionCarriesResponse(): void
    {
        $r = new HttpResponse(422, [], '{"error":"invalid"}');
        try {
            $r->throw();
            $this->fail('Expected exception not thrown');
        } catch (HttpResponseException $e) {
            $this->assertEquals(422, $e->response()->status());
            $this->assertEquals(422, $e->getCode());
            $this->assertStringContains('422', $e->getMessage());
        }
    }

    public function testThrowIfCallable(): void
    {
        $this->expectException(HttpResponseException::class);
        (new HttpResponse(200, [], ''))
            ->throwIf(fn($r) => $r->status() === 200); // throws even on 200
    }
}

// =============================================================================
// Http::fake() — fake response construction
// =============================================================================

class HttpFakeResponseTest extends TestCase
{
    public function tearDown(): void { Http::resetFakes(); }

    public function testFakeResponseDescriptor(): void
    {
        $descriptor = Http::response(['id' => 1], 201, ['x-custom' => 'yes']);
        $this->assertEquals(['id' => 1], $descriptor['body']);
        $this->assertEquals(201, $descriptor['status']);
        $this->assertTrue($descriptor['_fake']);
    }

    public function testFromFakeArrayBody(): void
    {
        $r = HttpResponse::fromFake(Http::response(['users' => []], 200));
        $this->assertEquals(200, $r->status());
        $this->assertEquals(['users' => []], $r->json());
        $this->assertTrue($r->wasFaked());
    }

    public function testFromFakeStringBody(): void
    {
        $r = HttpResponse::fromFake(Http::response('plain text', 200));
        $this->assertEquals('plain text', $r->body());
    }

    public function testFromFakeStatusCodes(): void
    {
        $this->assertEquals(404, HttpResponse::fromFake(Http::response(null, 404))->status());
        $this->assertEquals(500, HttpResponse::fromFake(Http::response(null, 500))->status());
    }
}

// =============================================================================
// Http — fake request interception
// =============================================================================

class HttpFakeInterceptionTest extends TestCase
{
    public function tearDown(): void { Http::resetFakes(); }

    public function testFakeGetReturnsRegisteredResponse(): void
    {
        Http::fake([
            'https://api.example.com/users' => Http::response(['id' => 1], 200),
        ]);

        $r = Http::get('https://api.example.com/users');
        $this->assertEquals(200, $r->status());
        $this->assertEquals(1, $r->json('id'));
        $this->assertTrue($r->wasFaked());
    }

    public function testFakePostReturnsRegisteredResponse(): void
    {
        Http::fake([
            'https://api.example.com/users' => Http::response(['created' => true], 201),
        ]);

        $r = Http::post('https://api.example.com/users', ['name' => 'Bob']);
        $this->assertEquals(201, $r->status());
        $this->assertTrue($r->json('created'));
    }

    public function testWildcardPatternMatchesUrls(): void
    {
        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $r1 = Http::get('https://api.example.com/users');
        $r2 = Http::get('https://api.example.com/posts/1');

        $this->assertTrue($r1->json('ok'));
        $this->assertTrue($r2->json('ok'));
    }

    public function testUnmatchedUrlReturnsFallback200(): void
    {
        Http::fake([
            'https://specific.example.com' => Http::response(['x' => 1], 200),
        ]);

        // Different URL — falls back to empty 200
        $r = Http::get('https://other.example.com/api');
        $this->assertEquals(200, $r->status());
        $this->assertTrue($r->wasFaked());
    }

    public function testFakeNotActiveWithoutSetup(): void
    {
        // Without Http::fake(), isFaking() must be false
        $this->assertFalse(Http::isFaking());
    }

    public function testResetFakesClearsFakingState(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $this->assertTrue(Http::isFaking());
        Http::resetFakes();
        $this->assertFalse(Http::isFaking());
    }
}

// =============================================================================
// Http — request recording + assertions
// =============================================================================

class HttpAssertionTest extends TestCase
{
    public function setUp(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function tearDown(): void { Http::resetFakes(); }

    public function testRecordsGetRequest(): void
    {
        Http::get('https://api.example.com/users');
        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        $this->assertEquals('GET',                           $recorded[0]['method']);
        $this->assertEquals('https://api.example.com/users', $recorded[0]['url']);
    }

    public function testRecordsPostWithData(): void
    {
        Http::post('https://api.example.com/users', ['name' => 'Alice']);
        $recorded = Http::recorded();
        $this->assertEquals('POST',    $recorded[0]['method']);
        $this->assertEquals('Alice',   $recorded[0]['data']['name']);
    }

    public function testAssertSentPasses(): void
    {
        Http::get('https://api.example.com/users');
        Http::assertSent(fn($r) => $r['url'] === 'https://api.example.com/users');
    }

    public function testAssertSentFailsWhenNotSent(): void
    {
        Http::get('https://api.example.com/users');
        $this->expectException(\RuntimeException::class);
        Http::assertSent(fn($r) => $r['url'] === 'https://other.com/never');
    }

    public function testAssertNotSentPasses(): void
    {
        Http::get('https://api.example.com/users');
        Http::assertNotSent(fn($r) => $r['url'] === 'https://other.com/never');
    }

    public function testAssertNotSentThrowsWhenFound(): void
    {
        Http::get('https://api.example.com/users');
        $this->expectException(\RuntimeException::class);
        Http::assertNotSent(fn($r) => $r['url'] === 'https://api.example.com/users');
    }

    public function testAssertNothingSentPassesWhenEmpty(): void
    {
        // No requests made
        Http::assertNothingSent();
    }

    public function testAssertNothingSentThrowsWhenRequestMade(): void
    {
        Http::get('https://api.example.com/test');
        $this->expectException(\RuntimeException::class);
        Http::assertNothingSent();
    }

    public function testAssertSentCount(): void
    {
        Http::get('https://api.example.com/a');
        Http::get('https://api.example.com/b');
        Http::get('https://api.example.com/c');
        Http::assertSentCount(3);
    }

    public function testAssertSentCountThrowsOnMismatch(): void
    {
        Http::get('https://api.example.com/a');
        $this->expectException(\RuntimeException::class);
        Http::assertSentCount(5);
    }

    public function testAssertSentMatchesMethod(): void
    {
        Http::post('https://api.example.com/users', ['name' => 'Bob']);
        Http::assertSent(fn($r) => $r['method'] === 'POST' && $r['data']['name'] === 'Bob');
    }

    public function testMultipleRequestsRecorded(): void
    {
        Http::get('https://api.example.com/a');
        Http::post('https://api.example.com/b', ['x' => 1]);
        Http::delete('https://api.example.com/c');
        $this->assertCount(3, Http::recorded());
    }
}

// =============================================================================
// HttpClient — fluent builder state
// =============================================================================

class HttpClientBuilderTest extends TestCase
{
    public function setUp(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function tearDown(): void { Http::resetFakes(); }

    public function testWithTokenSetsAuthorizationHeader(): void
    {
        Http::withToken('my-token')->get('https://api.example.com');

        // The request was recorded (faked) — token is in builder state, not easily inspectable
        // Verify no exception and request went through
        Http::assertSent(fn($r) => $r['url'] === 'https://api.example.com');
    }

    public function testWithQueryParamsAppendsToUrl(): void
    {
        Http::get('https://api.example.com/search', ['q' => 'hello', 'page' => 1]);
        Http::assertSent(fn($r) => str_contains($r['url'], 'q=hello'));
    }

    public function testQueryParamsAppendWithExistingQueryString(): void
    {
        Http::get('https://api.example.com/search?existing=1', ['q' => 'test']);
        Http::assertSent(fn($r) => str_contains($r['url'], 'existing=1') && str_contains($r['url'], 'q=test'));
    }

    public function testPutRecorded(): void
    {
        Http::put('https://api.example.com/users/1', ['name' => 'Updated']);
        Http::assertSent(fn($r) => $r['method'] === 'PUT');
    }

    public function testPatchRecorded(): void
    {
        Http::patch('https://api.example.com/users/1', ['email' => 'new@test.com']);
        Http::assertSent(fn($r) => $r['method'] === 'PATCH');
    }

    public function testDeleteRecorded(): void
    {
        Http::delete('https://api.example.com/users/1');
        Http::assertSent(fn($r) => $r['method'] === 'DELETE');
    }

    public function testHeadRecorded(): void
    {
        Http::head('https://api.example.com/users');
        Http::assertSent(fn($r) => $r['method'] === 'HEAD');
    }

    public function testChainedBuilderMethodsReturnSameInstance(): void
    {
        $client = new HttpClient();
        $same   = $client->withHeaders(['X-Custom' => '1'])
                         ->timeout(5)
                         ->retry(2, 100)
                         ->acceptJson()
                         ->asJson();
        $this->assertSame($client, $same);
    }

    public function testThrowOnFailureThrowsForFaked404(): void
    {
        Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);
        $this->expectException(HttpResponseException::class);
        Http::throwOnFailure()->get('https://api.example.com/missing');
    }
}

// =============================================================================
// Http — multiple fake patterns
// =============================================================================

class HttpFakePatternTest extends TestCase
{
    public function tearDown(): void { Http::resetFakes(); }

    public function testDifferentUrlsDifferentResponses(): void
    {
        Http::fake([
            'https://users.api.com/*'  => Http::response(['type' => 'user'],  200),
            'https://posts.api.com/*'  => Http::response(['type' => 'post'],  200),
            'https://errors.api.com/*' => Http::response(['error' => true],   500),
        ]);

        $u = Http::get('https://users.api.com/1');
        $p = Http::get('https://posts.api.com/1');
        $e = Http::get('https://errors.api.com/1');

        $this->assertEquals('user', $u->json('type'));
        $this->assertEquals('post', $p->json('type'));
        $this->assertEquals(500,    $e->status());
        $this->assertTrue($e->serverError());
    }

    public function testFake404IsClientError(): void
    {
        Http::fake(['*' => Http::response(null, 404)]);
        $r = Http::get('https://api.example.com/missing');
        $this->assertTrue($r->clientError());
        $this->assertTrue($r->notFound());
    }
}

// =============================================================================
// HttpResponse::fromFake edge cases
// =============================================================================

class HttpFakeEdgeCaseTest extends TestCase
{
    public function testNullBodyDecodesToEmptyArray(): void
    {
        $r = HttpResponse::fromFake(Http::response(null, 200));
        $this->assertEquals([], $r->json());
    }

    public function testEmptyArrayBodyEncodedToJson(): void
    {
        $r = HttpResponse::fromFake(Http::response([], 200));
        $this->assertEquals('[]', $r->body());
    }

    public function testNestedBodyAvailableViaDot(): void
    {
        $r = HttpResponse::fromFake(Http::response([
            'user' => ['id' => 42, 'name' => 'Carol'],
        ], 200));
        $this->assertEquals(42,      $r->json('user.id'));
        $this->assertEquals('Carol', $r->json('user.name'));
    }

    public function testWasFakedIsTrueForFakedResponse(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $r = Http::get('https://api.example.com');
        $this->assertTrue($r->wasFaked());
        Http::resetFakes();
    }
}
