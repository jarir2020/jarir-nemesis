<?php

namespace JarirAhmed\UserInfo;

class UserInfo
{
    /**
     * Get the user's IP address.
     *
     * @return string|null
     */
    public function getUserIp(): ?string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * Get the user's user agent (browser and OS details).
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent';
    }

    /**
     * Fetch IP information from an external API.
     *
     * @param string $ip
     * @return array
     * @throws \Exception
     */
    public function getIpInformation(string $ip): array
    {
        $url = "http://ip-api.com/json/{$ip}";
        $response = file_get_contents($url);

        if ($response === false) {
            throw new \Exception("Unable to fetch IP information.");
        }

        $data = json_decode($response, true);
        if ($data['status'] !== 'success') {
            throw new \Exception("Failed to retrieve IP information.");
        }

        return $data;
    }

    /**
     * Capture screen size if provided by the frontend.
     *
     * @param string $width
     * @param string $height
     * @return array
     */
    public function getScreenSize(string $width = 'Unknown', string $height = 'Unknown'): array
    {
        return [
            'width' => $width,
            'height' => $height
        ];
    }

    /**
     * Get the referer URL (where the user came from).
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Get the request method (GET, POST, etc.).
     *
     * @return string
     */
    public function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get the request time.
     *
     * @return int
     */
    public function getRequestTime(): int
    {
        return $_SERVER['REQUEST_TIME'];
    }

    /**
     * Get the browser's preferred language.
     *
     * @return string
     */
    public function getBrowserLanguage(): string
    {
        return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown';
    }

    /**
     * Get the current page URL (Request URI).
     *
     * @return string
     */
    public function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Get the host (domain) from where the request was made.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * Get the protocol (HTTP or HTTPS).
     *
     * @return string
     */
    public function getProtocol(): string
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'HTTPS' : 'HTTP';
    }

    /**
     * Get the operating system from the user agent string.
     *
     * @return string
     */
    public function getOperatingSystem(): string
    {
        $userAgent = $this->getUserAgent();
        $osArray = [
            'Windows' => 'Win',
            'Mac OS' => '(Mac_PowerPC)|(Macintosh)',
            'Linux' => '(X11)|(Linux)',
            'Android' => 'Android',
            'iPhone' => 'iPhone',
            'iPad' => 'iPad'
        ];

        foreach ($osArray as $os => $regex) {
            if (preg_match("/$regex/i", $userAgent)) {
                return $os;
            }
        }

        return 'Unknown OS';
    }

    /**
     * Get the browser information from the user agent string.
     *
     * @return string
     */
    public function getBrowser(): string
    {
        $userAgent = $this->getUserAgent();
        $browserArray = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Edge' => 'Edge',
            'Internet Explorer' => '(MSIE)|(Trident/7)',
            'Opera' => 'Opera'
        ];

        foreach ($browserArray as $browser => $regex) {
            if (preg_match("/$regex/i", $userAgent)) {
                return $browser;
            }
        }

        return 'Unknown Browser';
    }

    /**
     * Get the full list of captured user information.
     *
     * @return array
     */
    public function getAllInfo(): array
    {
        $ip = $this->getUserIp();
        $ipInfo = $this->getIpInformation($ip);

        return [
            'ip' => $ip,
            'user_agent' => $this->getUserAgent(),
            'referer' => $this->getReferer(),
            'request_method' => $this->getRequestMethod(),
            'request_time' => $this->getRequestTime(),
            'browser_language' => $this->getBrowserLanguage(),
            'request_uri' => $this->getRequestUri(),
            'host' => $this->getHost(),
            'protocol' => $this->getProtocol(),
            'operating_system' => $this->getOperatingSystem(),
            'browser' => $this->getBrowser(),
            'ip_info' => $ipInfo
        ];
    }
}
