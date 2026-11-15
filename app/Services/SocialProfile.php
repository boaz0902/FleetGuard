<?php

namespace App\Services;

class SocialProfile
{
    private const PLATFORMS = [
        'facebook' => [
            'name' => 'Facebook',
            'icon' => 'facebook',
            'hosts' => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
            'base' => 'https://www.facebook.com/',
            'label' => 'Facebook page',
            'pattern' => '/^[A-Za-z0-9.]{5,50}$/',
        ],
        'instagram' => [
            'name' => 'Instagram',
            'icon' => 'instagram',
            'hosts' => ['instagram.com', 'www.instagram.com'],
            'base' => 'https://www.instagram.com/',
            'label' => 'Instagram profile',
            'pattern' => '/^[A-Za-z0-9._]{1,30}$/',
        ],
        'tiktok' => [
            'name' => 'TikTok',
            'icon' => 'tiktok',
            'hosts' => ['tiktok.com', 'www.tiktok.com'],
            'base' => 'https://www.tiktok.com/',
            'label' => 'TikTok profile',
            'pattern' => '/^[A-Za-z0-9._]{2,24}$/',
        ],
        'x' => [
            'name' => 'X',
            'icon' => 'twitter-x',
            'hosts' => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
            'base' => 'https://x.com/',
            'label' => 'X profile',
            'pattern' => '/^[A-Za-z0-9_]{1,15}$/',
        ],
        'youtube' => [
            'name' => 'YouTube',
            'icon' => 'youtube',
            'hosts' => ['youtube.com', 'www.youtube.com', 'm.youtube.com'],
            'base' => 'https://www.youtube.com/',
            'label' => 'YouTube channel',
            'pattern' => '/^[A-Za-z0-9._-]{3,30}$/',
        ],
    ];

    public static function platforms(): array
    {
        $additionalPlatforms = config('fleetguard.social_platforms', []);

        return is_array($additionalPlatforms)
            ? array_replace(self::PLATFORMS, $additionalPlatforms)
            : self::PLATFORMS;
    }

    public function parse(string $platform, ?string $value): array
    {
        $definition = self::platforms()[$platform] ?? null;

        if ($definition === null) {
            return $this->invalidResult($platform);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return $this->emptyResult($platform, $definition);
        }

        if (str_starts_with($value, '@')) {
            return $this->fromHandle($platform, $value, $definition);
        }

        $parts = parse_url($value);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || ! isset($parts['host'])) {
            return $this->invalidResult($platform, $definition);
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return $this->invalidResult($platform, $definition);
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if (! in_array($host, $definition['hosts'], true)) {
            return $this->invalidResult($platform, $definition);
        }

        $segments = array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/')), fn (string $segment): bool => $segment !== ''));
        $segments = array_map(fn (string $segment): string => rawurldecode($segment), $segments);

        if ($platform === 'facebook' && strtolower($segments[0] ?? '') === 'profile.php') {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['id'] ?? null;

            if (! is_string($id) && ! is_int($id)) {
                return $this->invalidResult($platform, $definition);
            }

            $id = (string) $id;
            if (! preg_match('/^\d{1,30}$/', $id)) {
                return $this->invalidResult($platform, $definition);
            }

            return $this->profileResult($platform, $definition, null, $definition['base'].'profile.php?id='.rawurlencode($id), 'Facebook page');
        }

        if ($platform === 'facebook' && strtolower($segments[0] ?? '') === 'people' && count($segments) === 3) {
            if (! preg_match('/^[\pL\pN._-]{1,100}$/u', $segments[1]) || ! preg_match('/^\d{1,30}$/', $segments[2])) {
                return $this->invalidResult($platform, $definition);
            }

            $url = $definition['base'].'people/'.rawurlencode($segments[1]).'/'.rawurlencode($segments[2]);

            return $this->profileResult($platform, $definition, null, $url, 'Facebook page');
        }

        if ($platform === 'youtube' && strtolower($segments[0] ?? '') === 'channel' && count($segments) >= 2) {
            if (! preg_match('/^[A-Za-z0-9_-]{3,100}$/', $segments[1])) {
                return $this->invalidResult($platform, $definition);
            }

            return $this->profileResult($platform, $definition, null, $definition['base'].'channel/'.rawurlencode($segments[1]), 'YouTube channel');
        }

        if ($platform === 'youtube' && in_array(strtolower($segments[0] ?? ''), ['c', 'user'], true) && count($segments) >= 2) {
            if (! preg_match('/^[A-Za-z0-9._-]{1,100}$/', $segments[1])) {
                return $this->invalidResult($platform, $definition);
            }

            return $this->profileResult($platform, $definition, null, $definition['base'].strtolower($segments[0]).'/'.rawurlencode($segments[1]), 'YouTube channel');
        }

        if ($platform === 'tiktok') {
            $segments[0] = ltrim($segments[0] ?? '', '@');
        } elseif ($platform === 'youtube') {
            $segments[0] = ltrim($segments[0] ?? '', '@');
        }

        if (count($segments) !== 1 || ! preg_match($definition['pattern'], $segments[0] ?? '')) {
            return $this->invalidResult($platform, $definition);
        }

        return $this->fromHandle($platform, $segments[0], $definition);
    }

    private function fromHandle(string $platform, string $handle, array $definition): array
    {
        $handle = ltrim($handle, '@');

        if ($handle === '' || ! preg_match($definition['pattern'], $handle)) {
            return $this->invalidResult($platform, $definition);
        }

        $path = $platform === 'tiktok' ? '@'.$handle : ($platform === 'youtube' ? '@'.$handle : $handle);
        $url = $definition['base'].$path;
        if ($platform === 'instagram') {
            $url .= '/';
        }

        return $this->profileResult($platform, $definition, $handle, $url, '@'.$handle);
    }

    private function profileResult(string $platform, array $definition, ?string $username, ?string $url, ?string $label): array
    {
        return [
            'valid' => true,
            'platform' => $platform,
            'name' => $definition['name'],
            'icon' => $definition['icon'],
            'username' => $username,
            'label' => $label,
            'url' => $url,
            'error' => null,
        ];
    }

    private function emptyResult(string $platform, array $definition): array
    {
        return $this->profileResult($platform, $definition, null, null, null);
    }

    private function invalidResult(string $platform, ?array $definition = null): array
    {
        return [
            'valid' => false,
            'platform' => $platform,
            'name' => $definition['name'] ?? ucfirst($platform),
            'icon' => $definition['icon'] ?? 'link-45deg',
            'username' => null,
            'label' => null,
            'url' => null,
            'error' => 'Enter a valid '.$this->platformName($definition, $platform).' profile URL or @handle.',
        ];
    }

    private function platformName(?array $definition, string $platform): string
    {
        return strtolower($definition['name'] ?? $platform);
    }
}
