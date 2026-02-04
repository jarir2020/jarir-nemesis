<?php

namespace Tests\Feature;

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestCase;
use Nemesis\Support\URL;
use Nemesis\Support\WebHook;
use Nemesis\Middleware\DebugBar;
use Nemesis\Http\Request;

class FinalPerfectionTest extends TestCase {

    public function test_signed_urls() {
        putenv('APP_KEY=testkey123'); // Ensure stable key
        $url = "http://localhost/download/file.pdf";
        $signed = URL::sign($url, 3600); // 1 hour
        
        $this->assertStringContainsString('signature=', $signed);
        $this->assertStringContainsString('expires=', $signed);
        
        // Correct signature
        $this->assertTrue(URL::verifySign($signed));
        
        // Tampered signature
        $tampered = $signed . "extra";
        $this->assertFalse(URL::verifySign($tampered));
        
        // Expired signature
        $expired = URL::sign($url, -100); // Already expired
        $this->assertFalse(URL::verifySign($expired));
        echo "test_signed_urls: PASS\n";
    }

    public function test_debug_bar_injection() {
        $middleware = new DebugBar();
        $request = new Request(); // Mock request
        
        $response = $middleware->handle($request, function($req) {
            return "<html><body>Hello World</body></html>";
        });
        
        $this->assertStringContainsString('nemesis-debug-bar', $response);
        $this->assertStringContainsString('⏱️', $response);
        $this->assertStringContainsString('💾', $response);
        echo "test_debug_bar_injection: PASS\n";
    }

    public function test_webhook_dispatcher() {
        // We'll test with a non-existent local port to verify it returns an error structure correctly
        $result = WebHook::dispatch('http://localhost:9999/null', ['ping' => 'pong']);
        
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertFalse($result['success']); // Should fail on closed port
        echo "test_webhook_dispatcher: PASS\n";
    }
}

// Self-execute manually for speed/clarity
$test = new FinalPerfectionTest();
echo "--- Final Perfection Feature Tests ---\n";
$test->test_signed_urls();
$test->test_debug_bar_injection();
$test->test_webhook_dispatcher();
echo "--- Tests Passed ---\n";
