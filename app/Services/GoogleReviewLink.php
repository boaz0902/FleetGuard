<?php

namespace App\Services;

class GoogleReviewLink
{
    private const GOOGLE_HOSTS = [
        'google.com',
        'google.com.ph',
        'www.google.com',
        'www.google.com.ph',
        'maps.google.com',
        'maps.app.goo.gl',
        'goo.gl',
        'g.page',
        'search.google.com',
    ];

    public function parse(?string $value, string $kind): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return ['valid' => true, 'url' => null, 'error' => null];
        }

        $parts = parse_url($value);
        if (
            ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7F]/', $value)
        ) {
            return $this->invalidResult();
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $path = $parts['path'] ?? '';
        $validHost = in_array($host, self::GOOGLE_HOSTS, true);

        if (! $validHost || ! $this->hasExpectedPath($host, $path, $kind)) {
            return $this->invalidResult();
        }

        $url = 'https://'.$host.$path;
        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        return ['valid' => true, 'url' => $url, 'error' => null];
    }

    private function hasExpectedPath(string $host, string $path, string $kind): bool
    {
        if (in_array($host, ['maps.app.goo.gl', 'g.page'], true)) {
            return trim($path, '/') !== '';
        }

        if ($host === 'goo.gl') {
            return $kind === 'read'
                ? (bool) preg_match('~^/maps/[^/]+/?$~', $path)
                : false;
        }

        if ($host === 'search.google.com') {
            return $kind === 'write' && preg_match('~^/local/writereview/?$~', $path) === 1;
        }

        return preg_match('~^/maps(?:/|$)~i', $path) === 1;
    }

    private function invalidResult(): array
    {
        return [
            'valid' => false,
            'url' => null,
            'error' => 'Enter a secure Google Maps profile or review link.',
        ];
    }
}
