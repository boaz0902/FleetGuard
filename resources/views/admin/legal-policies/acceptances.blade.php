@extends('layouts.admin')

@section('title', 'Policy Acceptances | FleetGuard')
@section('admin-page-title', 'Policy Acceptances')
@section('admin-page-icon', 'people')
@section('admin-breadcrumb', 'Legal & Policies / Acceptances')
@section('admin-page-description', 'Read-only booking acceptance records with the exact policy version and timestamp.')

@section('admin-content')
    <main class="admin-legal" id="main-content">
        <div class="admin-legal-toolbar"><a class="admin-legal-back" href="{{ route('admin.legal-policies.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Policy library</a></div>
        <section class="admin-legal-history">
            <div class="admin-legal-history-heading"><div><p class="admin-legal-eyebrow">Immutable record</p><h2>Customer acceptances</h2></div><span>{{ number_format($acceptances->total()) }} records</span></div>
            @if ($acceptances->isEmpty())
                <div class="admin-legal-empty"><i class="bi bi-file-earmark-check" aria-hidden="true"></i><h3>No acceptance records</h3><p>Records will appear when booking requests are submitted.</p></div>
            @else
                <div class="admin-legal-table-wrap">
                    <table class="admin-legal-table">
                        <thead><tr><th>Customer</th><th>Booking</th><th>Policy version</th><th>Status</th><th>Accepted at (Philippine time)</th></tr></thead>
                        <tbody>
                            @foreach ($acceptances as $acceptance)
                                <tr>
                                    <td>{{ $acceptance->customer->full_name }}</td>
                                    <td>{{ $acceptance->booking->booking_reference }}</td>
                                    <td>{{ $acceptance->policy->title }} · {{ $acceptance->version->version }}</td>
                                    <td><span class="admin-legal-status is-published">{{ ucfirst($acceptance->status) }}</span></td>
                                    <td><time datetime="{{ $acceptance->accepted_at->toIso8601String() }}">{{ $acceptance->accepted_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</time></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($acceptances->hasPages())<div class="admin-legal-pagination">{{ $acceptances->links() }}</div>@endif
            @endif
        </section>
    </main>
@endsection
