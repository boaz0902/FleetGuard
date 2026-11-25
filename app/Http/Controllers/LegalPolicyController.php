<?php

namespace App\Http\Controllers;

use App\Models\LegalPolicy;
use Illuminate\View\View;

class LegalPolicyController extends Controller
{
    public function show(string $slug): View
    {
        $policy = LegalPolicy::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
        $version = $policy->currentPublishedVersion;

        abort_unless($version, 404);

        return view('legal.show', [
            'policy' => $policy,
            'version' => $version,
            'policies' => LegalPolicy::query()
                ->where('is_published', true)
                ->orderBy('id')
                ->get()
                ->filter(fn (LegalPolicy $item): bool => $item->currentPublishedVersion !== null)
                ->values(),
            'isPreview' => false,
        ]);
    }
}
