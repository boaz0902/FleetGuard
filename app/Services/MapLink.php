<?php

namespace App\Services;

class MapLink
{
    private const GOOGLE_HOSTS = [
        'google.com',
        'www.google.com',
        'google.com.ph',
        'www.google.com.ph',
        'maps.google.com',
    ];

    public function parse(?string $value, ?string $address = null): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            $address = trim((string) $address);

            if ($address === '') {
                return $this->emptyResult();
            }

            $openUrl = $this->searchUrl($address);

            return [
                'valid' => true,
                'kind' => 'address',
                'stored_url' => null,
                'open_url' => $openUrl,
                'embed_url' => $openUrl.'&output=embed',
                'error' => null,
            ];
        }

        if (stripos($value, '<iframe') !== false) {
            if (! preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $value, $matches)) {
                return $this->invalidResult();
            }

            $source = null;
            foreach ([1, 2, 3] as $index) {
                if (($matches[$index] ?? '') !== '') {
                    $source = $matches[$index];
                    break;
                }
            }
            if ($source === null) {
                return $this->invalidResult();
            }

            $value = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (str_contains($value, '<') || str_contains($value, '>')) {
            return $this->invalidResult();
        }

        if (str_starts_with($value, '/maps/embed?')) {
            $value = 'https://www.google.com'.$value;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return $this->invalidResult();
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return $this->invalidResult();
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        if (in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)) {
            $validShortPath = $host === 'goo.gl'
                ? (bool) preg_match('~^/maps/[^/]+/?$~', $path)
                : trim($path, '/') !== '';

            if (! $validShortPath || preg_match('/[\x00-\x20]/', $path.$query)) {
                return $this->invalidResult();
            }

            $openUrl = 'https://'.$host.$path.($query !== '' ? '?'.$query : '');

            return [
                'valid' => true,
                'kind' => 'short',
                'stored_url' => $openUrl,
                'open_url' => $openUrl,
                'embed_url' => null,
                'error' => null,
            ];
        }

        if (! in_array($host, self::GOOGLE_HOSTS, true) || ! str_starts_with($path, '/maps') || preg_match('/[\x00-\x20]/', $path.$query)) {
            return $this->invalidResult();
        }

        if (preg_match('~^/maps/embed/?$~i', $path)) {
            $embedUrl = 'https://www.google.com/maps/embed'.($query !== '' ? '?'.$query : '');

            return [
                'valid' => true,
                'kind' => 'embed',
                'stored_url' => $embedUrl,
                'open_url' => $embedUrl,
                'embed_url' => $embedUrl,
                'error' => null,
            ];
        }

        parse_str($query, $queryValues);
        $search = is_string($queryValues['q'] ?? null) ? trim($queryValues['q']) : '';

        if ($search === '' && preg_match('~@(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)~', $path, $coordinates)) {
            $latitude = (float) $coordinates[1];
            $longitude = (float) $coordinates[2];

            if (abs($latitude) > 90 || abs($longitude) > 180) {
                return $this->invalidResult();
            }

            $search = $coordinates[1].','.$coordinates[2];
        }

        if ($search === '' && preg_match('~^/maps/(?:place|search)/([^/]+)~', $path, $pathMatch)) {
            $search = trim(urldecode($pathMatch[1]));
        }

        if ($search === '' || preg_match('/[\x00-\x1F\x7F]/', $search)) {
            return $this->invalidResult();
        }

        $openUrl = $this->searchUrl($search);

        return [
            'valid' => true,
            'kind' => 'search',
            'stored_url' => $openUrl,
            'open_url' => $openUrl,
            'embed_url' => $openUrl.'&output=embed',
            'error' => null,
        ];
    }

    private function searchUrl(string $query): string
    {
        return 'https://www.google.com/maps?q='.rawurlencode($query);
    }

    private function emptyResult(): array
    {
        return [
            'valid' => true,
            'kind' => 'empty',
            'stored_url' => null,
            'open_url' => null,
            'embed_url' => null,
            'error' => null,
        ];
    }

    private function invalidResult(): array
    {
        return [
            'valid' => false,
            'kind' => 'invalid',
            'stored_url' => null,
            'open_url' => null,
            'embed_url' => null,
            'error' => "This doesn't look like a Google Maps link.",
        ];
    }
}
