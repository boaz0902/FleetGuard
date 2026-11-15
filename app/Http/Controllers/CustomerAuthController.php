<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\RegisterCustomerRequest;
use App\Models\User;
use App\Services\CustomerAccountService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $this->redirectAuthenticatedUser($request->user());
        }

        return view('customer-auth.login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        if ($request->user()) {
            return $this->redirectAuthenticatedUser($request->user());
        }

        if (! Auth::attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages([
                'email' => ['These customer sign-in details could not be verified.'],
            ]);
        }

        $user = Auth::user();

        if (! $user instanceof User || ! $user->isCustomer()) {
            Auth::logout();
            $request->session()->regenerate();

            throw ValidationException::withMessages([
                'email' => ['These customer sign-in details could not be verified.'],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function showRegistrationForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $this->redirectAuthenticatedUser($request->user());
        }

        $email = $request->query('email');

        return view('customer-auth.register', [
            'prefillEmail' => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? strtolower(trim($email))
                : null,
        ]);
    }

    public function register(
        RegisterCustomerRequest $request,
        CustomerAccountService $accounts,
    ): RedirectResponse {
        if ($request->user()) {
            return $this->redirectAuthenticatedUser($request->user());
        }

        $user = $accounts->register($request->validated());
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('verification.notice')
            ->with('status', 'Your account is ready. Check your email to verify your address.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function redirectAuthenticatedUser(User $user): RedirectResponse
    {
        if ($user->isCustomer()) {
            return redirect()->route('account');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('home');
    }
}
