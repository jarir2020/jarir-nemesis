<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy: TenantResolver | 2026-04-06

namespace Nemesis\Tenancy;

/**
 * TenantResolver — identifies the current tenant from an incoming request.
 *
 * Identification strategies (checked in configured priority order):
 *
 *   1. subdomain  — tenant.myapp.com → slug = 'tenant'
 *   2. header     — X-Tenant-ID: tenant → slug = 'tenant'
 *   3. path       — myapp.com/tenant/dashboard → slug = 'tenant'
 *   4. query      — myapp.com/dashboard?tenant=tenant → slug = 'tenant'
 *
 * Config from config/tenancy.php or environment:
 *   TENANT_IDENTIFICATION=subdomain,header,path
 *   TENANT_SUBDOMAIN_BASE=myapp.com
 *   TENANT_HEADER=X-Tenant-ID
 *   TENANT_PATH_SEGMENT=1          (1 = first segment of URI path)
 */
class TenantResolver
{
    /** @var list<string> */
    private array  $drivers;
    private string $subdomainBase;
    private string $headerKey;
    private int    $pathSegment;

    /** @var list<string> Slugs that are NOT tenants (reserved domains/routes). */
    private array  $excluded;

    public function __construct(array $config = [])
    {
        $this->drivers       = $config['identification'] ?? ['subdomain', 'header', 'path'];
        $this->subdomainBase = $config['subdomain_base'] ?? (string) (getenv('TENANT_SUBDOMAIN_BASE') ?: 'example.com');
        $this->headerKey     = $config['header_key']     ?? (string) (getenv('TENANT_HEADER') ?: 'X-Tenant-ID');
        $this->pathSegment   = (int) ($config['path_segment'] ?? (getenv('TENANT_PATH_SEGMENT') ?: 1));
        $this->excluded      = $config['excluded']        ?? ['www', 'api', 'admin', 'app', 'mail'];
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Resolve the tenant slug from the given request context.
     *
     * @param string $host    HTTP_HOST  (e.g. 'acme.myapp.com')
     * @param string $path    REQUEST_URI path segment (e.g. '/acme/dashboard')
     * @param array  $headers All HTTP headers, keys normalised to lowercase
     * @param array  $query   $_GET parameters
     * @return string|null    Tenant slug, or null if not resolved
     */
    public function resolve(
        string $host    = '',
        string $path    = '',
        array  $headers = [],
        array  $query   = [],
    ): ?string {
        foreach ($this->drivers as $driver) {
            $slug = match ($driver) {
                'subdomain' => $this->fromSubdomain($host),
                'header'    => $this->fromHeader($headers),
                'path'      => $this->fromPath($path),
                'query'     => $this->fromQuery($query),
                default     => null,
            };

            if ($slug !== null && !in_array($slug, $this->excluded, true)) {
                return $slug;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Strategies
    // -------------------------------------------------------------------------

    /**
     * Extract subdomain from host.
     * 'acme.myapp.com' with base 'myapp.com' → 'acme'
     * 'www.myapp.com' → excluded → null
     */
    public function fromSubdomain(string $host): ?string
    {
        $host = strtolower(explode(':', $host)[0]); // strip port
        $base = strtolower($this->subdomainBase);

        if (!str_ends_with($host, '.' . $base)) return null;

        $sub = substr($host, 0, strlen($host) - strlen('.' . $base));
        if ($sub === '' || str_contains($sub, '.')) return null; // nested subdomains ignored

        return $sub;
    }

    /**
     * Extract from a specific request header.
     * Header: X-Tenant-ID: acme → 'acme'
     */
    public function fromHeader(array $headers): ?string
    {
        $key = strtolower(str_replace('-', '_', 'http_' . $this->headerKey));
        $alt = strtolower($this->headerKey);

        $value = $headers[$key] ?? $headers[$alt] ?? $headers[strtolower($this->headerKey)] ?? null;
        return $value !== null ? trim((string) $value) : null;
    }

    /**
     * Extract the Nth path segment.
     * '/acme/dashboard' with pathSegment=1 → 'acme'
     */
    public function fromPath(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $slug     = $segments[$this->pathSegment - 1] ?? null;
        return $slug !== null ? strtolower($slug) : null;
    }

    /**
     * Extract from query string parameter 'tenant'.
     * ?tenant=acme → 'acme'
     */
    public function fromQuery(array $query): ?string
    {
        $v = $query['tenant'] ?? null;
        return $v !== null ? trim(strtolower((string) $v)) : null;
    }

    // -------------------------------------------------------------------------
    // Config accessors (for testing)
    // -------------------------------------------------------------------------

    public function getDrivers(): array        { return $this->drivers; }
    public function getSubdomainBase(): string { return $this->subdomainBase; }
    public function getHeaderKey(): string     { return $this->headerKey; }
    public function getExcluded(): array       { return $this->excluded; }
}
