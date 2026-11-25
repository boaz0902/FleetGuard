@extends('layouts.admin')

@section('title', 'Legal & Policies | FleetGuard')
@section('admin-page-title', 'Legal & Policies')
@section('admin-page-icon', 'file-earmark-text')
@section('admin-breadcrumb', 'Management')
@section('admin-page-description', 'Manage published documents, draft versions, and customer acceptance records.')

@section('admin-content')
    <main class="admin-legal" id="main-content">
        @if (session('status'))<div class="admin-legal-flash" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('status') }}</div>@endif

        <section class="admin-legal-intro">
            <div><p class="admin-legal-eyebrow">Governance</p><h2>Policy library</h2><p>Published versions are retained as history. Editing a published document creates a separate draft version.</p></div>
            <a class="admin-legal-button admin-legal-button--secondary" href="{{ route('admin.legal-policies.acceptances') }}"><i class="bi bi-people" aria-hidden="true"></i> Review acceptances</a>
        </section>

        <section class="admin-legal-grid" aria-label="Legal policy documents">
            @foreach ($policies as $policy)
                @php
                    $current = $policy->currentPublishedVersion;
                    $latest = $policy->versions->first();
                    $status = $latest?->status === 'draft' ? 'Draft changes' : ($policy->is_published ? 'Published' : 'Unpublished');
                @endphp
                <article class="admin-legal-card">
                    <div class="admin-legal-card-heading">
                        <span class="admin-legal-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
                        <span class="admin-legal-status {{ $policy->is_published ? 'is-published' : 'is-unpublished' }}">{{ $status }}</span>
                    </div>
                    <h3>{{ $policy->title }}</h3>
                    <p class="admin-legal-card-meta">
                        @if ($current)
                            Live version {{ $current->version }} · Updated {{ $current->updated_at->timezone('Asia/Manila')->format('M j, Y') }}
                        @else
                            No currently effective published version
                        @endif
                    </p>
                    <p class="admin-legal-card-count">{{ $policy->versions->count() }} {{ \Illuminate\Support\Str::plural('version', $policy->versions->count()) }} in history</p>
                    <div class="admin-legal-card-actions">
                        <a class="admin-legal-button" href="{{ route('admin.legal-policies.edit', $policy) }}">Manage document <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        @if ($current)
                            <a class="admin-legal-preview-link" href="{{ route('admin.legal-policies.preview', [$policy, 'version' => $current->id]) }}" target="_blank" rel="noopener">Preview live version</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    </main>
@endsection
