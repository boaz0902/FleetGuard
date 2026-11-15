<?php

namespace App\Services;

use App\Exceptions\CustomerLinkConflict;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function register(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $email = $this->normalizedEmail($attributes['email']);

            if (User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This email address is already registered.'],
                ]);
            }

            $user = User::query()->create([
                'name' => trim($attributes['name']),
                'email' => $email,
                'password' => $attributes['password'],
            ]);
            $user->forceFill(['role' => 'customer'])->save();

            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'customer_account_registered',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'changes' => [
                    'user_id' => $user->id,
                    'role' => 'customer',
                ],
                'created_at' => now(),
            ]);

            return $user;
        });
    }

    public function linkExistingCustomerAfterVerification(User $user): void
    {
        if (! $user->isCustomer() || ! $user->hasVerifiedEmail()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $matches = $this->matchingCustomers($user->email);

            if ($matches->isEmpty()) {
                return;
            }

            $linkedCustomers = Customer::query()
                ->where('user_id', $user->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $linkedIds = $linkedCustomers->modelKeys();
            $matchingIds = $matches->modelKeys();
            $foreignLink = $matches->first(
                fn (Customer $customer): bool => $customer->user_id !== null
                    && (int) $customer->user_id !== (int) $user->id,
            );

            if ($foreignLink || $linkedCustomers->count() > 1) {
                $this->writeLinkConflict(
                    $user,
                    array_values(array_unique([...$matchingIds, ...$linkedIds])),
                    $foreignLink ? 'email_match_linked_to_another_account' : 'account_has_multiple_customer_records',
                );

                return;
            }

            if ($linkedCustomers->isNotEmpty()) {
                $linked = $linkedCustomers->first();

                if ($this->normalizedEmail($linked->email) !== $this->normalizedEmail($user->email)) {
                    $this->writeLinkConflict(
                        $user,
                        array_values(array_unique([...$matchingIds, ...$linkedIds])),
                        'account_already_linked_to_a_different_customer',
                    );

                    return;
                }

                $duplicates = array_values(array_diff($matchingIds, [$linked->id]));

                if ($duplicates !== []) {
                    $this->writeLinkConflict($user, $duplicates, 'duplicate_customer_records_already_linked');
                }

                return;
            }

            if ($matches->count() > 1) {
                $this->writeLinkConflict($user, $matchingIds, 'multiple_customer_records_match_email');

                return;
            }

            $oldest = $matches->first();
            $oldest->forceFill(['user_id' => $user->id])->save();

            $this->writeAccountLinked(
                $user,
                $oldest,
                array_values(array_diff($matchingIds, [$oldest->id])),
            );
        });
    }

    public function resolveBookingCustomer(?User $user, array $attributes, string $email): Customer
    {
        $matches = $this->matchingCustomers($email);

        if ($matches->contains(fn (Customer $customer): bool => $customer->status === Customer::STATUS_BLOCKED)) {
            throw ValidationException::withMessages([
                'email' => ['This booking request could not be accepted. Please contact FleetGuard support.'],
            ]);
        }

        if ($matches->count() > 1) {
            throw new CustomerLinkConflict($matches->modelKeys(), 'multiple_customer_records_match_email');
        }

        if (! $user?->isCustomer()) {
            return $matches->first() ?? $this->createCustomer($attributes, $email);
        }

        $linkedCustomers = Customer::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $matchingIds = $matches->modelKeys();
        $linkedIds = $linkedCustomers->modelKeys();
        $foreignLink = $matches->first(
            fn (Customer $customer): bool => $customer->user_id !== null
                && (int) $customer->user_id !== (int) $user->id,
        );

        if ($foreignLink || $linkedCustomers->count() > 1) {
            throw new CustomerLinkConflict(
                array_values(array_unique([...$matchingIds, ...$linkedIds])),
                $foreignLink ? 'email_match_linked_to_another_account' : 'account_has_multiple_customer_records',
            );
        }

        if ($linkedCustomers->isNotEmpty()) {
            $linked = $linkedCustomers->first();

            if ($this->normalizedEmail($linked->email) !== $this->normalizedEmail($email)) {
                throw new CustomerLinkConflict(
                    array_values(array_unique([...$matchingIds, ...$linkedIds])),
                    'account_already_linked_to_a_different_customer',
                );
            }

            return $linked;
        }

        $customer = $matches->first();

        if (! $customer) {
            $customer = $this->createCustomer(
                $attributes,
                $email,
                $user->hasVerifiedEmail() ? $user : null,
            );

            if ($customer->user_id !== null) {
                $this->writeAccountLinked($user, $customer, []);
            }

            return $customer;
        }

        if ($user->hasVerifiedEmail()) {
            $customer->forceFill(['user_id' => $user->id])->save();

            $this->writeAccountLinked(
                $user,
                $customer,
                array_values(array_diff($matchingIds, [$customer->id])),
            );
        }

        return $customer;
    }

    public function recordBookingConflict(User $user, CustomerLinkConflict $conflict): void
    {
        DB::transaction(function () use ($user, $conflict): void {
            $this->writeLinkConflict($user, $conflict->customerIds, $conflict->reason);
        });
    }

    private function matchingCustomers(string $email): Collection
    {
        return Customer::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalizedEmail($email)])
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function createCustomer(array $attributes, string $email, ?User $user = null): Customer
    {
        return Customer::query()->create([
            'user_id' => $user?->id,
            'full_name' => trim($attributes['full_name']),
            'email' => $this->normalizedEmail($email),
            'mobile_number' => trim($attributes['mobile_number']),
        ]);
    }

    private function writeAccountLinked(User $user, Customer $customer, array $duplicateIds): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'customer_account_linked',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'changes' => [
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'duplicate_customer_ids' => $duplicateIds,
            ],
            'created_at' => now(),
        ]);
    }

    private function writeLinkConflict(User $user, array $customerIds, string $reason): void
    {
        $customerIds = array_values(array_unique(array_filter($customerIds)));
        $subjectId = $customerIds[0] ?? null;

        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'customer_link_conflict',
            'subject_type' => Customer::class,
            'subject_id' => $subjectId,
            'changes' => [
                'user_id' => $user->id,
                'customer_id' => $subjectId,
                'customer_ids' => $customerIds,
                'reason' => $reason,
            ],
            'created_at' => now(),
        ]);
    }

    private function normalizedEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
