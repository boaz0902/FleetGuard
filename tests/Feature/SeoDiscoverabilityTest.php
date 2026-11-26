<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_only_public_indexable_pages_and_available_vehicles(): void
    {
        $activeVehicle = $this->createVehicle('public-car', Vehicle::STATUS_ACTIVE);
        $maintenanceVehicle = $this->createVehicle('maintenance-car', Vehicle::STATUS_MAINTENANCE);
        $this->createVehicle('private-car', Vehicle::STATUS_INACTIVE);

        $response = $this->get(route('seo.sitemap'))->assertOk();

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('fleet').'</loc>', false)
            ->assertSee('<loc>'.route('fleet.show', $activeVehicle).'</loc>', false)
            ->assertSee('<loc>'.route('fleet.show', $maintenanceVehicle).'</loc>', false)
            ->assertDontSee(route('fleet.show', 'private-car'), false)
            ->assertDontSee(route('booking'), false);

        $this->assertSame(4, substr_count($response->getContent(), '<url>'));
        preg_match_all('/<loc>(.*?)<\/loc>/s', $response->getContent(), $locations);
        foreach ($locations[1] as $location) {
            $this->assertStringNotContainsString('?', $location);
        }
    }

    public function test_robots_txt_allows_crawling_and_discloses_the_sitemap_url(): void
    {
        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText("User-agent: *\nDisallow:\nSitemap: ".route('seo.sitemap'));
    }

    public function test_indexable_pages_have_clean_canonicals_and_nonpublic_pages_are_noindexed(): void
    {
        $vehicle = $this->createVehicle('canonical-car', Vehicle::STATUS_ACTIVE);

        $this->get(route('home', ['utm_source' => 'campaign']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertDontSee('utm_source=campaign', false);

        $this->get(route('fleet'))->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('fleet').'">', false);

        $this->get(route('fleet.show', $vehicle))->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false);

        foreach ([route('booking'), route('login'), route('register')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('<meta name="robots" content="noindex, follow">', false);
        }

        $this->get(route('admin.login'))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_business_structured_data_uses_only_configured_business_details(): void
    {
        config([
            'fleetguard.name' => 'Configured Rental',
            'fleetguard.phone' => '+63 912 345 6789',
            'fleetguard.email' => null,
            'fleetguard.address' => null,
            'fleetguard.google_rating' => '5.0',
            'fleetguard.google_review_count' => '99',
        ]);

        $response = $this->get(route('home'))->assertOk();
        $this->assertSame(
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Configured Rental',
                'url' => route('home'),
                'telephone' => '+63 912 345 6789',
            ],
            $this->structuredData($response->getContent()),
        );
    }

    public function test_fleet_pages_emit_breadcrumb_structured_data_matching_the_visible_trail(): void
    {
        $vehicle = $this->createVehicle('breadcrumb-car', Vehicle::STATUS_ACTIVE);

        $fleetHtml = $this->get(route('fleet'))->assertOk()->getContent();
        $vehicleHtml = $this->get(route('fleet.show', $vehicle))->assertOk()->getContent();

        $this->assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fleet', 'item' => route('fleet')],
            ],
        ], $this->structuredDataByType($fleetHtml, 'BreadcrumbList'));
        $this->assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fleet', 'item' => route('fleet')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $vehicle->name, 'item' => route('fleet.show', $vehicle)],
            ],
        ], $this->structuredDataByType($vehicleHtml, 'BreadcrumbList'));
    }

    public function test_fleet_pages_avoid_unverified_vehicle_condition_claims(): void
    {
        $vehicle = $this->createVehicle('condition-claim-car', Vehicle::STATUS_ACTIVE);

        $this->get(route('fleet'))->assertOk()
            ->assertDontSee('insured', false)
            ->assertDontSee('maintained', false)
            ->assertDontSee('Local support', false);
        $this->get(route('fleet.show', $vehicle))->assertOk()
            ->assertDontSee('insured', false)
            ->assertDontSee('maintained vehicle', false);
    }

    public function test_vehicle_open_graph_image_is_emitted_only_for_an_existing_vehicle_asset(): void
    {
        $vehicleWithImage = $this->createVehicle('image-car', Vehicle::STATUS_ACTIVE, 'images/fleet/vios.webp');
        $vehicleWithoutImage = $this->createVehicle('no-image-car', Vehicle::STATUS_ACTIVE);

        $this->get(route('fleet.show', $vehicleWithImage))->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('images/fleet/vios.webp').'">', false);
        $this->get(route('fleet.show', $vehicleWithoutImage))->assertOk()
            ->assertDontSee('property="og:image"', false);
        $this->get(route('home'))->assertOk()
            ->assertDontSee('property="og:image"', false);
    }

    private function createVehicle(string $id, string $status, ?string $imagePath = null): Vehicle
    {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => ucfirst(str_replace('-', ' ', $id)),
            'brand' => 'Toyota',
            'model' => 'Test',
            'category' => 'sedan',
            'description' => 'Test vehicle description.',
            'daily_rate' => 2500,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => $imagePath,
            'status' => $status,
        ]);
    }

    private function structuredData(string $html): array
    {
        $this->assertSame(1, preg_match(
            '/<script type="application\/ld\+json">(.*?)<\/script>/s',
            $html,
            $matches,
        ));

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function structuredDataByType(string $html, string $type): array
    {
        $count = preg_match_all(
            '/<script type="application\/ld\+json">(.*?)<\/script>/s',
            $html,
            $matches,
        );
        $this->assertGreaterThan(0, $count);

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === $type) {
                return $data;
            }
        }

        $this->fail("No {$type} structured data was found.");
    }
}