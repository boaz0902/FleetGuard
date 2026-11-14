<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\CustomerManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerManagementService $customers): View
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'activity' => ['nullable', Rule::in(['none', 'pending', 'upcoming', 'ongoing', 'completed'])],
            'joined' => ['nullable', Rule::in(['month'])],
            'sort' => ['nullable', Rule::in([
                'full_name',
                'email',
                'total_bookings',
                'latest_booking_at',
                'created_at',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $now = Carbon::now('Asia/Manila');
        $showContact = $request->user()->isAdmin();

        return view('admin.customers.index', [
            'customers' => $customers->directory($filters, $now, $showContact),
            'metrics' => $customers->metrics($now),
            'filters' => $filters,
            'now' => $now,
            'canEdit' => $showContact,
        ]);
    }

    public function show(
        Request $request,
        Customer $customer,
        CustomerManagementService $customers,
    ): View {
        $this->authorize('view', $customer);

        $user = $request->user();
        $includeAudit = $user->can('viewAny', AuditLog::class);
        $canReviewBookings = $user->can('review', Booking::class);
        $activeTab = $request->query('tab', 'overview');

        if (! in_array($activeTab, ['overview', 'bookings', 'notes', 'activity'], true)) {
            $activeTab = 'overview';
        }

        return view('admin.customers.show', [
            'customer' => $customer,
            'details' => $customers->details(
                $customer,
                Carbon::now('Asia/Manila'),
                $includeAudit,
                $canReviewBookings,
            ),
            'displayEmail' => $customers->emailForDisplay($customer, $user->isAdmin()),
            'displayPhone' => $customers->phoneForDisplay($customer, $user->isAdmin()),
            'canEdit' => $user->can('update', $customer),
            'canReviewBookings' => $canReviewBookings,
            'canViewAudit' => $includeAudit,
            'activeTab' => $activeTab,
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        CustomerManagementService $customers,
    ) {
        $updated = $customers->updateProfile($customer, $request->validated(), $request->user());

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', $updated ? 'Customer profile updated.' : 'No changes were made to the customer record.');
    }
}
