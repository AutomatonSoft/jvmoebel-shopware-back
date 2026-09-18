<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

final class UrlNormalizer
{
    public function validate(string $url): string
    {
        $url = trim($url);
        if ('' === $url) {
            throw new \InvalidArgumentException('URL must not be empty.');
        }
        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('URL must not exceed 2048 bytes.');
        }
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL must be a valid absolute URL.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('URL must contain a scheme and host.');
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('URL scheme must be http or https.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('URL user information is not allowed.');
        }
        if (isset($parts['fragment'])) {
            throw new \InvalidArgumentException('URL fragments are not allowed.');
        }

        return $url;
    }

    public function normalize(string $url): string
    {
        $url = $this->validate($url);
        $parts = parse_url($url);
        \assert(is_array($parts) && isset($parts['scheme'], $parts['host']));

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && !(('http' === $scheme && 80 === $parts['port']) || ('https' === $scheme && 443 === $parts['port']))
            ? ':'.$parts['port']
            : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    public function hash(string $url): string
    {
        return hash('sha256', $this->normalize($url));
    }

    public function host(string $url): string
    {
        $parts = parse_url($this->validate($url));
        \assert(is_array($parts) && isset($parts['host']));

        return strtolower($parts['host']);
    }
}
