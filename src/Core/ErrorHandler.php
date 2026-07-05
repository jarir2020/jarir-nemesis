<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Rewritten: 2026-04-02
// Phase 5 — wired to exception hierarchy + error views
// R1 fix: removed dead import JarirAhmed\HTTPResponse\HTTPResponse

namespace Nemesis\Core;

use Nemesis\Exceptions\HttpException;
use Nemesis\Exceptions\Concerns\RenderableException;
use Nemesis\Exceptions\Concerns\ReportableException;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class ErrorHandler
{
    /**
     * Handle any uncaught Throwable.
     * Detects: CLI / JSON request / HTML request / debug mode.
     */
    public static function handleException(\Throwable $exception): never
    {
        // Always log
        Log::error($exception->getMessage(), [
            'exception' => get_class($exception),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTraceAsString(),
        ]);

        // Let exception report itself (e.g. send to Sentry)
        if ($exception instanceof ReportableException) {
            try { $exception->report(); } catch (\Throwable) {}
        }

        // CLI output
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\n[" . get_class($exception) . "] " . $exception->getMessage() . "\n");
            fwrite(STDERR, "  at " . $exception->getFile() . ':' . $exception->getLine() . "\n");
            fwrite(STDERR, $exception->getTraceAsString() . "\n");
            exit(1);
        }

        // Resolve HTTP status code
        $statusCode = match (true) {
            $exception instanceof HttpException    => $exception->getStatusCode(),
            $exception instanceof ValidationException => 422,
            default                                => 500,
        };

        http_response_code($statusCode);

        $request = PHP_SAPI === 'cli' ? null : new Request();

        // If exception can render itself, let it handle the response.
        if ($exception instanceof RenderableException) {
            try {
                $response = $request instanceof Request
                    ? $exception->render($request)
                    : null;

                if ($response instanceof Response) {
                    $response->send();
                    exit;
                }
            } catch (\Throwable $renderException) {
                Log::error('Renderable exception failed to render: ' . $renderException->getMessage(), [
                    'exception' => get_class($renderException),
                ]);
            }
        }

        // JSON requests (API / AJAX)
        if (self::wantsJson()) {
            self::renderJson($exception, $statusCode);
        }

        // HTML requests
        if (self::isDebug()) {
            self::renderDebugPage($exception, $statusCode);
        }

        self::renderErrorView($exception, $statusCode);
    }

    /**
     * Convert PHP errors into ErrorExceptions so they're caught uniformly.
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false; // Error suppressed with @
        }

        Log::error("PHP Error [{$errno}]: {$errstr}", [
            'file' => $errfile,
            'line' => $errline,
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\nPHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}\n");
            exit(1);
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    // -------------------------------------------------------------------------
    // Rendering helpers
    // -------------------------------------------------------------------------

    private static function renderJson(\Throwable $e, int $statusCode): never
    {
        header('Content-Type: application/json');

        $payload = [
            'error'   => true,
            'status'  => $statusCode,
            'message' => $e->getMessage(),
        ];

        if ($e instanceof ValidationException) {
            $payload['errors'] = $e->getErrors();
        }

        if (self::isDebug()) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => explode("\n", $e->getTraceAsString()),
            ];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private static function renderErrorView(\Throwable $e, int $statusCode): never
    {
        $base    = __DIR__ . '/../../views/errors/';
        $specific = $base . $statusCode . '.php';
        $generic  = $base . 'generic.php';
        $viewFile = file_exists($specific) ? $specific : $generic;

        $message = $e->getMessage();
        $errors  = ($e instanceof ValidationException) ? $e->getErrors() : [];
        $code    = $statusCode;

        extract(compact('message', 'errors', 'code'), EXTR_SKIP);
        include $viewFile;
        exit;
    }

    private static function renderDebugPage(\Throwable $e, int $statusCode): never
    {
        header('Content-Type: text/html; charset=UTF-8');

        $class   = htmlspecialchars(get_class($e), ENT_QUOTES);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES);
        $file    = htmlspecialchars($e->getFile(), ENT_QUOTES);
        $line    = $e->getLine();
        $trace   = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES);
        $prev    = $e->getPrevious()
            ? htmlspecialchars(get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage(), ENT_QUOTES)
            : null;

        // Read the source file around the error line
        $source = self::readSourceContext($e->getFile(), $line);

        $phpVersion = PHP_VERSION;
        $appEnv     = htmlspecialchars((string)(getenv('APP_ENV') ?: 'local'), ENT_QUOTES);
        $css = '*{margin:0;padding:0;box-sizing:border-box}'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",monospace;background:#1e1e2e;color:#cdd6f4;font-size:14px}'
            . 'header{background:#313244;padding:20px 32px;border-bottom:2px solid #f38ba8}'
            . 'header .status{font-size:13px;color:#f38ba8;font-weight:600;letter-spacing:.05em;text-transform:uppercase}'
            . 'header h1{font-size:20px;font-weight:700;margin-top:4px;color:#cdd6f4}'
            . 'header .msg{color:#a6adc8;margin-top:6px;font-size:14px;font-family:monospace}'
            . '.meta{background:#181825;padding:10px 32px;font-size:12px;color:#6c7086;border-bottom:1px solid #313244}'
            . '.meta span{color:#cba6f7;margin-right:16px}'
            . 'section{padding:24px 32px}'
            . 'section h2{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#89b4fa;margin-bottom:12px}'
            . 'pre{background:#181825;border:1px solid #313244;border-radius:6px;padding:16px;overflow-x:auto;font-size:13px;line-height:1.6;color:#cdd6f4}'
            . '.highlight{background:#3d1c2c;border-left:3px solid #f38ba8;padding-left:8px;color:#f38ba8}'
            . '.lineno{color:#585b70;user-select:none;min-width:40px;display:inline-block;text-align:right;margin-right:12px}'
            . '.prev{background:#2a1e38;border:1px solid #cba6f7;border-radius:6px;padding:12px 16px;font-size:13px;color:#cba6f7;margin-top:8px}'
            . '.badge{display:inline-block;background:#f38ba8;color:#1e1e2e;font-size:11px;font-weight:700;padding:2px 8px;border-radius:3px;margin-right:8px}';

        echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">"
            . "<title>{$statusCode} — {$class}</title>"
            . "<style>{$css}</style></head><body>"
            . "<header>"
            . "<div class=\"status\"><span class=\"badge\">{$statusCode}</span>Nemesis Debug &mdash; {$class}</div>"
            . "<h1>{$message}</h1>"
            . "<div class=\"msg\">{$file} &nbsp;&middot;&nbsp; line {$line}</div>"
            . "</header>"
            . "<div class=\"meta\">PHP <span>{$phpVersion}</span> Nemesis <span>4.0.0</span> APP_ENV <span>{$appEnv}</span></div>";

        if ($source) {
            echo '<section><h2>Source</h2><pre>';
            foreach ($source as $num => $srcLine) {
                $hl = ($num === $line) ? ' class="highlight"' : '';
                $ln = htmlspecialchars($srcLine, ENT_QUOTES);
                echo "<span{$hl}><span class=\"lineno\">{$num}</span>{$ln}</span>\n";
            }
            echo '</pre></section>';
        }

        if ($prev) {
            echo "<section><h2>Caused By</h2><div class=\"prev\">{$prev}</div></section>";
        }

        echo "<section><h2>Stack Trace</h2><pre>{$trace}</pre></section></body></html>";

        exit;
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    private static function wantsJson(): bool
    {
        $accept      = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $xhrHeader   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || $xhrHeader === 'xmlhttprequest';
    }

    private static function isDebug(): bool
    {
        return filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Read ~7 lines of source context around the error line.
     * Returns [lineNumber => lineContent] or empty array on failure.
     */
    private static function readSourceContext(string $file, int $errorLine): array
    {
        if (!is_readable($file)) {
            return [];
        }

        $lines  = file($file, FILE_IGNORE_NEW_LINES);
        $start  = max(0, $errorLine - 5);
        $end    = min(count($lines) - 1, $errorLine + 4);
        $result = [];

        for ($i = $start; $i <= $end; $i++) {
            $result[$i + 1] = $lines[$i];
        }

        return $result;
    }
}
