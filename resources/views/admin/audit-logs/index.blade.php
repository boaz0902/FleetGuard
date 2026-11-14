@extends('layouts.admin')

@section('title', 'Audit Logs | FleetGuard')
@section('admin-page-title', 'Audit Logs')
@section('admin-page-icon', 'clipboard-data')
@section('admin-breadcrumb', 'Management')
@section('admin-page-description', 'A read-only history of administrative and booking activity.')

@section('admin-content')
    <main class="admin-audit-logs" id="main-content">
        <section class="admin-audit-header">
            <div>
                <p class="admin-audit-eyebrow">Governance</p>
                <h2>System activity</h2>
                <p class="admin-audit-subtitle">Review recorded changes and the actors responsible. Audit entries cannot be edited here.</p>
            </div>
            <p class="admin-audit-updated">
                <i class="bi bi-clock" aria-hidden="true"></i>
                Last updated <time datetime="{{ $now->toIso8601String() }}">{{ $now->timezone('Asia/Manila')->format('M j, Y g:i A') }}</time>
            </p>
        </section>

        <section class="admin-audit-metrics" aria-label="Audit log statistics">
            @foreach ([
                ['key' => 'total', 'label' => 'Total entries', 'description' => 'All recorded activity', 'icon' => 'journal-text', 'tone' => 'blue'],
                ['key' => 'today', 'label' => 'Today', 'description' => 'Since midnight, Manila time', 'icon' => 'calendar-day', 'tone' => 'green'],
                ['key' => 'last_seven_days', 'label' => 'Last 7 days', 'description' => 'Including today', 'icon' => 'calendar-week', 'tone' => 'amber'],
                ['key' => 'distinct_actors', 'label' => 'Distinct actors', 'description' => 'Accounts with recorded activity', 'icon' => 'people', 'tone' => 'slate'],
            ] as $metric)
                <article class="admin-audit-metric">
                    <span class="admin-audit-metric-icon admin-audit-metric-icon--{{ $metric['tone'] }}">
                        <i class="bi bi-{{ $metric['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <span class="admin-audit-metric-copy">
                        <span>{{ $metric['label'] }}</span>
                        <strong>{{ number_format($metrics[$metric['key']]) }}</strong>
                        <small>{{ $metric['description'] }}</small>
                    </span>
                </article>
            @endforeach
        </section>

        @if ($errors->any())
            <div class="admin-audit-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <section class="admin-audit-panel" aria-labelledby="audit-filters-heading">
            <div class="admin-audit-panel-heading">
                <div>
                    <p class="admin-audit-eyebrow">Find an event</p>
                    <h2 id="audit-filters-heading">Filter activity</h2>
                </div>
                <a class="admin-audit-clear" href="{{ route('admin.audit-logs.index') }}">Clear all</a>
            </div>

            <form class="admin-audit-filters" method="GET" action="{{ route('admin.audit-logs.index') }}">
                <label class="admin-audit-search">
                    <span>Search</span>
                    <span class="admin-audit-input-wrap">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            maxlength="100"
                            placeholder="Action, actor, or record reference"
                            aria-label="Search audit actions, actor names, or affected records"
                        >
                    </span>
                </label>

                <label>
                    <span>Action type</span>
                    <select name="action" aria-label="Filter by action type">
                        <option value="">All actions</option>
                        @foreach ($actionGroups as $area => $actions)
                            <optgroup label="{{ $area }}">
                                @forelse ($actions as $action)
                                    <option value="{{ $action['value'] }}" @selected(($filters['action'] ?? '') === $action['value'])>{{ $action['label'] }}</option>
                                @empty
                                    @if ($area === 'Auth')
                                        <option value="" disabled>No authentication events recorded</option>
                                    @endif
                                @endforelse
                            </optgroup>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Actor</span>
                    <select name="actor" aria-label="Filter by actor">
                        <option value="">All actors</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>From date</span>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="Filter from date">
                </label>

                <label>
                    <span>To date</span>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="Filter through date">
                </label>

                <button class="admin-audit-apply" type="submit">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    Apply filters
                </button>
            </form>

            @if ($filterChips !== [])
                <div class="admin-audit-chips" aria-label="Active audit log filters">
                    @foreach ($filterChips as $chip)
                        <a class="admin-audit-chip" href="{{ $chip['url'] }}" aria-label="Remove {{ $chip['label'] }} filter">
                            <span>{{ $chip['label'] }}</span>
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="admin-audit-panel admin-audit-results" aria-labelledby="audit-results-heading">
            <div class="admin-audit-panel-heading">
                <div>
                    <p class="admin-audit-eyebrow">Immutable history</p>
                    <h2 id="audit-results-heading">Activity log</h2>
                </div>
                <p class="admin-audit-result-count" aria-live="polite">
                    Showing {{ number_format($logs->firstItem() ?? 0) }}–{{ number_format($logs->lastItem() ?? 0) }} of {{ number_format($logs->total()) }}
                </p>
            </div>

            <div class="admin-audit-table-wrap">
                <table class="admin-audit-table">
                    <thead>
                        <tr>
                            <th scope="col">Timestamp</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Action</th>
                            <th scope="col">Affected record</th>
                            <th scope="col">Summary</th>
                            <th scope="col">Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $entry)
                            <tr>
                                <td data-label="Timestamp">
                                    <time datetime="{{ $entry['timestamp']->toIso8601String() }}">
                                        {{ $entry['timestamp']->timezone('Asia/Manila')->format('M j, Y · g:i:s A') }}
                                    </time>
                                </td>
                                <td data-label="Actor">{{ $entry['actor'] }}</td>
                                <td data-label="Action">
                                    <span class="admin-audit-action admin-audit-action--{{ $entry['action']['area_key'] }}">
                                        <i class="bi bi-{{ $entry['action']['icon'] }}" aria-hidden="true"></i>
                                        <span>{{ $entry['action']['label'] }}</span>
                                    </span>
                                    <small class="admin-audit-action-area">{{ $entry['action']['area'] }}</small>
                                </td>
                                <td data-label="Affected record">
                                    @if ($entry['record']['url'])
                                        <a class="admin-audit-record-link" href="{{ $entry['record']['url'] }}">{{ $entry['record']['label'] }}</a>
                                    @else
                                        <span class="admin-audit-record-label">{{ $entry['record']['label'] }}</span>
                                    @endif
                                    @if ($entry['record']['context'])
                                        <small class="admin-audit-record-context">{{ $entry['record']['context'] }}</small>
                                    @endif
                                </td>
                                <td data-label="Summary">{{ $entry['summary'] }}</td>
                                <td data-label="Changes">
                                    <details class="admin-audit-change-details">
                                        <summary aria-label="Show changes for {{ $entry['action']['label'] }}">
                                            <span>{{ count($entry['diff']) }} {{ \Illuminate\Support\Str::plural('field', count($entry['diff'])) }}</span>
                                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                        </summary>
                                        @if ($entry['diff'] !== [])
                                            <dl class="admin-audit-diff">
                                                @foreach ($entry['diff'] as $change)
                                                    <div class="admin-audit-diff-row">
                                                        <dt>{{ $change['field'] }}</dt>
                                                        <dd><span class="admin-audit-diff-old">{{ $change['from'] }}</span></dd>
                                                        <dd class="admin-audit-diff-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></dd>
                                                        <dd><span class="admin-audit-diff-new">{{ $change['to'] }}</span></dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @else
                                            <p class="admin-audit-no-diff">No field values were stored for this event.</p>
                                        @endif
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="admin-audit-empty">
                                        <span><i class="bi bi-journal-x" aria-hidden="true"></i></span>
                                        <strong>No audit entries found</strong>
                                        <p>Try a different search or remove filters to see more activity.</p>
                                        @if ($filterChips !== [])
                                            <a href="{{ route('admin.audit-logs.index') }}">Clear filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="admin-audit-pagination">
                    {{ $logs->onEachSide(1)->links() }}
                </div>
            @endif
        </section>
    </main>
@endsection
