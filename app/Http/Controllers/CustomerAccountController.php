<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeCustomerPasswordRequest;
use App\Http\Requests\CustomerEmailVerificationRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerBookingService;
use App\Services\SafeMailDeliveryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function show(Request $request, CustomerBookingService $bookings): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isCustomer(), 403);
        $this->authorize('viewCustomerAccount', $user);

        $initials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return view('customer.account', [
            'user' => $user,
            'initials' => $initials,
            'bookingSummary' => $bookings->summary($user, Carbon::now(config('app.timezone'))),
        ]);
    }

    public function editProfile(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isCustomer(), 403);

        return view('customer.profile', [
            'user' => $user,
            'profilePhone' => $user->phone ?? ($user->hasVerifiedEmail() ? $user->customer?->mobile_number : null),
        ]);
    }

    public function updateProfile(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();

        $changedFields = DB::transaction(function () use ($user, $validated): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $changedFields = [];

            foreach (['name', 'phone'] as $field) {
                if ($lockedUser->{$field} !== $validated[$field]) {
                    $changedFields[] = $field;
                }
            }

            if ($changedFields === []) {
                return [];
            }

            $lockedUser->fill(array_intersect_key($validated, array_flip($changedFields)))->save();

            if (in_array('phone', $changedFields, true) && $lockedUser->hasVerifiedEmail()) {
                $customer = Customer::query()
                    ->where('user_id', $lockedUser->id)
                    ->lockForUpdate()
                    ->first();

                if ($customer) {
                    $customer->mobile_number = $validated['phone'];
                    $customer->save();
                }
            }

            AuditLog::query()->create([
                'user_id' => $lockedUser->id,
                'action' => 'customer_profile_updated',
                'subject_type' => User::class,
                'subject_id' => $lockedUser->id,
                'changes' => [
                    'user_id' => $lockedUser->id,
                    'changed_fields' => $changedFields,
                ],
                'created_at' => now(),
            ]);

            return $changedFields;
        });

        return redirect()
            ->route('account.profile')
            ->with('status', $changedFields === [] ? 'Nothing to update; your profile is already current.' : 'Your profile has been updated.');
    }

    public function changePassword(ChangeCustomerPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $password = $request->validated('password');

        $auditLogId = DB::transaction(function () use ($user, $password): int {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->forceFill(['password' => $password])->save();
            Password::broker()->deleteToken($lockedUser);

            $audit = AuditLog::query()->create([
                'user_id' => $lockedUser->id,
                'action' => 'customer_password_changed',
                'subject_type' => User::class,
                'subject_id' => $lockedUser->id,
                'changes' => ['user_id' => $lockedUser->id],
                'created_at' => now(),
            ]);

            return $audit->id;
        });

        $freshUser = $user->fresh();
        Auth::setUser($freshUser);
        Auth::logoutOtherDevices($password);
        $request->session()->regenerate();

        app(SafeMailDeliveryService::class)->sendPasswordChangedNotification($freshUser, $auditLogId);

        return redirect()
            ->route('account.profile')
            ->with('status', 'Your password has been changed.');
    }

    public function verificationNotice(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isCustomer(), 403);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account');
        }

        return view('customer-auth.verify-email');
    }

    public function verify(CustomerEmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()
            ->intended(route('account'))
            ->with('status', 'Your email address has been verified.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', 'If your email still needs verification, a new link has been sent.');
    }
}
