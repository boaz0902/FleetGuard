<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, VehicleAvailabilityService $availability)
    {
        $validated = $request->validated();
        $pickupDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$validated['pickup_date']} {$validated['pickup_time']}", 'Asia/Manila');
        $returnDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$validated['return_date']} {$validated['return_time']}", 'Asia/Manila');
        $email = strtolower(trim($validated['email']));
        $user = $request->user();

        if ($user?->isCustomer() && strtolower($user->email) !== $email) {
            throw ValidationException::withMessages([
                'email' => ['Use the email address associated with your customer account.'],
            ]);
        }

        $booking = DB::transaction(function () use ($validated, $pickupDateTime, $returnDateTime, $email, $user, $availability) {
            $vehicle = Vehicle::query()
                ->whereKey($validated['vehicle_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $availability->isAvailable($vehicle, $pickupDateTime, $returnDateTime)) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['The selected vehicle is not available for the requested dates and times.'],
                ]);
            }

            $customer = Customer::query()->where('email', $email)->first();

            if (! $customer) {
                $customer = Customer::query()->create([
                    'user_id' => $user?->isCustomer() ? $user->id : null,
                    'full_name' => trim($validated['full_name']),
                    'email' => $email,
                    'mobile_number' => trim($validated['mobile_number']),
                ]);
            } elseif ($user?->isCustomer()) {
                if ($customer->user_id !== null && $customer->user_id !== $user->id) {
                    throw ValidationException::withMessages([
                        'email' => ['This customer record is not linked to your account.'],
                    ]);
                }

                if ($customer->user_id === null) {
                    $customer->forceFill(['user_id' => $user->id])->save();
                }
            }

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
                'status' => Booking::STATUS_PENDING,
            ]);

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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking request received and is awaiting admin approval.',
                'booking' => $booking->only([
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
                ]),
            ], 201);
        }

        return redirect()->back()->with('status', 'Booking request received and is awaiting admin approval.');
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);

        if ($request->expectsJson()) {
            return response()->json($booking->load(['customer', 'vehicle']));
        }

        return view('bookings.show', ['booking' => $booking->load(['customer', 'vehicle'])]);
    }
}
