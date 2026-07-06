<?php
declare(strict_types=1);

namespace Nemesis\Support;

use Nemesis\Core\Config;
use Nemesis\Http\Request;

class IpAccess
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $defaults = [
            'allow_all' => true,
            'allow' => [],
            'block' => [],
            'trusted_proxies' => [],
        ];

        $this->config = array_merge($defaults, $config);
        $this->config['allow'] = array_values(array_unique(array_filter(array_map([$this, 'normalizeRule'], (array) $this->config['allow']))));
        $this->config['block'] = array_values(array_unique(array_filter(array_map([$this, 'normalizeRule'], (array) $this->config['block']))));
        $this->config['trusted_proxies'] = array_values(array_unique(array_filter(array_map([$this, 'normalizeRule'], (array) $this->config['trusted_proxies']))));
    }

    public static function fromConfig(): static
    {
        $config = Config::get('ip', []);
        return new static(is_array($config) ? $config : []);
    }

    public function clientIp(?Request $request = null): string
    {
        $request ??= new Request();
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

        if (($this->config['trusted_proxies'] ?? []) === []) {
            return $this->normalizeRule($ip);
        }

        $forwarded = $request->header('X-Forwarded-For', '');
        if (is_string($forwarded) && $forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            foreach ($parts as $candidate) {
                if ($candidate !== '' && !$this->isTrustedProxy($candidate)) {
                    return $this->normalizeRule($candidate);
                }
            }
        }

        return $this->normalizeRule($ip);
    }

    public function isAllowed(?string $ip = null): bool
    {
        $ip = $this->normalizeRule($ip ?? $this->clientIp());

        if ($this->isBlocked($ip)) {
            return false;
        }

        $allow = $this->config['allow'] ?? [];
        if ($allow !== []) {
            foreach ($allow as $rule) {
                if ($this->matchesRule($ip, $rule)) {
                    return true;
                }
            }

            return false;
        }

        return (bool) ($this->config['allow_all'] ?? true);
    }

    public function isBlocked(?string $ip = null): bool
    {
        $ip = $this->normalizeRule($ip ?? $this->clientIp());

        foreach ((array) ($this->config['block'] ?? []) as $rule) {
            if ($this->matchesRule($ip, (string) $rule)) {
                return true;
            }
        }

        return false;
    }

    public function status(?string $ip = null): array
    {
        $ip = $this->normalizeRule($ip ?? $this->clientIp());

        return [
            'ip' => $ip,
            'allow_all' => (bool) ($this->config['allow_all'] ?? true),
            'allowed' => $this->isAllowed($ip),
            'blocked' => $this->isBlocked($ip),
            'allow' => array_values((array) ($this->config['allow'] ?? [])),
            'block' => array_values((array) ($this->config['block'] ?? [])),
            'trusted_proxies' => array_values((array) ($this->config['trusted_proxies'] ?? [])),
        ];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function withRule(string $type, string $rule): static
    {
        $type = strtolower(trim($type));
        $rule = $this->normalizeRule($rule);

        if ($rule === '') {
            return new static($this->config);
        }

        $config = $this->config;
        if ($type === 'allow') {
            $config['allow'][] = $rule;
            $config['allow_all'] = false;
        } elseif ($type === 'block') {
            $config['block'][] = $rule;
        }

        return new static($config);
    }

    public function withoutRule(string $type, string $rule): static
    {
        $type = strtolower(trim($type));
        $rule = $this->normalizeRule($rule);
        $config = $this->config;

        if ($type === 'allow') {
            $config['allow'] = array_values(array_filter((array) $config['allow'], fn($item) => $this->normalizeRule((string) $item) !== $rule));
        } elseif ($type === 'block') {
            $config['block'] = array_values(array_filter((array) $config['block'], fn($item) => $this->normalizeRule((string) $item) !== $rule));
        }

        if ($config['allow'] === []) {
            $config['allow_all'] = true;
        }

        return new static($config);
    }

    public function reset(): static
    {
        return new static([
            'allow_all' => true,
            'allow' => [],
            'block' => [],
            'trusted_proxies' => [],
        ]);
    }

    protected function normalizeRule(string $rule): string
    {
        return strtolower(trim($rule));
    }

    protected function isTrustedProxy(string $ip): bool
    {
        foreach ((array) ($this->config['trusted_proxies'] ?? []) as $rule) {
            if ($this->matchesRule($ip, (string) $rule)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesRule(string $ip, string $rule): bool
    {
        $rule = $this->normalizeRule($rule);
        if ($rule === '' || $rule === '*') {
            return true;
        }

        if (str_contains($rule, '*')) {
            return fnmatch($rule, $ip);
        }

        if (str_contains($rule, '/')) {
            return $this->matchesCidr($ip, $rule);
        }

        return $ip === $rule;
    }

    protected function matchesCidr(string $ip, string $cidr): bool
    {
        [$network, $mask] = array_pad(explode('/', $cidr, 2), 2, null);
        $mask = (int) $mask;

        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $length = strlen($ipBin) * 8;
        if ($mask < 0 || $mask > $length) {
            return false;
        }

        $bytes = intdiv($mask, 8);
        $bits = $mask % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $maskByte = chr((0xFF << (8 - $bits)) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($maskByte)) === (ord($netBin[$bytes]) & ord($maskByte));
    }
}
