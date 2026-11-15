@extends('layouts.admin')

@section('title', $message->name.' | Message | FleetGuard')
@section('admin-page-icon', 'envelope-paper')
@section('admin-breadcrumb', 'Messages')
@section('admin-page-title', 'Message details')
@section('admin-page-description', 'Review the customer enquiry and its handling history.')

@section('admin-content')
    @php
        $statusPresentation = [
            'new' => ['New', 'envelope-exclamation'],
            'read' => ['Read', 'envelope-open'],
            'replied' => ['Replied', 'reply-fill'],
            'archived' => ['Archived', 'archive'],
        ][$message->status] ?? [ucfirst($message->status), 'envelope'];
        $nameParts = preg_split('/\s+/', trim($message->name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = mb_strtoupper(mb_substr($nameParts[0] ?? '?', 0, 1).(count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
        preg_match_all('/[\p{L}\p{N}]+/u', $message->message, $wordMatches);
        $messageWordCount = count($wordMatches[0]);
        $previousMessage = \App\Models\ContactMessage::query()
            ->where(fn ($query) => $query->where('created_at', '>', $message->created_at)
                ->orWhere(fn ($query) => $query->where('created_at', $message->created_at)->where('id', '<', $message->id)))
            ->orderBy('created_at')
            ->orderByDesc('id')
            ->first();
        $nextMessage = \App\Models\ContactMessage::query()
            ->where(fn ($query) => $query->where('created_at', '<', $message->created_at)
                ->orWhere(fn ($query) => $query->where('created_at', $message->created_at)->where('id', '>', $message->id)))
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->first();
        $timeline = [
            ['label' => 'Received', 'timestamp' => $message->created_at, 'icon' => 'inbox'],
            ['label' => 'Read', 'timestamp' => $message->read_at, 'icon' => 'envelope-open'],
            ['label' => 'Replied', 'timestamp' => $message->replied_at, 'icon' => 'reply'],
            ['label' => 'Archived', 'timestamp' => $message->archived_at, 'icon' => 'archive'],
        ];
    @endphp

    <main class="admin-message-detail" id="main-content">
        @if (session('success'))
            <div class="admin-messages-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-messages-flash" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <header class="admin-message-detail-header">
            <div class="admin-message-detail-header-main">
                <span class="admin-message-avatar" aria-hidden="true">{{ $initials }}</span>
                <div>
                    <p class="admin-message-detail-breadcrumb"><a href="{{ route('admin.messages.index') }}">Messages</a><i class="bi bi-chevron-right" aria-hidden="true"></i> {{ $message->reference }}</p>
                    <h2 class="admin-message-detail-title">{{ $message->name }}</h2>
                    <div class="admin-message-detail-subline">
                        <span class="admin-message-subject"><i class="bi bi-chat-left-text" aria-hidden="true"></i><span>{{ $message->subject }}</span></span>
                        <span class="admin-message-status admin-message-status--{{ $message->status }}"><i class="bi bi-{{ $statusPresentation[1] }}" aria-hidden="true"></i>{{ $statusPresentation[0] }}</span>
                        <span class="admin-message-detail-reference">{{ $message->reference }}</span>
                    </div>
                </div>
            </div>
            <p class="admin-message-detail-time" title="{{ $message->created_at?->format('M j, Y g:i:s A') }}">
                <i class="bi bi-clock" aria-hidden="true"></i>
                Received {{ $message->created_at?->diffForHumans() }}
            </p>
        </header>

        <div class="admin-message-detail-grid">
            <section class="admin-message-detail-conversation" aria-labelledby="message-content-title">
                <article class="admin-message-detail-card">
                    <div class="admin-message-panel-heading">
                        <div>
                            <p class="admin-message-eyebrow">Customer enquiry</p>
                            <h2 id="message-content-title">Message</h2>
                        </div>
                        <time class="admin-message-detail-time" datetime="{{ $message->created_at?->toIso8601String() }}" title="{{ $message->created_at?->format('M j, Y g:i:s A') }}">{{ $message->created_at?->format('M j, Y · g:i A') }}</time>
                    </div>
                    <div class="admin-message-bubble">
                        <p>{{ $message->message }}</p>
                        <div class="admin-message-bubble-meta">
                            <span>{{ mb_strlen($message->message) }} characters</span>
                            <span>{{ $messageWordCount }} {{ \Illuminate\Support\Str::plural('word', $messageWordCount) }}</span>
                        </div>
                    </div>
                </article>

                @can('updateStatus', $message)
                    <section class="admin-message-detail-card" aria-label="Message actions">
                        <div class="admin-message-detail-actions">
                            @include('admin.messages.partials.reply-chooser', ['instance' => 'actions', 'label' => 'Reply by email'])
                            @if ($message->status !== 'replied')
                                <form method="POST" action="{{ route('admin.messages.status', $message) }}" data-admin-disable-submit>
                                    @csrf
                                    @method('PATCH')
                                    <button class="admin-message-secondary-action" type="submit" name="status" value="replied">
                                        <i class="bi bi-reply" aria-hidden="true"></i> Mark as replied
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.messages.status', $message) }}" data-admin-disable-submit onsubmit="return confirm('{{ $message->status === 'archived' ? 'Restore this message to the read inbox?' : 'Archive this message?' }}')">
                                @csrf
                                @method('PATCH')
                                @if ($message->status === 'archived')
                                    <button class="admin-message-secondary-action" type="submit" name="status" value="read">
                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Restore
                                    </button>
                                @else
                                    <button class="admin-message-secondary-action admin-message-secondary-action--archive" type="submit" name="status" value="archived">
                                        <i class="bi bi-archive" aria-hidden="true"></i> Archive
                                    </button>
                                @endif
                            </form>
                        </div>
                    </section>
                @endcan
            </section>

            <aside class="admin-message-side-panel" aria-label="Sender information">
                <section class="admin-message-detail-card">
                    <h2>Sender details</h2>
                    <div class="admin-message-contact-list">
                        @include('admin.messages.partials.reply-chooser', ['instance' => 'sender', 'label' => $message->email])
                        @if ($message->phone)
                            <a class="admin-message-contact-button" href="tel:{{ preg_replace('/[^0-9+]/', '', $message->phone) }}">
                                <i class="bi bi-telephone" aria-hidden="true"></i><span>{{ $message->phone }}</span>
                            </a>
                        @endif
                    </div>

                    @if ($message->user)
                        <p class="admin-message-account-badge"><i class="bi bi-person-check" aria-hidden="true"></i> Linked account</p>
                    @endif

                    @if ($message->customer)
                        @can('view', $message->customer)
                            <a class="admin-message-customer-link" href="{{ route('admin.customers.show', $message->customer) }}">
                                <i class="bi bi-person-vcard" aria-hidden="true"></i> View customer profile
                            </a>
                        @endcan
                    @endif
                </section>

                <section class="admin-message-detail-card">
                    <h2>Message timeline</h2>
                    <ol class="admin-message-timeline">
                        @foreach ($timeline as $event)
                            <li class="{{ $event['timestamp'] ? 'is-complete' : '' }}">
                                <span class="admin-message-timeline-dot" aria-hidden="true"></span>
                                <span class="admin-message-timeline-copy">
                                    <span><i class="bi bi-{{ $event['icon'] }}" aria-hidden="true"></i> {{ $event['label'] }}</span>
                                    @if ($event['timestamp'])
                                        <small title="{{ $event['timestamp']->format('M j, Y g:i:s A') }}">{{ $event['timestamp']->format('M j, Y · g:i A') }}</small>
                                    @else
                                        <small>Not yet</small>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </aside>
        </div>

        <nav class="admin-message-detail-nav" aria-label="Message navigation">
            @if ($previousMessage)
                <a class="admin-message-detail-nav-control admin-message-detail-nav-control--previous" href="{{ route('admin.messages.show', $previousMessage) }}" aria-label="Previous message: {{ $previousMessage->name }}, {{ ucfirst($previousMessage->status) }}">
                    <span class="admin-message-detail-nav-heading"><i class="bi bi-chevron-left" aria-hidden="true"></i> Previous</span>
                    <span class="admin-message-detail-nav-sender">
                        <span class="admin-message-detail-nav-status-dot admin-message-detail-nav-status-dot--{{ $previousMessage->status }}" aria-hidden="true"></span>
                        <span class="admin-message-detail-nav-person">{{ $previousMessage->name }}</span>
                        <span class="visually-hidden">{{ ucfirst($previousMessage->status) }}</span>
                    </span>
                </a>
            @else
                <span class="admin-message-detail-nav-control admin-message-detail-nav-control--previous admin-message-detail-nav-control--disabled" aria-disabled="true" aria-label="No previous message">
                    <span class="admin-message-detail-nav-heading"><i class="bi bi-chevron-left" aria-hidden="true"></i> Previous</span>
                </span>
            @endif

            <a class="admin-message-detail-nav-control admin-message-detail-nav-control--back" href="{{ route('admin.messages.index') }}">
                <i class="bi bi-inbox" aria-hidden="true"></i> Back to inbox
            </a>

            @if ($nextMessage)
                <a class="admin-message-detail-nav-control admin-message-detail-nav-control--next" href="{{ route('admin.messages.show', $nextMessage) }}" aria-label="Next message: {{ $nextMessage->name }}, {{ ucfirst($nextMessage->status) }}">
                    <span class="admin-message-detail-nav-heading">Next <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                    <span class="admin-message-detail-nav-sender">
                        <span class="admin-message-detail-nav-status-dot admin-message-detail-nav-status-dot--{{ $nextMessage->status }}" aria-hidden="true"></span>
                        <span class="admin-message-detail-nav-person">{{ $nextMessage->name }}</span>
                        <span class="visually-hidden">{{ ucfirst($nextMessage->status) }}</span>
                    </span>
                </a>
            @else
                <span class="admin-message-detail-nav-control admin-message-detail-nav-control--next admin-message-detail-nav-control--disabled" aria-disabled="true" aria-label="No next message">
                    <span class="admin-message-detail-nav-heading">Next <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                </span>
            @endif
        </nav>
    </main>
@endsection
