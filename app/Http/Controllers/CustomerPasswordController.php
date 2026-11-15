<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestCustomerPasswordResetRequest;
use App\Http\Requests\ResetCustomerPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerPasswordController extends Controller
{
    public function showRequestForm(): View
    {
        return view('customer-auth.forgot-password');
    }

    public function sendResetLink(RequestCustomerPasswordResetRequest $request): RedirectResponse
    {
        $email = $request->validated('email');
        $customers = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->orderBy('id')
            ->get()
            ->filter(fn (User $candidate): bool => $candidate->isCustomer());

        if ($customers->count() === 1) {
            $user = $customers->first();
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with(
            'status',
            'If a customer account exists for that email, a password reset link has been sent.',
        );
    }

    public function showResetForm(Request $request, string $token): View
    {
        $email = $request->query('email', '');

        return view('customer-auth.reset-password', [
            'token' => $token,
            'email' => is_string($email) ? $email : '',
        ]);
    }

    public function reset(ResetCustomerPasswordRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $customers = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$credentials['email']])
            ->orderBy('id')
            ->get()
            ->filter(fn (User $candidate): bool => $candidate->isCustomer());

        if ($customers->count() !== 1) {
            return back()->withErrors([
                'email' => 'This reset link could not be verified or has expired.',
            ]);
        }

        $user = $customers->first();
        $credentials['email'] = $user->email;
        $status = Password::broker()->reset($credentials, function (User $user, string $password): void {
            abort_unless($user->isCustomer(), 403);

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
        }

        return back()->withErrors([
            'email' => 'This reset link could not be verified or has expired.',
        ]);
    }
}
