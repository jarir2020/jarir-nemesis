<?php
namespace Nemesis\Plugins\DebugBar;

class DebugBarMiddleware {
    protected $collector;

    public function __construct() {
        $this->collector = new DataCollector();
    }

    public function handle($request, $next) {
        ob_start();
        $response = $next($request);
        $output = ob_get_clean();

        // If response is a Response object, use it.
        // If response is null/string, combine with captured output.
        if (is_object($response) && method_exists($response, 'getContent')) {
            // It's a Response object, usually we assume it handles its own content.
            // But if there was echoed output, we might want to append? 
            // Usually controllers shouldn't mix echo and Response objects.
            // Let's assume Response object takes precedence.
        } else {
            // It's likely a string or null (from echo)
            if (is_string($response)) {
                $output .= $response;
            }
            $response = $output;
        }

        // Only inject into HTML responses
        if ($this->shouldInject($response)) {
            $data = $this->collector->collect();
            $content = $this->render($data);
            $this->injectContent($response, $content);
        }

        return $response;
    }

    protected function shouldInject($response) {
        // Check if response is HTML and not AJAX/JSON
        // In Nemesis, response might be a string or object. 
        // For now assume string or object with headers.
        
        // Simple check: content-type header
        $headers = headers_list();
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return false; // Not HTML
            }
        }
        
        // Don't inject on AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return false;
        }

        return true;
    }

    protected function render($data) {
        ob_start();
        include __DIR__ . '/../views/bar.php';
        return ob_get_clean();
    }

    protected function injectContent(&$response, $content) {
        // If response is a string (primitive output approach)
        if (is_string($response)) {
            $pos = strripos($response, '</body>');
            if ($pos !== false) {
                $response = substr($response, 0, $pos) . $content . substr($response, $pos);
            } else {
                $response .= $content;
            }
        } 
        // If response is an object (Nemesis\Http\Response)
        elseif (is_object($response) && method_exists($response, 'getContent') && method_exists($response, 'setContent')) {
            $body = $response->getContent();
            $pos = strripos($body, '</body>');
            if ($pos !== false) {
                $body = substr($body, 0, $pos) . $content . substr($body, $pos);
            } else {
                $body .= $content;
            }
            $response->setContent($body);
        }
    }
}
