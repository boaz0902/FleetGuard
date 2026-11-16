<?php

namespace Tests\Unit;

use App\Services\MapLink;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MapLinkTest extends TestCase
{
    public function test_place_coordinate_and_query_urls_become_google_embed_searches(): void
    {
        $parser = new MapLink;

        $place = $parser->parse('https://www.google.com/maps/place/Panabo+City,+Philippines/');
        $this->assertSame('https://www.google.com/maps?q=Panabo%20City%2C%20Philippines', $place['stored_url']);
        $this->assertSame($place['stored_url'].'&output=embed', $place['embed_url']);

        $coordinates = $parser->parse('https://maps.google.com/maps/@14.9,125.6,15z');
        $this->assertSame('https://www.google.com/maps?q=14.9%2C125.6', $coordinates['stored_url']);

        $query = $parser->parse('https://google.com.ph/maps?q=Panabo+City');
        $this->assertSame('https://www.google.com/maps?q=Panabo%20City', $query['stored_url']);
    }

    public function test_embed_iframe_short_link_and_address_fallback_are_supported(): void
    {
        $parser = new MapLink;
        $iframe = $parser->parse('<iframe title="FleetGuard map" src="https://www.google.com/maps/embed?pb=!1m0&amp;key=value" width="600" allowfullscreen></iframe>');
        $this->assertSame('https://www.google.com/maps/embed?pb=!1m0&key=value', $iframe['stored_url']);
        $this->assertSame($iframe['stored_url'], $iframe['embed_url']);

        $relativeEmbed = $parser->parse('/maps/embed?pb=!1m0');
        $this->assertSame('https://www.google.com/maps/embed?pb=!1m0', $relativeEmbed['embed_url']);

        $short = $parser->parse('https://maps.app.goo.gl/AbCdEf?entry=ttu');
        $this->assertSame('short', $short['kind']);
        $this->assertSame('https://maps.app.goo.gl/AbCdEf?entry=ttu', $short['open_url']);
        $this->assertNull($short['embed_url']);

        $fallback = $parser->parse(null, 'Panabo City, Philippines');
        $this->assertSame('address', $fallback['kind']);
        $this->assertSame('https://www.google.com/maps?q=Panabo%20City%2C%20Philippines', $fallback['open_url']);
        $this->assertSame($fallback['open_url'].'&output=embed', $fallback['embed_url']);
    }

    #[DataProvider('invalidLinks')]
    public function test_non_google_hosts_unsafe_urls_credentials_and_ports_are_rejected(string $url): void
    {
        $this->assertFalse((new MapLink)->parse($url)['valid']);
    }

    public static function invalidLinks(): array
    {
        return [
            'foreign host' => ['https://maps.example.test/maps?q=Panabo'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,example'],
            'credentials' => ['https://user:pass@www.google.com/maps?q=Panabo'],
            'port' => ['https://www.google.com:8443/maps?q=Panabo'],
            'invalid coordinates' => ['https://www.google.com/maps/@91,125,15z'],
            'iframe with unsafe source' => ['<iframe src="javascript:alert(1)"></iframe>'],
        ];
    }
}
