<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 4 — Health Check Endpoint | Created: 2026-04-02

namespace Nemesis\Http;

/**
 * HealthCheck
 *
 * Lightweight controller that checks the status of critical subsystems
 * and returns a JSON report. Designed for load-balancers, uptime monitors,
 * and k8s readiness/liveness probes.
 *
 * Route: GET /_health
 * Returns 200 when all checks pass, 503 when any check fails.
 */
class HealthCheck
{
    public function handle(Request $request): void
    {
        $checks  = $this->runChecks();
        $healthy = !in_array('fail', array_column($checks, 'status'), true);

        http_response_code($healthy ? 200 : 503);
        header('Content-Type: application/json');

        echo json_encode([
            'status'    => $healthy ? 'ok' : 'degraded',
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
            'checks'    => $checks,
        ], JSON_PRETTY_PRINT);
    }

    // -------------------------------------------------------------------------
    // Individual checks
    // -------------------------------------------------------------------------

    protected function runChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'disk'     => $this->checkDisk(),
            'php'      => $this->checkPhp(),
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            $pdo = \Nemesis\Core\Database::connect();
            $pdo->query('SELECT 1');
            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => 'Cannot connect: ' . $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            $driver = env('CACHE_DRIVER', 'file');

            if ($driver === 'redis') {
                $redis = new \Redis();
                $host  = env('REDIS_HOST', '127.0.0.1');
                $port  = (int) env('REDIS_PORT', 6379);
                $connected = @$redis->connect($host, $port, 1.0); // 1s timeout
                if (!$connected) {
                    return ['status' => 'fail', 'message' => "Redis unreachable at {$host}:{$port}"];
                }
                return ['status' => 'ok', 'message' => 'Redis connected'];
            }

            // File cache — just check the directory is writable
            $cacheDir = base_path('storage/framework/cache');
            if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
                return ['status' => 'fail', 'message' => 'Cache directory not writable'];
            }

            return ['status' => 'ok', 'driver' => $driver];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkDisk(): array
    {
        try {
            $storagePath = base_path('storage');
            $free  = disk_free_space($storagePath);
            $total = disk_total_space($storagePath);

            if ($free === false || $total === false) {
                return ['status' => 'fail', 'message' => 'Unable to read disk stats'];
            }

            $freePercent = round(($free / $total) * 100, 1);

            // Warn if less than 10% free
            $status = $freePercent >= 10 ? 'ok' : 'fail';

            return [
                'status'       => $status,
                'free_percent' => $freePercent,
                'free_mb'      => round($free / 1048576, 1),
                'total_mb'     => round($total / 1048576, 1),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkPhp(): array
    {
        return [
            'status'    => 'ok',
            'version'   => PHP_VERSION,
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
        ];
    }
}
