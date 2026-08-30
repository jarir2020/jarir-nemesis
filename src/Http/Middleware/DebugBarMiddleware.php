<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Moved from src/Middleware: 2026-04-02
// Renamed from DebugBar.php → DebugBarMiddleware.php to avoid clash with plugin class name.

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Core\Database;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class DebugBarMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        Database::enableQueryLog();

        $response = $next($request);

        $content = $response->getContent();
        if (str_contains($content, '<html')) {
            $time    = round((microtime(true) - $start) * 1000, 2);
            $memory  = round(memory_get_peak_usage() / 1024 / 1024, 2);
            $queries = Database::getQueryLog();
            $bar     = $this->renderBar($time, $memory, $queries);

            if (str_contains($content, '</body>')) {
                return $response->withContent(
                    str_replace('</body>', $bar . '</body>', $content)
                );
            }
        }

        return $response;
    }

    protected function renderBar(float $time, float $memory, array $queries): string
    {
        $queryCount = count($queries);
        $barStyle   = "position:fixed;bottom:0;left:0;right:0;height:35px;background:#1a202c;color:#fff;font-family:sans-serif;font-size:12px;display:flex;align-items:center;padding:0 15px;border-top:2px solid #3182ce;z-index:9999;opacity:0.95;";
        $itemStyle  = "margin-right:20px;display:flex;align-items:center;";

        return "
        <div id='nemesis-debug-bar' style='{$barStyle}'>
            <div style='{$itemStyle}'><b style='color:#63b3ed;margin-right:5px;'>Nemesis</b> Debugger</div>
            <div style='{$itemStyle}'>&#9201; {$time} ms</div>
            <div style='{$itemStyle}'>&#128190; {$memory} MB</div>
            <div style='{$itemStyle}'>&#128268; {$queryCount} Queries</div>
        </div>";
    }
}
