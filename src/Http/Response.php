<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — Typed Response | Updated: 2026-04-02

namespace Nemesis\Http;

/**
 * Immutable-ish HTTP Response value object.
 *
 * Fluent setters clone $this so the original is never mutated.
 * index.php calls $response->send() to flush headers + body to the client.
 *
 * Static factories cover the most common response types:
 *   Response::json($data)
 *   Response::view('home', ['user' => $u])
 *   Response::redirect('/login')
 *   Response::download('/storage/report.pdf', 'report.pdf')
 *   Response::stream(fn() => fpassthru($handle))
 */
class Response
{
    protected int     $statusCode    = 200;
    protected string  $content       = '';
    protected array   $headers       = [];
    protected ?string $redirectUrl   = null;
    protected ?string $downloadPath  = null;
    protected ?string $downloadName  = null;
    protected ?\Closure $streamCallback = null;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content    = $content;
        $this->statusCode = $status;
        $this->headers    = $headers;
    }

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    /**
     * JSON response — sets Content-Type: application/json.
     */
    public static function json(mixed $data, int $status = 200, array $headers = [], ?bool $pretty = null): static
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (self::shouldPrettyJson($pretty)) {
            $options |= JSON_PRETTY_PRINT;
        }

        $r = new static((string) json_encode($data, $options), $status, $headers);
        $r->headers['Content-Type'] = 'application/json';
        return $r;
    }

    /**
     * Plain-text response.
     */
    public static function text(string $content, int $status = 200): static
    {
        $r = new static($content, $status);
        $r->headers['Content-Type'] = 'text/plain; charset=UTF-8';
        return $r;
    }

    /**
     * HTTP redirect. Default 302 Found; use 301 for permanent.
     */
    public static function redirect(string $url, int $status = 302): static
    {
        $r = new static('', $status);
        $r->redirectUrl = $url;
        return $r;
    }

    /**
     * File download — Phase 4 deliverable.
     * Sets Content-Disposition: attachment so the browser prompts a save dialog.
     *
     * @param  string       $filePath  Absolute server path to the file.
     * @param  string|null  $filename  Name shown in the browser; defaults to basename.
     */
    public static function download(string $filePath, ?string $filename = null): static
    {
        $r = new static('', 200);
        $r->downloadPath = $filePath;
        $r->downloadName = $filename ?? basename($filePath);
        return $r;
    }

    /**
     * Streaming response — Phase 3 deliverable.
     * The $callback is called inside send() after headers are flushed.
     * Use it to stream large files or server-sent events without buffering.
     *
     * Example:
     *   Response::stream(function() use ($handle) {
     *       while (!feof($handle)) echo fread($handle, 8192);
     *   });
     */
    public static function stream(\Closure $callback, int $status = 200): static
    {
        $r = new static('', $status);
        $r->streamCallback = $callback;
        return $r;
    }

    /**
     * Rendered view response — Phase 3 deliverable.
     * Captures the View::render() output into the response body.
     */
    public static function view(string $view, array $data = [], int $status = 200): static
    {
        ob_start();
        \Nemesis\Core\View::render($view, $data);
        $content = (string) ob_get_clean();
        $r = new static($content, $status);
        $r->headers['Content-Type'] = 'text/html; charset=UTF-8';
        return $r;
    }

    // -------------------------------------------------------------------------
    // Fluent modifiers (clone so original stays unchanged)
    // -------------------------------------------------------------------------

    public function withStatus(int $code): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withContent(string $content): static
    {
        $clone = clone $this;
        $clone->content = $content;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getStatus(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    // -------------------------------------------------------------------------
    // Output — called by index.php (or tests) to flush the response
    // -------------------------------------------------------------------------

    public function send(): void
    {
        // --- Redirect ---
        if ($this->redirectUrl !== null) {
            http_response_code($this->statusCode);
            header('Location: ' . $this->redirectUrl);
            return;
        }

        // --- File download ---
        if ($this->downloadPath !== null) {
            if (!file_exists($this->downloadPath)) {
                http_response_code(404);
                echo 'File not found.';
                return;
            }

            http_response_code(200);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addslashes($this->downloadName ?? basename($this->downloadPath)) . '"');
            header('Content-Length: ' . filesize($this->downloadPath));
            header('Pragma: public');
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            readfile($this->downloadPath);
            return;
        }

        // --- Stream ---
        if ($this->streamCallback !== null) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            ($this->streamCallback)();
            return;
        }

        // --- Standard response ---
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->content;
    }

    protected static function shouldPrettyJson(?bool $override = null): bool
    {
        if ($override !== null) {
            return $override;
        }

        if (function_exists('config')) {
            return (bool) \config('api.pretty_json', \config('app.json_pretty', true));
        }

        if (function_exists('env')) {
            return (bool) \env('APP_JSON_PRETTY', true);
        }

        return true;
    }
}
