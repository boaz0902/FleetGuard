<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Services\BookingStatusTransitionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingManagementController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in([
                    Booking::STATUS_PENDING,
                    Booking::STATUS_APPROVED,
                    Booking::STATUS_REJECTED,
                    Booking::STATUS_ONGOING,
                    Booking::STATUS_COMPLETED,
                    Booking::STATUS_CANCELLED,
                ]),
            ],
            'vehicle_id' => ['nullable', 'string', 'exists:vehicles,id'],
            'pickup_from' => ['nullable', 'date_format:Y-m-d'],
            'pickup_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:pickup_from'],
        ]);

        $statusCounts = Booking::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $bookingsQuery = Booking::query()->with(['customer', 'vehicle']);
        $search = trim($validated['search'] ?? '');
        $searchCustomerPhone = $request->user()->can('review', Booking::class);

        if ($search !== '') {
            $searchPattern = "%{$search}%";
            $durationSearch = preg_replace('/\s+days?$/i', '', $search) ?: $search;
            $totalSearch = str_replace(['₱', ',', ' '], '', $search);

            if (str_contains($totalSearch, '.')) {
                $totalSearch = rtrim(rtrim($totalSearch, '0'), '.');
            }

            $dateSearch = $this->parseSearchDate($search);
            $monthDaySearch = $this->parseSearchMonthDay($search);
            $monthSearch = $this->parseSearchMonth($search);
            $timeSearches = $this->parseSearchTime($search);

            $bookingsQuery->where(function (Builder $query) use (

                $searchPattern,
                $durationSearch,
                $totalSearch,
                $dateSearch,
                $monthDaySearch,
                $monthSearch,
                $timeSearches,
                $searchCustomerPhone,
            ): void {
                $query
                    ->where('booking_reference', 'like', $searchPattern)
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($searchPattern, $searchCustomerPhone): void {
                        $customerQuery->where('full_name', 'like', $searchPattern);

                        if ($searchCustomerPhone) {
                            $customerQuery->orWhere('mobile_number', 'like', $searchPattern);
                        }
                    })
                    ->orWhereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery
                        ->where('name', 'like', $searchPattern)
                        ->orWhere('category', 'like', $searchPattern))
                    ->orWhere('pickup_datetime', 'like', $searchPattern)
                    ->orWhere('return_datetime', 'like', $searchPattern)
                    ->orWhere('rental_duration_days', 'like', "%{$durationSearch}%")
                    ->orWhere('estimated_total', 'like', "%{$totalSearch}%")
                    ->orWhere('status', 'like', $searchPattern)
                    ->orWhere('created_at', 'like', $searchPattern);

                if ($dateSearch !== null) {
                    $query
                        ->orWhereDate('pickup_datetime', $dateSearch)
                        ->orWhereDate('return_datetime', $dateSearch)
                        ->orWhereDate('created_at', $dateSearch);
                }

                if ($monthDaySearch !== null) {
                    $month = $monthDaySearch['month'];
                    $day = $monthDaySearch['day'];
                    $query->orWhere(function (Builder $dateQuery) use ($month, $day): void {
                        $dateQuery
                            ->where(fn (Builder $fieldQuery) => $fieldQuery
                                ->whereMonth('pickup_datetime', $month)
                                ->whereDay('pickup_datetime', $day))
                            ->orWhere(fn (Builder $fieldQuery) => $fieldQuery
                                ->whereMonth('return_datetime', $month)
                                ->whereDay('return_datetime', $day))
                            ->orWhere(fn (Builder $fieldQuery) => $fieldQuery
                                ->whereMonth('created_at', $month)
                                ->whereDay('created_at', $day));
                    });
                }

                if ($monthSearch !== null) {
                    $query->orWhere(function (Builder $dateQuery) use ($monthSearch): void {
                        $dateQuery
                            ->whereMonth('pickup_datetime', $monthSearch)
                            ->orWhereMonth('return_datetime', $monthSearch)
                            ->orWhereMonth('created_at', $monthSearch);
                    });
                }

                foreach ($timeSearches ?? [] as $timeSearch) {
                    $timePattern = "%{$timeSearch}%";
                    $query
                        ->orWhere('pickup_datetime', 'like', $timePattern)
                        ->orWhere('return_datetime', 'like', $timePattern)
                        ->orWhere('created_at', 'like', $timePattern);
                }
            });
        }

        $bookingsQuery
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['vehicle_id'] ?? null, fn (Builder $query, string $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($validated['pickup_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('pickup_datetime', '>=', $date))
            ->when($validated['pickup_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('pickup_datetime', '<=', $date));

        return view('admin.bookings.index', [
            'bookings' => $bookingsQuery
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'metrics' => [
                'total' => $statusCounts->sum(),
                'pending' => $statusCounts->get(Booking::STATUS_PENDING, 0),
                'approved' => $statusCounts->get(Booking::STATUS_APPROVED, 0),
                'ongoing' => $statusCounts->get(Booking::STATUS_ONGOING, 0),
                'completed' => $statusCounts->get(Booking::STATUS_COMPLETED, 0),
            ],
            'filters' => $validated,
        ]);
    }

    private function parseSearchDate(string $search): ?string
    {
        foreach (['Y-m-d', 'M d, Y'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                $date = Carbon::createFromFormat($format, $search);

                if ($date !== false) {
                    return $date->toDateString();
                }
            }
        }

        return null;
    }

    private function parseSearchMonthDay(string $search): ?array
    {
        foreach (['M d', 'M j'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                $date = Carbon::createFromFormat($format, $search);

                if ($date !== false) {
                    return ['month' => $date->month, 'day' => $date->day];
                }
            }
        }

        return null;
    }

    private function parseSearchMonth(string $search): ?int
    {
        return [
            'jan' => 1,
            'january' => 1,
            'feb' => 2,
            'february' => 2,
            'mar' => 3,
            'march' => 3,
            'apr' => 4,
            'april' => 4,
            'may' => 5,
            'jun' => 6,
            'june' => 6,
            'jul' => 7,
            'july' => 7,
            'aug' => 8,
            'august' => 8,
            'sep' => 9,
            'september' => 9,
            'oct' => 10,
            'october' => 10,
            'nov' => 11,
            'november' => 11,
            'dec' => 12,
            'december' => 12,
        ][strtolower($search)] ?? null;
    }

    private function parseSearchTime(string $search): ?array
    {
        foreach (['g:i A', 'h:i A'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                $time = Carbon::createFromFormat($format, $search);

                if ($time !== false) {
                    return [$time->format('H:i')];
                }
            }
        }

        foreach (['G:i', 'H:i', 'H:i:s'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                $time = Carbon::createFromFormat($format, $search);

                if ($time !== false) {
                    $times = [$time->format('H:i')];

                    if ($time->hour > 0 && $time->hour < 12) {
                        $times[] = $time->copy()->addHours(12)->format('H:i');
                    } elseif ($time->hour === 12) {
                        $times[] = $time->copy()->subHours(12)->format('H:i');
                    }

                    return $times;
                }
            }
        }

        return null;
    }

    public function show(Booking $booking)
    {
        $this->authorize('review', Booking::class);

        $booking->load([
            'customer.user',
            'vehicle',
            'auditLogs' => fn ($query) => $query
                ->with('user')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        $allowedTransitions = array_values(array_diff(
            $booking->getStatusTransitions()[$booking->status] ?? [],
            [Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED],
        ));

        if ($booking->status === Booking::STATUS_ONGOING) {
            $allowedTransitions = array_values(array_diff($allowedTransitions, [Booking::STATUS_CANCELLED]));
        }

        return view('admin.bookings.show', [
            'booking' => $booking,
            'allowedTransitions' => $allowedTransitions,
        ]);
    }

    public function document(Booking $booking, string $document)
    {
        $this->authorize('review', Booking::class);

        $path = match ($document) {
            'license-front' => $booking->license_front_path,
            'license-back' => $booking->license_back_path,
            'license-selfie' => $booking->license_selfie_path,
            default => null,
        };

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function updateStatus(
        UpdateBookingStatusRequest $request,
        Booking $booking,
        BookingStatusTransitionService $transitions,
    ) {
        $this->authorize('review', Booking::class);

        $validated = $request->validated();
        $status = $validated['status'];
        $reason = trim((string) ($validated['admin_remarks'] ?? ''));

        if (in_array($status, [Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Use the Rentals page to check vehicles out or in.'],
            ]);
        }

        $booking = $transitions->transition($booking, $status, $request->user(), $reason);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking status updated successfully.',
                'booking' => $booking->fresh()->load(['customer', 'vehicle']),
            ]);
        }

        return redirect()->back()->with('status', 'Reservation status updated successfully.');
    }
}
