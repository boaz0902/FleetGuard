<?php

namespace Tests\Unit;

use App\Services\SocialProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialProfileTest extends TestCase
{
    #[DataProvider('handleProfiles')]
    public function test_platform_handles_are_normalized(string $platform, string $input, string $username, string $url): void
    {
        $result = (new SocialProfile)->parse($platform, $input);

        $this->assertTrue($result['valid']);
        $this->assertSame($username, $result['username']);
        $this->assertSame($url, $result['url']);
    }

    public static function handleProfiles(): array
    {
        return [
            'facebook' => ['facebook', '@fleetguard', 'fleetguard', 'https://www.facebook.com/fleetguard'],
            'instagram' => ['instagram', '@fleet.guard', 'fleet.guard', 'https://www.instagram.com/fleet.guard/'],
            'tiktok' => ['tiktok', '@fleetguard', 'fleetguard', 'https://www.tiktok.com/@fleetguard'],
            'x' => ['x', '@fleet_guard', 'fleet_guard', 'https://x.com/fleet_guard'],
            'youtube' => ['youtube', '@FleetGuard', 'FleetGuard', 'https://www.youtube.com/@FleetGuard'],
        ];
    }

    public function test_profile_urls_strip_tracking_and_support_non_handle_platform_profiles(): void
    {
        $parser = new SocialProfile;

        $instagram = $parser->parse('instagram', 'https://www.instagram.com/fleet.guard/?utm_source=test#posts');
        $this->assertSame('https://www.instagram.com/fleet.guard/', $instagram['url']);

        $facebookId = $parser->parse('facebook', 'https://facebook.com/profile.php?id=12345&utm_source=test');
        $this->assertSame('Facebook page', $facebookId['label']);
        $this->assertNull($facebookId['username']);
        $this->assertSame('https://www.facebook.com/profile.php?id=12345', $facebookId['url']);

        $facebookPeople = $parser->parse('facebook', 'https://www.facebook.com/people/Fleet-Guard/12345?ref=about');
        $this->assertSame('Facebook page', $facebookPeople['label']);
        $this->assertSame('https://www.facebook.com/people/Fleet-Guard/12345', $facebookPeople['url']);

        $youtubeChannel = $parser->parse('youtube', 'https://youtube.com/channel/UC123456?si=tracking');
        $this->assertSame('YouTube channel', $youtubeChannel['label']);
        $this->assertSame('https://www.youtube.com/channel/UC123456', $youtubeChannel['url']);
    }

    #[DataProvider('invalidProfiles')]
    public function test_platform_mismatch_bad_schemes_credentials_and_ports_are_rejected(string $platform, string $input): void
    {
        $this->assertFalse((new SocialProfile)->parse($platform, $input)['valid']);
    }

    public static function invalidProfiles(): array
    {
        return [
            'host mismatch' => ['instagram', 'https://facebook.com/fleetguard'],
            'http scheme' => ['instagram', 'http://instagram.com/fleetguard'],
            'javascript scheme' => ['x', 'javascript:alert(1)'],
            'data scheme' => ['youtube', 'data:text/html,profile'],
            'credentials' => ['instagram', 'https://user:pass@instagram.com/fleetguard'],
            'port' => ['tiktok', 'https://tiktok.com:8443/@fleetguard'],
            'invalid handle' => ['x', '@this-handle-is-far-too-long-to-be-valid'],
        ];
    }
}
