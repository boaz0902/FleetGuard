<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        return response(
            "User-agent: *\nDisallow:\nSitemap: ".route('seo.sitemap')."\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function sitemap(): Response
    {
        $vehicles = Vehicle::query()
            ->whereIn('status', [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_MAINTENANCE])
            ->orderBy('id')
            ->get();

        return response()
            ->view('seo.sitemap', [
                'urls' => collect([
                    route('home'),
                    route('fleet'),
                ])->concat($vehicles->map(fn (Vehicle $vehicle): string => route('fleet.show', $vehicle))),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}