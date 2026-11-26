<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_fleet_pages_have_page_specific_titles_and_descriptions(): void
    {
        $home = $this->get(route('home'))->assertOk()->getContent();
        $fleet = $this->get(route('fleet'))->assertOk()->getContent();

        $this->assertSame('Car Rental in Panabo City | FleetGuard', $this->metaTitle($home));
        $this->assertStringContainsString('displayed daily rates', $this->metaDescription($home));
        $this->assertStringContainsString('send a request for FleetGuard review', $this->metaDescription($home));
        $this->assertSame('Browse the Fleet | FleetGuard', $this->metaTitle($fleet));
        $this->assertStringContainsString('passenger capacity, transmission and fuel type', $this->metaDescription($fleet));
        $this->assertNotSame($this->metaDescription($home), $this->metaDescription($fleet));
    }

    public function test_homepage_has_local_heading_structure_truthful_metadata_and_existing_image_optimizations(): void
    {
        config([
            'fleetguard.phone' => null,
            'fleetguard.email' => null,
            'fleetguard.address' => null,
        ]);

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta property="og:title" content="Car Rental in Panabo City | FleetGuard">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('<meta name="twitter:title" content="Car Rental in Panabo City | FleetGuard">', false)
            ->assertSee('name="twitter:description"', false)
            ->assertSee('Drive Your Next Journey.')
            ->assertSee('href="#fleet"', false)
            ->assertSee('href="#how-it-works"', false)
            ->assertSee('href="#about"', false)
            ->assertSee('href="#faq"', false)
            ->assertSee('href="#contact"', false)
            ->assertSee('How much does it cost to rent a car?')
            ->assertSee('Can I request a car for multiple days?')
            ->assertSee('href="'.route('fleet').'"', false)
            ->assertSee('href="'.route('booking').'"', false)
            ->assertDontSee('property="og:image"', false)
            ->assertDontSee('images/seo-share.jpg', false)
            ->assertDontSee('images/new-logo.jpg', false);
        $html = $response->getContent();

        $document = new \DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        $xpath = new \DOMXPath($document);
        $h1s = $xpath->query('//h1');
        $this->assertSame(1, $h1s->length);
        $this->assertSame('Car Rental in Panabo City.', trim(preg_replace('/\s+/u', ' ', $h1s->item(0)->textContent) ?? ''));

        $h2Text = [];
        foreach ($xpath->query('//h2') as $heading) {
            $h2Text[] = trim(preg_replace('/\s+/u', ' ', $heading->textContent) ?? '');
        }
        foreach ([
            'Explore Our Car Rental Fleet',
            'How to Rent a Car with FleetGuard',
            'Why Choose FleetGuard?',
            'Frequently Asked Questions About Car Rental',
        ] as $expectedHeading) {
            $this->assertContains($expectedHeading, $h2Text);
        }

        $heroImages = $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " hero ")]//img');
        $this->assertSame(1, $heroImages->length);
        $heroImage = $heroImages->item(0);
        $this->assertStringEndsWith('/images/hero/fleetguard-hero.jpg', $heroImage->getAttribute('src'));
        $this->assertStringContainsString('fleetguard-hero-800.jpg', $heroImage->getAttribute('srcset'));
        $this->assertStringContainsString('fleetguard-hero-1600.jpg', $heroImage->getAttribute('srcset'));
        $this->assertSame('high', $heroImage->getAttribute('fetchpriority'));
        $this->assertSame('async', $heroImage->getAttribute('decoding'));
        $this->assertSame('', $heroImage->getAttribute('alt'));
        $this->assertFalse($heroImage->hasAttribute('loading'));
        $this->assertSame('2400', $heroImage->getAttribute('width'));
        $this->assertSame('1600', $heroImage->getAttribute('height'));

        $lazyImages = $xpath->query('//img[@loading="lazy"]');
        $this->assertGreaterThan(0, $lazyImages->length);
        foreach ($lazyImages as $image) {
            $this->assertNotSame('', $image->getAttribute('width'));
            $this->assertNotSame('', $image->getAttribute('height'));
        }

        $schema = $this->structuredData($html);
        $this->assertSame('Organization', $schema['@type']);
        $this->assertSame('FleetGuard', $schema['name']);
        $this->assertSame(route('home'), $schema['url']);
        $this->assertArrayNotHasKey('address', $schema);
        $this->assertArrayNotHasKey('telephone', $schema);
        $this->assertArrayNotHasKey('email', $schema);
        $this->assertArrayNotHasKey('aggregateRating', $schema);
    }

    public function test_vehicle_metadata_uses_vehicle_attributes_instead_of_generic_seed_descriptions(): void
    {
        $first = $this->createVehicle('city-sedan', 'City Sedan', 'sedan', 5, 'Automatic', 'Gasoline');
        $second = $this->createVehicle('family-mpv', 'Family MPV', 'mpv', 7, 'Manual', 'Diesel');

        $firstHtml = $this->get(route('fleet.show', $first))->assertOk()->getContent();
        $secondHtml = $this->get(route('fleet.show', $second))->assertOk()->getContent();

        $firstTitle = $this->metaTitle($firstHtml);
        $secondTitle = $this->metaTitle($secondHtml);
        $firstDescription = $this->metaDescription($firstHtml);
        $secondDescription = $this->metaDescription($secondHtml);

        $this->assertStringContainsString('City Sedan', $firstTitle);
        $this->assertStringContainsString('sedan', $firstDescription);
        $this->assertStringContainsString('5 passenger capacity', $firstDescription);
        $this->assertStringContainsString('Automatic transmission', $firstDescription);
        $this->assertStringContainsString('Gasoline fuel type', $firstDescription);
        $this->assertStringNotContainsString('Sample FleetGuard vehicle', $firstDescription);
        $this->assertNotSame($firstTitle, $secondTitle);
        $this->assertNotSame($firstDescription, $secondDescription);
    }

    private function createVehicle(
        string $id,
        string $name,
        string $category,
        int $passengerCapacity,
        string $transmission,
        string $fuelType,
    ): Vehicle {
        return Vehicle::query()->create([
            'id' => $id,
            'name' => $name,
            'brand' => 'FleetGuard',
            'model' => $name,
            'category' => $category,
            'description' => 'Sample FleetGuard vehicle.',
            'daily_rate' => 2500,
            'passenger_capacity' => $passengerCapacity,
            'transmission' => $transmission,
            'fuel_type' => $fuelType,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    private function metaTitle(string $html): string
    {
        $this->assertSame(1, preg_match('/<title>(.*?)<\/title>/s', $html, $matches));

        return $matches[1];
    }

    private function metaDescription(string $html): string
    {
        $this->assertSame(1, preg_match('/<meta name="description" content="(.*?)">/s', $html, $matches));

        return $matches[1];
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
}
