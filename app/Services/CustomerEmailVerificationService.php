<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyCustomerEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerEmailVerificationService
{
    private const CODE_LIFETIME_MINUTES = 5;

    private const MAX_CODE_ATTEMPTS = 5;

    public function sendCode(User $user): void
    {
        if (! $user->isCustomer() || $user->hasVerifiedEmail()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $issued = DB::transaction(function () use ($user, $code): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $lockedUser->isCustomer() || $lockedUser->hasVerifiedEmail()) {
                return false;
            }

            $lockedUser->forceFill([
                'email_verification_code_hash' => Hash::make($code),
                'email_verification_code_expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
                'email_verification_code_attempts' => 0,
            ])->save();

            return true;
        });

        if ($issued) {
            app(SafeMailDeliveryService::class)->notify(
                User::query()->findOrFail($user->id),
                new VerifyCustomerEmail($code),
            );
        }
    }

    public function verifyCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->hasVerifiedEmail()) {
                return true;
            }

            $codeHash = $lockedUser->email_verification_code_hash;
            $expiresAt = $lockedUser->email_verification_code_expires_at;
            $attempts = (int) $lockedUser->email_verification_code_attempts;

            if (
                ! is_string($codeHash)
                || $codeHash === ''
                || ! $expiresAt
                || $expiresAt->isPast()
                || $attempts >= self::MAX_CODE_ATTEMPTS
            ) {
                $this->clearCode($lockedUser);

                return false;
            }

            if (! Hash::check($code, $codeHash)) {
                $attempts++;

                if ($attempts >= self::MAX_CODE_ATTEMPTS) {
                    $this->clearCode($lockedUser);
                } else {
                    $lockedUser->forceFill([
                        'email_verification_code_attempts' => $attempts,
                    ])->save();
                }

                return false;
            }

            $this->clearCode($lockedUser);

            if (! $lockedUser->markEmailAsVerified()) {
                return false;
            }

            event(new Verified($lockedUser));

            return true;
        });
    }

    private function clearCode(User $user): void
    {
        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_code_attempts' => 0,
        ])->save();
    }
}
