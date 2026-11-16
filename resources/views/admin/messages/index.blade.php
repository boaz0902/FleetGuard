@extends('layouts.admin')

@section('admin-page-icon', 'inbox')
@section('admin-breadcrumb', 'Messages')
@section('admin-page-title', 'Inbox')
@section('admin-page-description', 'Customer messages and enquiries')

@section('admin-content')
    <div class="admin-content-shell admin-dashboard-shell">
        <div class="admin-metrics-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
            @foreach (['total' => 'Total', 'new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'] as $key => $label)
                <div class="admin-metric-card admin-card">
                    <span class="admin-metric-label">{{ $label }}</span>
                    <strong class="admin-metric-value">{{ $stats[$key] ?? 0 }}</strong>
                </div>
            @endforeach
        </div>

        <div class="admin-card" style="padding: 1rem;">
            <form method="GET" class="admin-filter-row" style="display:flex; flex-wrap:wrap; gap: .75rem; align-items:center;">
                <input type="search" name="search" value="{{ old('search', $search) }}" placeholder="Search by name, email, reference or subject" style="flex:1 1 220px;">
                <select name="status" style="min-width: 150px;">
                    <option value="">All statuses</option>
                    @foreach (['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="button button-primary" type="submit">Filter</button>
            </form>
        </div>

        <div class="admin-card" style="margin-top: 1rem; padding: 0; overflow: hidden;">
            <div class="admin-table-wrap">
                <table class="admin-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Name</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Received</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($messages as $message)
                        <tr>
                            <td><a href="{{ route('admin.messages.show', $message) }}">{{ $message->reference }}</a></td>
                            <td>{{ $message->name }}</td>
                            <td>{{ $message->subject }}</td>
                            <td><span class="status-badge status-{{ $message->status }}">{{ ucfirst($message->status) }}</span></td>
                            <td>{{ $message->created_at?->format('M j, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center; padding:2rem;">No messages match your current filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-pagination" style="margin-top: 1rem;">
            {{ $messages->links() }}
        </div>
    </div>
@endsection
