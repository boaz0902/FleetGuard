@extends('layouts.admin')

@section('admin-page-icon', 'envelope-paper')
@section('admin-breadcrumb', 'Messages')
@section('admin-page-title', 'Message details')

@section('admin-content')
    <div class="admin-content-shell admin-dashboard-shell">
        <div class="admin-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem;">
            <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:center;">
                <div>
                    <p class="admin-breadcrumb"><span>Reference</span> <strong>{{ $message->reference }}</strong></p>
                    <h2 style="margin: .5rem 0;">{{ $message->subject }}</h2>
                </div>
                <div>
                    <span class="status-badge status-{{ $message->status }}">{{ ucfirst($message->status) }}</span>
                </div>
            </div>
        </div>

        <div class="admin-card" style="padding: 1rem 1.25rem;">
            <div style="display:grid; grid-template-columns: minmax(0, 1.4fr) minmax(260px, .8fr); gap:1rem;">
                <div>
                    <h3>Message</h3>
                    <p style="white-space: pre-wrap;">{{ $message->message }}</p>
                </div>
                <div>
                    <h3>Sender</h3>
                    <ul style="list-style:none; padding:0; margin:0; line-height:1.9;">
                        <li><strong>Name:</strong> {{ $message->name }}</li>
                        <li><strong>Email:</strong> <a href="mailto:{{ $message->email }}">{{ $message->email }}</a></li>
                        @if ($message->phone)
                            <li><strong>Phone:</strong> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $message->phone) }}">{{ $message->phone }}</a></li>
                        @endif
                        <li><strong>Received:</strong> {{ $message->created_at?->format('M j, Y h:i A') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="admin-card" style="margin-top:1rem; padding:1rem 1.25rem;">
            <form method="POST" action="{{ route('admin.messages.status', $message) }}">
                @csrf
                @method('PATCH')
                <div style="display:flex; flex-wrap:wrap; gap: .5rem; align-items:center;">
                    <button type="submit" name="status" value="read" class="button button-secondary">Mark as read</button>
                    <button type="submit" name="status" value="replied" class="button button-primary">Mark replied</button>
                    <button type="submit" name="status" value="archived" class="button button-light">Archive</button>
                </div>
            </form>
        </div>
    </div>
@endsection
