<?php

namespace Nemesis\Middleware;

use Nemesis\Http\Request;
use Nemesis\Core\Database;

class DebugBar {
    public function handle(Request $request, $next) {
        $start = microtime(true);
        
        // Enable SQL logging for this request
        Database::enableQueryLog();
        
        $response = $next($request);
        
        // Only inject if it's a browser request (HTML)
        if ($this->isHtml($response)) {
            $time = round((microtime(true) - $start) * 1000, 2);
            $memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
            $queries = Database::getQueryLog();
            
            $html = $this->renderBar($time, $memory, $queries);
            $content = (string)$response;
            
            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', $html . '</body>', $content);
                return $content;
            }
        }
        
        return $response;
    }

    protected function isHtml($response) {
        if (!is_string($response)) return false;
        return str_contains($response, '<html');
    }

    protected function renderBar($time, $memory, $queries) {
        $queryCount = count($queries);
        $barStyle = "position:fixed;bottom:0;left:0;right:0;height:35px;background:#1a202c;color:#fff;font-family:sans-serif;font-size:12px;display:flex;align-items:center;padding:0 15px;border-top:2px solid #3182ce;z-index:9999;opacity:0.95;";
        $itemStyle = "margin-right:20px;display:flex;align-items:center;";
        
        return "
        <div id='nemesis-debug-bar' style='$barStyle'>
            <div style='$itemStyle'><b style='color:#63b3ed;margin-right:5px;'>Nemesis</b> Debugger</div>
            <div style='$itemStyle'>⏱️ $time ms</div>
            <div style='$itemStyle'>💾 $memory MB</div>
            <div style='$itemStyle'>🔌 $queryCount Queries</div>
        </div>";
    }
}
