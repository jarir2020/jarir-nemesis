<?php
declare(strict_types=1);

namespace Nemesis\Console;

use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Conservative vendor compression helper.
 *
 * The compressor only targets PHP class-like files that are not referenced by
 * the project source tree and are not protected by allowlist rules.
 */
class VendorCompressor
{
    private string $root;
    private string $vendorDir;
    private string $backupDir;

    public function __construct(?string $root = null)
    {
        $resolved = $root ?: (function_exists('base_path') ? base_path() : getcwd());
        $real = realpath($resolved);

        if ($real === false) {
            throw new RuntimeException("Project root not found: {$resolved}");
        }

        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
        $this->vendorDir = $this->root . DIRECTORY_SEPARATOR . 'vendor';
        $this->backupDir = $this->root . DIRECTORY_SEPARATOR . '.nemesis' . DIRECTORY_SEPARATOR . 'vendor-compress';
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function compress(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $timestamp = gmdate('Y-m-d\THis\Z');
        $archivePath = $this->resolveBackupPath($options['archive'] ?? null, $timestamp, '.zip');
        $reportPath = $this->resolveBackupPath($options['report'] ?? null, $timestamp, '.json');
        $keep = $this->normalizeList($options['keep'] ?? []);
        $exclude = $this->normalizeList($options['exclude'] ?? []);

        $scan = $this->scan($keep, $exclude);

        $report = [
            'command' => 'vendor:compress',
            'timestamp' => gmdate('c'),
            'mode' => $dryRun ? 'dry-run' : 'compress',
            'scope' => [
                'root' => $this->root,
                'vendor' => $this->vendorDir,
                'source_files_scanned' => $scan['scope']['source_files_scanned'],
                'vendor_files_scanned' => $scan['scope']['vendor_files_scanned'],
            ],
            'flags' => [
                'dry_run' => $dryRun,
                'json' => (bool) ($options['json'] ?? false),
                'report' => $reportPath,
                'archive' => $archivePath,
                'keep' => $keep,
                'exclude' => $exclude,
            ],
            'summary' => [
                'preserved' => count($scan['preserved']),
                'candidates' => count($scan['candidates']),
                'skipped' => count($scan['skipped']),
                'warnings' => count($scan['warnings']),
            ],
            'preserved' => $scan['preserved'],
            'candidates' => $scan['candidates'],
            'skipped' => $scan['skipped'],
            'warnings' => $scan['warnings'],
            'restore' => [
                'archive_path' => $archivePath,
                'manifest_path' => $reportPath,
                'restore_command' => 'php nemesis vendor:compress --restore=' . $reportPath,
            ],
        ];

        if (!$dryRun && !empty($scan['candidates'])) {
            $this->createArchiveAndRemoveFiles($scan['candidates'], $archivePath, $report);
        }

        $this->writeJson($reportPath, $report);

        return $report;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function restore(string $source, array $options = []): array
    {
        $sourcePath = $this->resolvePath($source);
        if (!file_exists($sourcePath)) {
            throw new RuntimeException("Restore source not found: {$source}");
        }

        $report = [
            'command' => 'vendor:compress',
            'timestamp' => gmdate('c'),
            'mode' => 'restore',
            'scope' => [
                'root' => $this->root,
                'vendor' => $this->vendorDir,
            ],
            'flags' => [
                'restore' => $sourcePath,
            ],
            'summary' => [
                'restored' => 0,
                'warnings' => 0,
            ],
            'preserved' => [],
            'candidates' => [],
            'skipped' => [],
            'warnings' => [],
            'restore' => [
                'archive_path' => $sourcePath,
                'manifest_path' => $sourcePath,
                'restore_command' => 'php nemesis vendor:compress --restore=' . $sourcePath,
            ],
        ];

        if (is_dir($sourcePath)) {
            throw new RuntimeException('Restore expects an archive or manifest file, not a directory.');
        }

        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'json') {
            $manifest = $this->loadManifest($sourcePath);
            $archivePath = $manifest['restore']['archive_path'] ?? null;
            if (is_string($archivePath) && file_exists($archivePath)) {
                $this->restoreFromArchive($archivePath);
                $report['restore']['archive_path'] = $archivePath;
            } else {
                $this->restoreFromManifest($manifest);
            }

            $report['summary']['restored'] = count($manifest['candidates'] ?? []);
            $report['restore']['manifest_path'] = $sourcePath;
            return $report;
        }

        $this->restoreFromArchive($sourcePath);
        $manifestPath = preg_replace('/\.zip$/i', '.json', $sourcePath) ?: $sourcePath . '.json';
        if (is_string($manifestPath) && file_exists($manifestPath)) {
            $report['restore']['manifest_path'] = $manifestPath;
        }

        $report['summary']['restored'] = $this->countZipRestoredEntries($sourcePath);
        return $report;
    }

    /**
     * @param list<string> $keep
     * @param list<string> $exclude
     * @return array{
     *     scope: array{source_files_scanned:int,vendor_files_scanned:int},
     *     preserved: list<array<string, mixed>>,
     *     candidates: list<array<string, mixed>>,
     *     skipped: list<array<string, mixed>>,
     *     warnings: list<string>
     * }
     */
    public function scan(array $keep = [], array $exclude = []): array
    {
        if (!is_dir($this->vendorDir)) {
            return [
                'scope' => ['source_files_scanned' => 0, 'vendor_files_scanned' => 0],
                'preserved' => [],
                'candidates' => [],
                'skipped' => [],
                'warnings' => ['Vendor directory not found.'],
            ];
        }

        $references = $this->collectProjectReferences();
        $reflectionHeavyPackages = $this->identifyReflectionHeavyPackages();
        $preserveMap = $this->buildPreserveMap($keep, $reflectionHeavyPackages);

        $preserved = [];
        $candidates = [];
        $skipped = [];
        $warnings = $references['warnings'];
        $vendorFilesScanned = 0;

        foreach ($this->iterateVendorFiles() as $file) {
            $vendorFilesScanned++;
            $relative = $this->relativePath($file);
            $package = $this->packageNameFromPath($relative);

            if ($this->matchesAny($relative, $exclude)) {
                $skipped[] = [
                    'path' => $relative,
                    'package' => $package,
                    'reason' => 'excluded',
                ];
                continue;
            }

            if (!str_ends_with($file, '.php')) {
                $preserved[] = [
                    'path' => $relative,
                    'package' => $package,
                    'reason' => 'non-php',
                ];
                continue;
            }

            $classInfo = $this->readClassInfo($file);
            if ($classInfo === null) {
                $preserved[] = [
                    'path' => $relative,
                    'package' => $package,
                    'reason' => 'no-class-declaration',
                ];
                continue;
            }

            if (isset($preserveMap[$file])) {
                $preserved[] = [
                    'path' => $relative,
                    'package' => $package,
                    'reason' => $preserveMap[$file],
                ];
                continue;
            }

            if ($this->isReferenced($classInfo, $references)) {
                $preserved[] = [
                    'path' => $relative,
                    'package' => $package,
                    'reason' => 'referenced-by-project',
                ];
                continue;
            }

            $candidates[] = [
                'path' => $relative,
                'package' => $package,
                'class' => $classInfo['fqcn'],
                'short_name' => $classInfo['short_name'],
                'reason' => 'unreferenced-class',
                'size' => filesize($file) ?: 0,
                'sha1' => sha1_file($file) ?: null,
                'content_b64' => base64_encode((string) file_get_contents($file)),
            ];
        }

        return [
            'scope' => [
                'source_files_scanned' => $references['source_files_scanned'],
                'vendor_files_scanned' => $vendorFilesScanned,
            ],
            'preserved' => $preserved,
            'candidates' => $candidates,
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @param array<string, mixed> $report
     */
    private function createArchiveAndRemoveFiles(array $candidates, string $archivePath, array &$report): void
    {
        $this->ensureBackupDir();

        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new RuntimeException("Unable to create archive: {$archivePath}");
        }

        $manifest = [
            'command' => 'vendor:compress',
            'timestamp' => gmdate('c'),
            'mode' => 'compress',
            'restore' => [
                'archive_path' => $archivePath,
                'manifest_path' => preg_replace('/\.zip$/i', '.json', $archivePath),
                'restore_command' => 'php nemesis vendor:compress --restore=' . preg_replace('/\.zip$/i', '.json', $archivePath),
            ],
            'candidates' => [],
        ];

        foreach ($candidates as $item) {
            $absolute = $this->root . DIRECTORY_SEPARATOR . $item['path'];
            if (!file_exists($absolute)) {
                continue;
            }

            $zip->addFile($absolute, $item['path']);
            $manifest['candidates'][] = $item;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->close();

        foreach ($candidates as $item) {
            $absolute = $this->root . DIRECTORY_SEPARATOR . $item['path'];
            if (file_exists($absolute) && is_file($absolute)) {
                unlink($absolute);
            }
        }

        $report['restore']['archive_path'] = $archivePath;
        $report['restore']['manifest_path'] = preg_replace('/\.zip$/i', '.json', $archivePath);
        $report['summary']['removed'] = count($candidates);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function restoreFromManifest(array $manifest): void
    {
        foreach (($manifest['candidates'] ?? []) as $item) {
            if (!is_array($item) || empty($item['path'])) {
                continue;
            }

            $absolute = $this->root . DIRECTORY_SEPARATOR . $item['path'];
            $dir = dirname($absolute);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = '';
            if (isset($item['content_b64']) && is_string($item['content_b64'])) {
                $decoded = base64_decode($item['content_b64'], true);
                if ($decoded !== false) {
                    $content = $decoded;
                }
            }

            file_put_contents($absolute, $content);
        }
    }

    private function restoreFromArchive(string $archivePath): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath);
        if ($result !== true) {
            throw new RuntimeException("Unable to open archive: {$archivePath}");
        }

        if (!$zip->extractTo($this->root)) {
            $zip->close();
            throw new RuntimeException("Unable to extract archive: {$archivePath}");
        }

        $zip->close();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read manifest: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid manifest JSON: {$path}");
        }

        return $data;
    }

    /**
     * @return array{fqcn:?string, short_name:string, namespace:?string, class_name:string}
     */
    private function readClassInfo(string $file): ?array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        if (!preg_match('/^namespace\s+([^;]+);/m', $content, $nsMatch)) {
            return null;
        }

        if (!preg_match('/^(?:final\s+|abstract\s+)?(class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $classMatch)) {
            return null;
        }

        $namespace = trim($nsMatch[1]);
        $className = $classMatch[2];
        $fqcn = trim($namespace . '\\' . $className, '\\');

        return [
            'fqcn' => $fqcn !== '' ? $fqcn : null,
            'short_name' => $className,
            'namespace' => $namespace !== '' ? $namespace : null,
            'class_name' => $className,
        ];
    }

    /**
     * @return array{tokens: array<string, true>, source_files_scanned: int}
     */
    private function collectProjectReferences(): array
    {
        $tokens = [];
        $sourceFilesScanned = 0;
        $warnings = [];
        $dynamicMarkersFound = false;

        foreach ($this->iterateProjectFiles() as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $sourceFilesScanned++;
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            foreach ($this->extractTokens($content) as $token) {
                $tokens[$token] = true;
            }

            if ($this->containsDynamicResolutionMarkers($content)) {
                $dynamicMarkersFound = true;
            }
        }

        if ($dynamicMarkersFound) {
            $warnings[] = 'Dynamic resolution markers were detected in the project tree; reflection-heavy vendor packages will be preserved.';
        }

        return [
            'tokens' => $tokens,
            'source_files_scanned' => $sourceFilesScanned,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractTokens(string $content): array
    {
        $tokens = [];
        $tokens = array_merge(
            $tokens,
            $this->extractUseStatementTokens($content),
            $this->extractTypeAndCallTokens($content),
            $this->extractQuotedClassTokens($content)
        );

        return array_values(array_unique($tokens));
    }

    /**
     * @return list<string>
     */
    private function extractUseStatementTokens(string $content): array
    {
        $tokens = [];

        if (!preg_match_all('/^\s*use\s+([^;]+);/m', $content, $matches)) {
            return $tokens;
        }

        foreach ($matches[1] as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            foreach ($this->expandUseStatement($statement) as $candidate) {
                $tokens[] = $candidate;
            }
        }

        return $tokens;
    }

    /**
     * Expand a `use` statement into concrete class-like tokens, including aliases.
     *
     * @return list<string>
     */
    private function expandUseStatement(string $statement): array
    {
        $tokens = [];

        if (str_contains($statement, '{') && str_contains($statement, '}')) {
            $prefix = trim((string) preg_replace('/\{.*$/', '', $statement));
            $inner = preg_replace('/^.*\{/', '', $statement);
            $inner = preg_replace('/\}.*/', '', $inner);

            if ($prefix !== '') {
                $prefix = rtrim($prefix, '\\');
            }

            foreach (preg_split('/\s*,\s*/', trim((string) $inner)) ?: [] as $segment) {
                if ($segment === '') {
                    continue;
                }

                $tokens = array_merge($tokens, $this->expandUseSegment($prefix, $segment));
            }

            return $tokens;
        }

        return $this->expandUseSegment('', $statement);
    }

    /**
     * @return list<string>
     */
    private function expandUseSegment(string $prefix, string $segment): array
    {
        $tokens = [];
        $segment = trim($segment);
        if ($segment === '') {
            return $tokens;
        }

        $alias = null;
        if (preg_match('/\s+as\s+/i', $segment) === 1) {
            [$segment, $alias] = preg_split('/\s+as\s+/i', $segment, 2);
            $segment = trim((string) $segment);
            $alias = trim((string) $alias);
        }

        $fqcn = $prefix !== ''
            ? trim($prefix . '\\' . ltrim($segment, '\\'), '\\')
            : trim($segment, '\\');

        if ($fqcn !== '') {
            $tokens[] = $fqcn;
            $tokens[] = basename(str_replace('\\', '/', $fqcn));
        }

        if ($alias !== null && $alias !== '') {
            $tokens[] = $alias;
        }

        return $tokens;
    }

    /**
     * Catch common class references in type declarations and object usage.
     *
     * @return list<string>
     */
    private function extractTypeAndCallTokens(string $content): array
    {
        $tokens = [];
        $patterns = [
            '/\b(?:new|extends|implements|instanceof|catch)\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/m',
            '/\b([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)::class\b/m',
            '/\b(?:public|protected|private)?\s*(?:static\s+)?(?:readonly\s+)?(?:\??([A-Za-z_\\\\][A-Za-z0-9_\\\\]*))\s+\$[A-Za-z_][A-Za-z0-9_]*/m',
            '/\bfunction\s+[A-Za-z_][A-Za-z0-9_]*\s*\(([^)]*)\)\s*(?::\s*([A-Za-z_\\\\][A-Za-z0-9_\\\\]*))?/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    foreach (array_slice($match, 1) as $candidate) {
                        if (!is_string($candidate) || $candidate === '') {
                            continue;
                        }

                        if (str_contains($candidate, '$')) {
                            continue;
                        }

                        $clean = trim($candidate, "\\ \t\n\r\0\x0B?&|");
                        if ($clean === '' || in_array($clean, ['public', 'protected', 'private', 'static', 'readonly', 'function'], true)) {
                            continue;
                        }

                        $tokens[] = $clean;
                        if (str_contains($clean, '\\')) {
                            $tokens[] = basename(str_replace('\\', '/', $clean));
                        }
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * Capture string-based class references used by common container helpers.
     *
     * @return list<string>
     */
    private function extractQuotedClassTokens(string $content): array
    {
        $tokens = [];
        $patterns = [
            '/\b(?:app|make|resolve|singleton|bind|instance)\(\s*[\'"]([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]/m',
            '/\bclass_alias\(\s*[\'"]([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]\s*,\s*[\'"]([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]\s*\)/m',
            '/\b(?:ReflectionClass|ReflectionMethod|ReflectionFunction|ReflectionProperty|ReflectionObject)\(\s*[\'"]([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    foreach (array_slice($match, 1) as $candidate) {
                        if (!is_string($candidate) || $candidate === '') {
                            continue;
                        }

                        $tokens[] = $candidate;
                        if (str_contains($candidate, '\\')) {
                            $tokens[] = basename(str_replace('\\', '/', $candidate));
                        }
                    }
                }
            }
        }

        return $tokens;
    }

    private function containsDynamicResolutionMarkers(string $content): bool
    {
        $patterns = [
            '/\bReflectionClass\b/',
            '/\bReflectionMethod\b/',
            '/\bReflectionFunction\b/',
            '/\bReflectionProperty\b/',
            '/\bReflectionObject\b/',
            '/\bclass_exists\s*\(/',
            '/\binterface_exists\s*\(/',
            '/\btrait_exists\s*\(/',
            '/\benum_exists\s*\(/',
            '/\bmethod_exists\s*\(/',
            '/\bproperty_exists\s*\(/',
            '/\bis_a\s*\(/',
            '/\bis_subclass_of\s*\(/',
            '/\bcall_user_func(?:_array)?\s*\(/',
            '/\bforward_static_call(?:_array)?\s*\(/',
            '/\bresolve\s*\(\s*[\'"]?[A-Za-z_\\\\][A-Za-z0-9_\\\\]*[\'"]?\s*\)/',
            '/\bapp\s*\(\s*[\'"]?[A-Za-z_\\\\][A-Za-z0-9_\\\\]*[\'"]?\s*\)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{fqcn:?string, short_name:string} $classInfo
     * @param array{tokens: array<string, true>} $references
     */
    private function isReferenced(array $classInfo, array $references): bool
    {
        $fqcn = $classInfo['fqcn'] ?? null;
        $short = $classInfo['short_name'] ?? '';

        return ($fqcn !== null && isset($references['tokens'][$fqcn]))
            || ($short !== '' && isset($references['tokens'][$short]));
    }

    /**
     * @return iterable<string>
     */
    private function iterateProjectFiles(): iterable
    {
        $skip = [
            DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.nemesis' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getRealPath();
            if ($path === false) {
                continue;
            }

            $normalized = str_replace('/', DIRECTORY_SEPARATOR, $path);
            $skipFile = false;
            foreach ($skip as $needle) {
                if (str_contains($normalized, $needle)) {
                    $skipFile = true;
                    break;
                }
            }

            if ($skipFile) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * @return iterable<string>
     */
    private function iterateVendorFiles(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->vendorDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getRealPath();
            if ($path === false) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * @param list<string> $keep
     * @return array<string, string>
     */
    /**
     * @param array<string, true> $reflectionHeavyPackages
     */
    private function buildPreserveMap(array $keep, array $reflectionHeavyPackages = []): array
    {
        $preserve = [];

        foreach ($this->iterateVendorFiles() as $file) {
            $relative = $this->relativePath($file);

            if ($this->isAlwaysPreserved($relative)) {
                $preserve[$file] = 'bootstrap-or-entrypoint';
                continue;
            }

            if ($this->matchesAny($relative, $keep)) {
                $preserve[$file] = 'keep-flag';
                continue;
            }

            $packageRoot = $this->packageRootFromFile($file);
            if ($packageRoot === null) {
                continue;
            }

            if (isset($reflectionHeavyPackages[$packageRoot])) {
                $preserve[$file] = 'reflection-heavy-package';
                continue;
            }

            $composerJson = $packageRoot . DIRECTORY_SEPARATOR . 'composer.json';
            if (!file_exists($composerJson)) {
                continue;
            }

            $meta = json_decode((string) file_get_contents($composerJson), true);
            if (!is_array($meta)) {
                continue;
            }

            $preservedFiles = $this->composerProtectedFiles($packageRoot, $meta);
            foreach ($preservedFiles as $path => $reason) {
                $preserve[$path] = $reason;
            }
        }

        return $preserve;
    }

    /**
     * @return array<string, true>
     */
    private function identifyReflectionHeavyPackages(): array
    {
        $packages = [];

        foreach (glob($this->vendorDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $packageRoot) {
            $phpFiles = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($packageRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($phpFiles as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $path = $fileInfo->getRealPath();
                if ($path === false || !str_ends_with($path, '.php')) {
                    continue;
                }

                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                if ($this->containsDynamicResolutionMarkers($content)) {
                    $packages[rtrim($packageRoot, DIRECTORY_SEPARATOR)] = true;
                    break;
                }
            }
        }

        return $packages;
    }

    /**
     * @param array<string, mixed> $composer
     * @return array<string, string> absolute path => reason
     */
    private function composerProtectedFiles(string $packageRoot, array $composer): array
    {
        $protected = [];
        $files = [];

        if (isset($composer['bin']) && is_array($composer['bin'])) {
            foreach ($composer['bin'] as $bin) {
                if (is_string($bin) && $bin !== '') {
                    $files[] = $bin;
                }
            }
        }

        if (isset($composer['files']) && is_array($composer['files'])) {
            foreach ($composer['files'] as $file) {
                if (is_string($file) && $file !== '') {
                    $files[] = $file;
                }
            }
        }

        if (isset($composer['autoload']['files']) && is_array($composer['autoload']['files'])) {
            foreach ($composer['autoload']['files'] as $file) {
                if (is_string($file) && $file !== '') {
                    $files[] = $file;
                }
            }
        }

        foreach (array_unique($files) as $file) {
            $absolute = $packageRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), DIRECTORY_SEPARATOR);
            if (file_exists($absolute)) {
                $protected[$absolute] = 'composer-protected';
            }
        }

        return $protected;
    }

    private function isAlwaysPreserved(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        return $normalized === 'vendor/autoload.php'
            || $normalized === 'vendor/composer'
            || str_starts_with($normalized, 'vendor/composer/')
            || str_starts_with($normalized, 'vendor/bin/')
            || str_ends_with($normalized, '/composer.json')
            || str_ends_with($normalized, '/composer.lock');
    }

    private function packageRootFromFile(string $file): ?string
    {
        $relative = $this->relativePath($file);
        $parts = explode(DIRECTORY_SEPARATOR, $relative);
        if (count($parts) < 3 || $parts[0] !== 'vendor') {
            return null;
        }

        return $this->vendorDir . DIRECTORY_SEPARATOR . $parts[1] . DIRECTORY_SEPARATOR . $parts[2];
    }

    private function packageNameFromPath(string $relativePath): string
    {
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        if (count($parts) >= 3 && $parts[0] === 'vendor') {
            return $parts[1] . '/' . $parts[2];
        }

        return 'unknown';
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if ($value === null || $value === false || $value === true) {
            return [];
        }

        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        if (!is_array($value)) {
            return [(string) $value];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                foreach (array_filter(array_map('trim', explode(',', $item))) as $part) {
                    $items[] = $part;
                }
            } elseif ($item !== null && $item !== false) {
                $items[] = (string) $item;
            }
        }

        return array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
    }

    private function matchesAny(string $path, array $patterns): bool
    {
        if (empty($patterns)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            $needle = str_replace('\\', '/', trim($pattern));
            if ($needle === '') {
                continue;
            }

            if ($normalized === $needle || str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $absolute): string
    {
        $root = rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $root)) {
            return substr($absolute, strlen($root));
        }

        return ltrim($absolute, DIRECTORY_SEPARATOR);
    }

    private function resolvePath(mixed $path): string
    {
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Invalid path given.');
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function resolveBackupPath(mixed $value, string $timestamp, string $extension): string
    {
        $this->ensureBackupDir();

        if (is_string($value) && $value !== '') {
            return $this->resolvePath($value);
        }

        $filename = $timestamp . $extension;
        return $this->backupDir . DIRECTORY_SEPARATOR . $filename;
    }

    private function ensureBackupDir(): void
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    private function writeJson(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    private function countZipRestoredEntries(string $archivePath): int
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            return 0;
        }

        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && $name !== 'manifest.json') {
                $count++;
            }
        }
        $zip->close();

        return $count;
    }
}
