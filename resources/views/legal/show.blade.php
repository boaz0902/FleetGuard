@extends('layouts.app')

@section('title', $policy->title.' | FleetGuard')
@section('meta-description', 'Review the FleetGuard '.$policy->title.' document and its current version.')

@section('content')
    @include('partials.public-navigation')

    @php
        $draftNotice = str_contains($version->content, 'DRAFT — PENDING BUSINESS AND LEGAL REVIEW.');
        $contentRenderer = app(\App\Services\LegalPolicyContent::class);
    @endphp

    <main class="legal-page" id="main-content">
        <header class="legal-page-heading">
            <p class="legal-eyebrow"><span aria-hidden="true"></span> FleetGuard · Legal library</p>
            <h1>{{ $policy->title }}</h1>
            <p>Policy information and document version details.</p>
            <dl class="legal-document-meta">
                <div><dt>Version</dt><dd>{{ $version->version }}</dd></div>
                <div><dt>Last updated</dt><dd><time datetime="{{ $version->updated_at->toIso8601String() }}">{{ $version->updated_at->timezone('Asia/Manila')->format('F j, Y') }}</time></dd></div>
                <div><dt>Effective date</dt><dd>{{ $version->effective_at?->format('F j, Y') ?? 'Pending confirmation' }}</dd></div>
            </dl>
        </header>

        @if ($isPreview)
            <div class="legal-draft-notice" role="status"><i class="bi bi-eye" aria-hidden="true"></i> Administrator preview · This document is not necessarily public.</div>
        @elseif ($draftNotice)
            <div class="legal-draft-notice" role="note"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Draft content pending business and legal review. This placeholder is not a final policy.</div>
        @endif

        <div class="legal-document-layout">
            <article class="legal-document">
                {!! $contentRenderer->renderDocument($version->content) !!}
            </article>

            <aside class="legal-document-nav" aria-label="Other legal documents">
                <p>Legal &amp; Policies</p>
                @foreach ($policies as $item)
                    <a href="{{ $isPreview ? route('admin.legal-policies.preview', [$item, 'version' => $item->currentPublishedVersion?->id]) : route('legal.show', $item->slug) }}"
                       class="{{ $item->is($policy) ? 'is-active' : '' }}"
                       @if ($item->is($policy)) aria-current="page" @endif>
                        {{ $item->title }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                @endforeach
                @auth
                    @if (auth()->user()->isCustomer())
                        <a class="legal-history-link" href="{{ route('account.legal-policies') }}">My acceptance history <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    @endif
                @endauth
            </aside>
        </div>
    </main>
@endsection
