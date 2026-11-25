<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TestimonialSource;
use App\Enums\TestimonialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderTestimonialRequest;
use App\Http\Requests\SaveTestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class TestimonialController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::in(array_column(TestimonialSource::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(TestimonialStatus::cases(), 'value'))],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'featured' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $query = Testimonial::query();

        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('customer_display_name', 'like', '%'.$search.'%')
                    ->orWhere('review_content', 'like', '%'.$search.'%');
            });
        }

        if (filled($filters['source'] ?? null)) {
            $query->where('source', $filters['source']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['rating'] ?? null)) {
            $query->where('rating', $filters['rating']);
        }

        if (array_key_exists('featured', $filters) && $filters['featured'] !== null) {
            $query->where('is_featured', $filters['featured'] === '1');
        }

        return view('admin.testimonials.index', [
            'testimonials' => $query
                ->with(['user:id,name', 'booking:id,booking_reference,customer_id', 'booking.customer:id,full_name,user_id'])
                ->ordered()
                ->paginate(20)
                ->withQueryString(),
            'sources' => TestimonialSource::cases(),
            'statuses' => TestimonialStatus::cases(),
            'pendingCustomerSubmissions' => Testimonial::query()
                ->where('source', TestimonialSource::VerifiedCustomer)
                ->where('status', TestimonialStatus::Pending)
                ->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Testimonial::class);

        return view('admin.testimonials.create', [
            'testimonial' => Testimonial::make([
                'source' => TestimonialSource::Manual,
                'status' => TestimonialStatus::Draft,
                'rating' => 5,
                'review_date' => today(),
                'display_order' => ((int) Testimonial::query()->max('display_order')) + 1,
                'is_featured' => false,
            ]),
        ]);
    }

    public function store(SaveTestimonialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $image = $request->file('profile_image');
        unset($data['profile_image'], $data['remove_profile_image']);

        $data['source'] = TestimonialSource::Manual;
        $data['status'] = TestimonialStatus::Draft;
        $data['is_featured'] = (bool) $data['is_featured'];
        if ($image) {
            $data['profile_image_path'] = $this->storeProfileImage($image);
        }

        $testimonial = Testimonial::query()->create($data);

        return redirect()
            ->route('admin.testimonials.edit', $testimonial)
            ->with('status', 'Testimonial saved as a draft.');
    }

    public function edit(Testimonial $testimonial): View
    {
        $this->authorize('view', $testimonial);

        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(SaveTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validated();
        $image = $request->file('profile_image');
        $removeImage = (bool) ($data['remove_profile_image'] ?? false);
        $oldImagePath = $testimonial->profile_image_path;
        unset($data['profile_image'], $data['remove_profile_image']);

        if ($testimonial->source === TestimonialSource::VerifiedCustomer) {
            $data['customer_display_name'] = $testimonial->customer_display_name;
        }

        $data['is_featured'] = (bool) $data['is_featured'];
        if ($image) {
            $data['profile_image_path'] = $this->storeProfileImage($image);
        } elseif ($removeImage) {
            $data['profile_image_path'] = null;
        }

        $testimonial->update($data);

        if (($image || $removeImage) && $oldImagePath !== $testimonial->profile_image_path) {
            $this->deleteProfileImage($oldImagePath);
        }

        return redirect()
            ->route('admin.testimonials.edit', $testimonial)
            ->with('status', 'Testimonial updated.');
    }

    public function publish(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('publish', $testimonial);

        if (
            $testimonial->source === TestimonialSource::VerifiedCustomer
            && (! $testimonial->consent_given || ! $testimonial->consented_at)
        ) {
            return back()->withErrors([
                'testimonial' => 'This customer submission cannot be published without recorded display consent.',
            ]);
        }

        $testimonial->update(['status' => TestimonialStatus::Published]);

        return back()->with('status', 'Testimonial published on the homepage.');
    }

    public function reject(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('publish', $testimonial);

        if ($testimonial->status !== TestimonialStatus::Pending) {
            return back()->withErrors(['testimonial' => 'Only pending testimonials can be rejected.']);
        }

        $testimonial->update([
            'status' => TestimonialStatus::Rejected,
            'is_featured' => false,
        ]);

        return back()->with('status', 'Customer testimonial rejected.');
    }

    public function unpublish(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('publish', $testimonial);
        $testimonial->update(['status' => TestimonialStatus::Draft]);

        return back()->with('status', 'Testimonial unpublished and saved as a draft.');
    }

    public function toggleFeatured(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('feature', $testimonial);
        $testimonial->update(['is_featured' => ! $testimonial->is_featured]);

        return back()->with('status', $testimonial->is_featured
            ? 'Testimonial featured.'
            : 'Testimonial removed from featured testimonials.');
    }

    public function reorder(ReorderTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $direction = $request->validated('direction');
        $moved = DB::transaction(function () use ($direction, $testimonial): bool {
            $locked = Testimonial::query()->lockForUpdate()->findOrFail($testimonial->id);
            $ids = Testimonial::query()
                ->where('is_featured', $locked->is_featured)
                ->orderBy('display_order')
                ->orderByDesc('review_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->values()
                ->all();
            $index = array_search($locked->id, $ids, true);
            $neighborIndex = $index + ($direction === 'up' ? -1 : 1);

            if ($index === false || ! array_key_exists($neighborIndex, $ids)) {
                return false;
            }

            [$ids[$index], $ids[$neighborIndex]] = [$ids[$neighborIndex], $ids[$index]];

            foreach ($ids as $position => $id) {
                Testimonial::query()->whereKey($id)->update(['display_order' => $position]);
            }

            return true;
        });

        return back()->with('status', $moved ? 'Display order updated.' : 'This testimonial is already at the edge of its group.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('delete', $testimonial);
        $imagePath = $testimonial->profile_image_path;
        $testimonial->delete();
        $this->deleteProfileImage($imagePath);

        return redirect()->route('admin.testimonials.index')->with('status', 'Testimonial deleted.');
    }

    private function storeProfileImage(UploadedFile $image): string
    {
        $path = $image->store('testimonials', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The testimonial profile image could not be stored.');
        }

        return $path;
    }

    private function deleteProfileImage(?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'testimonials/')) {
            return;
        }

        if (! Storage::disk('public')->delete($path)) {
            Log::warning('A testimonial profile image could not be removed.', ['path' => $path]);
        }
    }
}
