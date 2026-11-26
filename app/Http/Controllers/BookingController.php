<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomerLinkConflict;
use App\Http\Requests\StoreBookingRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\LegalPolicy;
use App\Models\LegalPolicyAcceptance;
use App\Models\Vehicle;
use App\Services\CustomerAccountService;
use App\Services\SafeMailDeliveryService;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BookingController extends Controller
{
    public function store(
        StoreBookingRequest $request,
        VehicleAvailabilityService $availability,
        CustomerAccountService $accounts,
        SafeMailDeliveryService $mail,
    ) {
        $validated = $request->validated();
        $pickupDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$validated['pickup_date']} {$validated['pickup_time']}", 'Asia/Manila');
        $returnDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$validated['return_date']} {$validated['return_time']}", 'Asia/Manila');
        $email = strtolower(trim($validated['email']));
        $user = $request->user();
        $acceptedAt = now();

        if ($user?->isCustomer() && strtolower($user->email) !== $email) {
            throw ValidationException::withMessages([
                'email' => ['Use the email address associated with your customer account.'],
            ]);
        }

        $documentPaths = $this->storeBookingDocuments($request);
        $bookingCreated = false;

        try {
            try {
                $booking = DB::transaction(function () use (
                    $validated,
                    $documentPaths,
                    $pickupDateTime,
                    $returnDateTime,
                    $email,
                    $user,
                    $acceptedAt,
                    $availability,
                    $accounts,
                ) {
                    $vehicle = Vehicle::query()
                        ->whereKey($validated['vehicle_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $availability->isAvailable($vehicle, $pickupDateTime, $returnDateTime)) {
                        throw ValidationException::withMessages([
                            'vehicle_id' => ['The selected vehicle is not available for the requested dates and times.'],
                        ]);
                    }

                    $customer = $accounts->resolveBookingCustomer($user, [
                        'full_name' => trim($validated['full_name']),
                        'mobile_number' => trim($validated['mobile_number']),
                    ], $email);

                    $rentalDurationDays = max(1, (int) ceil($pickupDateTime->diffInSeconds($returnDateTime) / 86400));
                    $booking = Booking::query()->create([
                        'booking_reference' => 'FG-'.strtoupper(Str::random(8)),
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                        'pickup_location' => trim($validated['pickup_location']),
                        'pickup_datetime' => $pickupDateTime,
                        'return_location' => trim($validated['return_location']),
                        'return_datetime' => $returnDateTime,
                        'rental_duration_days' => $rentalDurationDays,
                        'daily_rate_snapshot' => $vehicle->daily_rate,
                        'estimated_total' => $vehicle->daily_rate * $rentalDurationDays,
                        'customer_notes' => trim((string) ($validated['customer_notes'] ?? '')) ?: null,
                        'license_front_path' => $documentPaths['license_front_path'] ?? null,
                        'license_back_path' => $documentPaths['license_back_path'] ?? null,
                        'license_selfie_path' => $documentPaths['license_selfie_path'] ?? null,
                        'status' => Booking::STATUS_PENDING,
                    ]);

                    foreach (LegalPolicy::bookingAgreementSlugs() as $slug) {
                        $policy = LegalPolicy::query()
                            ->where('slug', $slug)
                            ->where('is_published', true)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $version = $policy->currentPublishedVersionAt($acceptedAt);

                        if (! $version || $version->id !== (int) $validated['policy_versions'][$slug]) {
                            throw ValidationException::withMessages([
                                'agreements.'.$slug => ['A required policy has changed. Review the current policy and try again.'],
                            ]);
                        }

                        LegalPolicyAcceptance::query()->create([
                            'customer_id' => $customer->id,
                            'booking_id' => $booking->id,
                            'legal_policy_id' => $policy->id,
                            'legal_policy_version_id' => $version->id,
                            'status' => 'accepted',
                            'accepted_at' => $acceptedAt,
                        ]);
                    }

                    AuditLog::query()->create([
                        'user_id' => Auth::id(),
                        'action' => 'booking_submitted',
                        'subject_type' => Booking::class,
                        'subject_id' => $booking->id,
                        'changes' => [
                            'customer_id' => $customer->id,
                            'vehicle_id' => $vehicle->id,
                            'pickup_datetime' => $pickupDateTime->toDateTimeString(),
                            'return_datetime' => $returnDateTime->toDateTimeString(),
                            'estimated_total' => (string) $booking->estimated_total,
                        ],
                        'created_at' => now(),
                    ]);

                    return $booking;
                });
                $bookingCreated = true;
            } finally {
                if (! $bookingCreated && $documentPaths !== []) {
                    Storage::disk(config('filesystems.booking_documents_disk', 'private'))
                        ->delete(array_values($documentPaths));
                }
            }
        } catch (CustomerLinkConflict $conflict) {
            if ($user?->isCustomer()) {
                $accounts->recordBookingConflict($user, $conflict);
            }

            throw ValidationException::withMessages([
                'email' => ['This booking request could not be accepted. Please contact FleetGuard support.'],
            ]);
        }

        $mail->sendBookingNotification($booking, 'received');
        $mail->sendAdminBookingNotification($booking);

        if ($request->expectsJson()) {
            $bookingDetails = $booking->only([
                'booking_reference',
                'vehicle_id',
                'pickup_location',
                'pickup_datetime',
                'return_location',
                'return_datetime',
                'rental_duration_days',
                'daily_rate_snapshot',
                'estimated_total',
                'status',
            ]);

            if (
                $user?->isCustomer()
                && $user->hasVerifiedEmail()
                && $booking->customer()->where('user_id', $user->id)->exists()
            ) {
                $bookingDetails['details_url'] = route('bookings.show', $booking);
            }

            return response()->json([
                'message' => 'Booking request received and is awaiting admin approval.',
                'booking' => $bookingDetails,
            ], 201);
        }

        return redirect()->back()->with('status', 'Booking request received and is awaiting admin approval.');
    }

    private function storeBookingDocuments(StoreBookingRequest $request): array
    {
        $documentFields = [
            'license_front' => 'license_front_path',
            'license_back' => 'license_back_path',
            'license_selfie' => 'license_selfie_path',
        ];
        $paths = [];
        $storedAllFiles = false;

        try {
            foreach ($documentFields as $input => $attribute) {
                if (! $request->hasFile($input)) {
                    continue;
                }

                $path = $request->file($input)->store(
                    'booking-documents',
                    config('filesystems.booking_documents_disk', 'private'),
                );

                if (! is_string($path)) {
                    throw new RuntimeException('Unable to securely store a booking identity document.');
                }

                $paths[$attribute] = $path;
            }

            $storedAllFiles = true;
        } finally {
            if (! $storedAllFiles && $paths !== []) {
                Storage::disk(config('filesystems.booking_documents_disk', 'private'))
                    ->delete(array_values($paths));
            }
        }

        return $paths;
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $request->user();

        if ($user?->isCustomer() && ! $user->hasVerifiedEmail()) {
            if (! $request->session()->has('url.intended')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()
                ->route('verification.notice')
                ->with('status', 'Please verify your email to continue');
        }

        $this->authorize('view', $booking);

        if ($user->isCustomer()) {
            $booking->load([
                'vehicle',
                'auditLogs' => fn ($query) => $query
                    ->whereIn('action', [
                        'booking_approved',
                        'booking_rejected',
                        'booking_cancelled',
                        'booking_cancelled_by_customer',
                        'rental_checked_out',
                        'booking_marked_ongoing',
                        'rental_checked_in',
                        'booking_marked_completed',
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ]);

            return view('bookings.show', [
                'booking' => $booking,
                'customerView' => true,
                'statusDescription' => $this->customerStatusDescription($booking->status),
                'timeline' => $this->customerTimeline($booking),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($booking->load(['customer', 'vehicle']));
        }

        return view('bookings.show', ['booking' => $booking->load(['customer', 'vehicle'])]);
    }

    private function customerStatusDescription(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PENDING => 'Your request is waiting for FleetGuard to review it.',
            Booking::STATUS_APPROVED => 'Your request is approved. Your vehicle is reserved for the scheduled dates.',
            Booking::STATUS_ONGOING => 'Your rental is currently in progress.',
            Booking::STATUS_COMPLETED => 'Your rental is complete.',
            Booking::STATUS_REJECTED => 'FleetGuard could not approve this request.',
            Booking::STATUS_CANCELLED => 'This booking request has been cancelled.',
            default => 'Your booking status is being reviewed.',
        };
    }

    private function customerTimeline(Booking $booking): array
    {
        $logs = $booking->auditLogs;
        $approved = $logs->firstWhere('action', 'booking_approved');
        $checkedOut = $logs->first(
            fn (AuditLog $log): bool => in_array($log->action, ['rental_checked_out', 'booking_marked_ongoing'], true),
        );
        $checkedIn = $logs->first(
            fn (AuditLog $log): bool => in_array($log->action, ['rental_checked_in', 'booking_marked_completed'], true),
        );
        $cancelled = $logs->first(
            fn (AuditLog $log): bool => in_array($log->action, ['booking_cancelled', 'booking_cancelled_by_customer'], true),
        );
        $rejected = $logs->firstWhere('action', 'booking_rejected');
        $timeline = [[
            'label' => 'Request submitted',
            'description' => 'Your booking request was received.',
            'at' => $booking->created_at,
            'complete' => true,
            'reason' => null,
        ]];

        if ($approved || in_array($booking->status, [Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED], true)) {
            $timeline[] = [
                'label' => 'Approved',
                'description' => 'FleetGuard approved your booking request.',
                'at' => $approved?->created_at,
                'complete' => true,
                'reason' => null,
            ];
        }

        if ($booking->checked_out_at || $checkedOut) {
            $timeline[] = [
                'label' => 'Checked out',
                'description' => 'Your rental began.',
                'at' => $booking->checked_out_at ?? $checkedOut?->created_at,
                'complete' => true,
                'reason' => null,
            ];
        }

        if ($booking->checked_in_at || $checkedIn) {
            $timeline[] = [
                'label' => 'Checked in',
                'description' => 'The vehicle was returned.',
                'at' => $booking->checked_in_at ?? $checkedIn?->created_at,
                'complete' => true,
                'reason' => null,
            ];
        }

        if ($booking->status === Booking::STATUS_COMPLETED) {
            $timeline[] = [
                'label' => 'Completed',
                'description' => 'Your rental is complete.',
                'at' => $booking->checked_in_at ?? $checkedIn?->created_at,
                'complete' => true,
                'reason' => null,
            ];
        } elseif ($booking->status === Booking::STATUS_CANCELLED && $cancelled) {
            $timeline[] = [
                'label' => 'Cancelled',
                'description' => 'This booking request was cancelled.',
                'at' => $cancelled->created_at,
                'complete' => true,
                'reason' => $cancelled->action === 'booking_cancelled_by_customer'
                    ? ($cancelled->changes['reason'] ?? null)
                    : null,
            ];
        } elseif ($booking->status === Booking::STATUS_REJECTED && $rejected) {
            $timeline[] = [
                'label' => 'Not approved',
                'description' => 'FleetGuard could not approve this request.',
                'at' => $rejected->created_at,
                'complete' => true,
                'reason' => null,
            ];
        }

        return $timeline;
    }
}
