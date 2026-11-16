@extends('layouts.admin')

@section('title', 'Messages | FleetGuard')
@section('admin-page-icon', 'envelope-paper')
@section('admin-breadcrumb', 'Messages')
@section('admin-page-title', 'Messages')
@section('admin-page-description', 'Customer messages and enquiries')

@section('admin-content')
    @php
        $statusOptions = [
            'new' => ['New', 'envelope-exclamation'],
            'read' => ['Read', 'envelope-open'],
            'replied' => ['Replied', 'reply-fill'],
            'archived' => ['Archived', 'archive'],
        ];
        $subjectOptions = [
            'General question',
            'Booking question',
            'Vehicle question',
            'Feedback',
            'Other',
        ];
        $subjectIcons = [
            'General question' => 'chat-left-text',
            'Booking question' => 'calendar-check',
            'Vehicle question' => 'car-front',
            'Feedback' => 'chat-square-heart',
            'Other' => 'three-dots',
        ];
        $activeFilters = array_filter([
            'search' => $search,
            'status' => $statusFilter,
            'subject' => $subjectFilter,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <main class="admin-messages" id="main-content">
        @if (session('success'))
            <div class="admin-messages-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <header class="admin-messages-header">
            <div>
                <p class="admin-messages-breadcrumb"><span>FleetGuard</span><i class="bi bi-chevron-right" aria-hidden="true"></i> Messages</p>
                <h2 class="admin-messages-title">Messages inbox</h2>
                <p class="admin-messages-subtitle">A clear view of customer questions, booking enquiries, and follow-ups.</p>
            </div>
            <p class="admin-messages-updated" title="{{ now()->format('M j, Y g:i:s A') }}">
                <i class="bi bi-clock" aria-hidden="true"></i>
                Last updated {{ now()->diffForHumans() }}
            </p>
        </header>

        <section class="admin-message-kpis" aria-label="Message totals">
            @foreach (['total' => ['Total', 'inbox', 'All messages', null], 'new' => ['New', 'envelope-exclamation', 'Awaiting review', 'new'], 'read' => ['Read', 'envelope-open', 'Reviewed', 'read'], 'replied' => ['Replied', 'reply', 'Responses sent', 'replied'], 'archived' => ['Archived', 'archive', 'Filed away', 'archived']] as $key => [$label, $icon, $caption, $filter])
                <a class="admin-message-kpi {{ $key === 'new' && ($stats[$key] ?? 0) > 0 ? 'admin-message-kpi--new' : '' }}" href="{{ $filter ? route('admin.messages.index', ['status' => $filter]) : route('admin.messages.index') }}">
                    <span class="admin-message-kpi-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                    <span class="admin-message-kpi-copy">
                        <span class="admin-message-kpi-label">{{ $label }}</span>
                        <strong>{{ $stats[$key] ?? 0 }}</strong>
                        <small>{{ $caption }}</small>
                    </span>
                </a>
            @endforeach
        </section>

        <section class="admin-message-panel" aria-labelledby="message-filters-title">
            <div class="admin-message-panel-heading">
                <div>
                    <p class="admin-message-eyebrow">Inbox controls</p>
                    <h2 id="message-filters-title">Find a message</h2>
                </div>
            </div>
            <form method="GET" action="{{ route('admin.messages.index') }}" class="admin-message-filters">
                <label class="admin-message-search-field">
                    <span>Search messages</span>
                    <span class="admin-message-search-control">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" name="search" value="{{ $search }}" placeholder="Name, email, reference or subject" aria-label="Search messages">
                    </span>
                </label>
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => [$label])
                            <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Subject</span>
                    <select name="subject">
                        <option value="">All subjects</option>
                        @foreach ($subjectOptions as $subject)
                            <option value="{{ $subject }}" @selected($subjectFilter === $subject)>{{ $subject }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="admin-message-apply" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Apply filters</button>
            </form>

            @if ($activeFilters)
                <div class="admin-message-filter-chips" aria-label="Active filters">
                    @foreach ($activeFilters as $key => $value)
                        <a class="admin-message-filter-chip" href="{{ route('admin.messages.index', request()->except($key, 'page')) }}">
                            <span>{{ ucfirst($key) }}: {{ $key === 'status' ? ucfirst($value) : $value }}</span>
                            <i class="bi bi-x" aria-hidden="true"></i>
                            <span class="visually-hidden">Remove {{ $key }} filter</span>
                        </a>
                    @endforeach
                    <a class="admin-message-filter-chip" href="{{ route('admin.messages.index') }}"><i class="bi bi-x-circle" aria-hidden="true"></i> Clear all</a>
                </div>
            @endif
        </section>

        <section class="admin-message-panel" aria-labelledby="message-results-title">
            <div class="admin-message-results-toolbar">
                <div>
                    <p class="admin-message-eyebrow">Customer communication</p>
                    <div class="admin-message-panel-heading admin-message-panel-heading--results">
                        <h2 id="message-results-title">All messages</h2>
                        <p class="admin-message-result-count">{{ $messages->total() }} {{ \Illuminate\Support\Str::plural('message', $messages->total()) }}</p>
                    </div>
                </div>
                @if ($messages->isNotEmpty())
                    <div class="admin-message-view-toggle" role="group" aria-label="Message view">
                        <button type="button" data-message-view-toggle="table" aria-label="Table view" title="Table view"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                        <button type="button" data-message-view-toggle="cards" aria-label="Card view" title="Card view"><i class="bi bi-grid" aria-hidden="true"></i></button>
                    </div>
                @endif
            </div>

            @if ($messages->isEmpty())
                <div class="admin-message-empty">
                    <span class="admin-message-empty-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span>
                    @if ($activeFilters)
                        <strong>No messages found</strong>
                        <p>There are no messages matching these filters. Clear the filters to see the full inbox.</p>
                        <a href="{{ route('admin.messages.index') }}">Clear filters</a>
                    @else
                        <strong>Your inbox is clear</strong>
                        <p>Messages submitted through the public contact form will appear here.</p>
                    @endif
                </div>
            @else
                <div class="admin-message-table-view" data-message-view-panel="table">
                    <div class="admin-message-table-wrap">
                        <table class="admin-message-table">
                            <thead>
                                <tr>
                                    <th scope="col">Sender</th>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Message preview</th>
                                    <th scope="col">Received</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messages as $message)
                                    @php
                                        $nameParts = preg_split('/\s+/', trim($message->name), -1, PREG_SPLIT_NO_EMPTY);
                                        $initials = mb_strtoupper(mb_substr($nameParts[0] ?? '?', 0, 1).(count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
                                        $statusPresentation = $statusOptions[$message->status] ?? [ucfirst($message->status), 'envelope'];
                                    @endphp
                                    <tr class="{{ $message->status === 'new' ? 'is-unread' : '' }}">
                                        <td>
                                            <div class="admin-message-sender">
                                                <span class="admin-message-avatar" aria-hidden="true">{{ $initials }}</span>
                                                <span class="admin-message-sender-copy">
                                                    <strong>{{ $message->name }}</strong>
                                                    <small>{{ $message->email }}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td><a class="admin-message-reference" href="{{ route('admin.messages.show', $message) }}">{{ $message->reference }}</a></td>
                                        <td><a class="admin-message-subject" href="{{ route('admin.messages.show', $message) }}"><i class="bi bi-{{ $subjectIcons[$message->subject] ?? 'chat-left-text' }}" aria-hidden="true"></i><span>{{ $message->subject }}</span></a></td>
                                        <td><span class="admin-message-preview" title="{{ $message->message }}">{{ \Illuminate\Support\Str::limit($message->message, 120) }}</span></td>
                                        <td><time class="admin-message-time" datetime="{{ $message->created_at?->toIso8601String() }}" title="{{ $message->created_at?->format('M j, Y g:i:s A') }}">{{ $message->created_at?->diffForHumans() }}</time></td>
                                        <td><span class="admin-message-status admin-message-status--{{ $message->status }}"><i class="bi bi-{{ $statusPresentation[1] }}" aria-hidden="true"></i>{{ $statusPresentation[0] }}</span></td>
                                        <td>
                                            <div class="admin-message-actions">
                                                <a class="admin-message-action" href="{{ route('admin.messages.show', $message) }}" aria-label="View message {{ $message->reference }}" title="View message"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                                @if ($message->status !== 'archived')
                                                    <form method="POST" action="{{ route('admin.messages.status', $message) }}" data-admin-disable-submit onsubmit="return confirm('Archive this message?')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="admin-message-action admin-message-action--archive" type="submit" name="status" value="archived" aria-label="Archive message {{ $message->reference }}" title="Archive message"><i class="bi bi-archive" aria-hidden="true"></i></button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-message-card-view admin-message-card-list" data-message-view-panel="cards" hidden>
                    @foreach ($messages as $message)
                        @php
                            $nameParts = preg_split('/\s+/', trim($message->name), -1, PREG_SPLIT_NO_EMPTY);
                            $initials = mb_strtoupper(mb_substr($nameParts[0] ?? '?', 0, 1).(count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
                            $statusPresentation = $statusOptions[$message->status] ?? [ucfirst($message->status), 'envelope'];
                        @endphp
                        <article class="admin-message-card {{ $message->status === 'new' ? 'is-unread' : '' }}">
                            <div class="admin-message-card-heading">
                                <div class="admin-message-sender">
                                    <span class="admin-message-avatar" aria-hidden="true">{{ $initials }}</span>
                                    <span class="admin-message-card-copy">
                                        <strong>{{ $message->name }}</strong>
                                        <small>{{ $message->email }}</small>
                                    </span>
                                </div>
                                <span class="admin-message-status admin-message-status--{{ $message->status }}"><i class="bi bi-{{ $statusPresentation[1] }}" aria-hidden="true"></i>{{ $statusPresentation[0] }}</span>
                            </div>
                            <p class="admin-message-card-subject"><a class="admin-message-subject" href="{{ route('admin.messages.show', $message) }}"><i class="bi bi-{{ $subjectIcons[$message->subject] ?? 'chat-left-text' }}" aria-hidden="true"></i><span>{{ $message->subject }}</span></a></p>
                            <p class="admin-message-preview" title="{{ $message->message }}">{{ \Illuminate\Support\Str::limit($message->message, 150) }}</p>
                            <div class="admin-message-card-meta">
                                <span class="admin-message-reference">{{ $message->reference }}</span>
                                <time class="admin-message-time" datetime="{{ $message->created_at?->toIso8601String() }}" title="{{ $message->created_at?->format('M j, Y g:i:s A') }}">{{ $message->created_at?->diffForHumans() }}</time>
                            </div>
                            <div class="admin-message-actions">
                                <a class="admin-message-action" href="{{ route('admin.messages.show', $message) }}" aria-label="View message {{ $message->reference }}" title="View message"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                @if ($message->status !== 'archived')
                                    <form method="POST" action="{{ route('admin.messages.status', $message) }}" data-admin-disable-submit onsubmit="return confirm('Archive this message?')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="admin-message-action admin-message-action--archive" type="submit" name="status" value="archived" aria-label="Archive message {{ $message->reference }}" title="Archive message"><i class="bi bi-archive" aria-hidden="true"></i></button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($messages->hasPages())
            <div class="admin-message-pagination">{{ $messages->links() }}</div>
        @endif
    </div>
@endsection
