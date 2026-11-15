<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerEmailVerificationRequest;
use App\Models\User;
use App\Services\CustomerBookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function verificationNotice(): View
    {
        return view('customer-auth.verify-email');
    }

    public function verify(CustomerEmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('account')->with('status', 'Your email address has been verified.');
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
