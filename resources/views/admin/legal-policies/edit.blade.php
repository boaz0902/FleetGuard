@extends('layouts.admin')

@section('title', 'Edit '.$policy->title.' | FleetGuard')
@section('admin-page-title', 'Edit Policy')
@section('admin-page-icon', 'file-earmark-text')
@section('admin-breadcrumb', 'Legal & Policies / '.$policy->title)
@section('admin-page-description', 'Draft changes are versioned separately; published historical versions are never overwritten.')

@section('admin-content')
    @php
        $liveVersion = $policy->currentPublishedVersion;
        $sourceVersion = $draft ?? $liveVersion ?? $policy->versions->first();
        $editorContent = app(\App\Services\LegalPolicyContent::class)->editorContent(old('content', $sourceVersion?->content ?? ''));
    @endphp
    <main class="admin-legal" id="main-content">
        @if (session('status'))<div class="admin-legal-flash" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="admin-legal-alert" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{ $errors->first() }}</div>@endif

        <div class="admin-legal-toolbar">
            <a class="admin-legal-back" href="{{ route('admin.legal-policies.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> All policies</a>
            @if ($sourceVersion)
                <a class="admin-legal-button admin-legal-button--secondary" href="{{ route('admin.legal-policies.preview', [$policy, 'version' => $sourceVersion->id]) }}" target="_blank" rel="noopener"><i class="bi bi-eye" aria-hidden="true"></i> Preview version</a>
            @endif
        </div>

        <section class="admin-legal-editor">
            <div class="admin-legal-editor-heading">
                <div><p class="admin-legal-eyebrow">Document editor</p><h2>{{ $policy->title }}</h2><p>{{ $policy->is_published ? 'Public document' : 'Not visible on public pages' }} · {{ $policy->versions->count() }} saved versions</p></div>
                <span class="admin-legal-status {{ $policy->is_published ? 'is-published' : 'is-unpublished' }}">{{ $policy->is_published ? 'Published' : 'Unpublished' }}</span>
            </div>

            <form method="POST" action="{{ route('admin.legal-policies.update', $policy) }}" class="admin-legal-form" data-legal-editor>
                @csrf
                @method('PUT')
                <div id="policy-content-editor-label" class="admin-legal-content-label">Policy content <small>Format the selected text directly. Unsupported HTML and unsafe content are rejected or removed when saving.</small></div>
                <div class="admin-legal-format-toolbar" role="toolbar" aria-label="Policy text formatting" data-legal-format-toolbar>
                    <div class="admin-legal-format-group" aria-label="Character styling">
                        <button type="button" data-legal-action="bold" data-legal-state="bold" aria-pressed="false" aria-label="Bold (Ctrl+B)" title="Bold (Ctrl+B)"><strong>B</strong></button>
                        <button type="button" data-legal-action="italic" data-legal-state="italic" aria-pressed="false" aria-label="Italic (Ctrl+I)" title="Italic (Ctrl+I)"><em>I</em></button>
                        <button type="button" data-legal-action="underline" data-legal-state="underline" aria-pressed="false" aria-label="Underline (Ctrl+U)" title="Underline (Ctrl+U)"><u>U</u></button>
                        <button type="button" data-legal-action="strikeThrough" data-legal-state="strikeThrough" aria-pressed="false" aria-label="Strikethrough (Ctrl+Shift+X)" title="Strikethrough (Ctrl+Shift+X)"><s>S</s></button>
                    </div>
                    <div class="admin-legal-format-group" aria-label="Font appearance">
                        <select data-legal-action="fontName" aria-label="Font style" title="Font style">
                            <option value="" selected disabled>Font style</option>
                            <option value="Arial">Arial</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Tahoma">Tahoma</option>
                            <option value="Trebuchet MS">Trebuchet MS</option>
                            <option value="Verdana">Verdana</option>
                            <option value="Courier New">Courier New</option>
                        </select>
                        <select data-legal-action="fontSize" aria-label="Font size" title="Font size">
                            <option value="8">8 pt</option>
                            <option value="9">9 pt</option>
                            <option value="10">10 pt</option>
                            <option value="11">11 pt</option>
                            <option value="12" selected>12 pt</option>
                            <option value="14">14 pt</option>
                            <option value="16">16 pt</option>
                            <option value="18">18 pt</option>
                            <option value="24">24 pt</option>
                            <option value="30">30 pt</option>
                            <option value="36">36 pt</option>
                        </select>
                        <label class="admin-legal-color-control" data-legal-state="foreColor" title="Font color">
                            <span class="visually-hidden">Font color</span>
                            <i class="bi bi-type" aria-hidden="true"></i>
                            <input type="color" data-legal-action="foreColor" value="#263341" aria-label="Font color">
                        </label>
                        <label class="admin-legal-color-control" data-legal-state="hiliteColor" title="Highlight color">
                            <span class="visually-hidden">Highlight color</span>
                            <i class="bi bi-highlighter" aria-hidden="true"></i>
                            <input type="color" data-legal-action="hiliteColor" value="#fff19c" aria-label="Highlight color">
                        </label>
                    </div>
                    <div class="admin-legal-format-group" aria-label="Alignment and lists">
                        <button type="button" data-legal-action="justifyLeft" data-legal-state="justifyLeft" aria-pressed="false" aria-label="Align left" title="Align left"><i class="bi bi-text-left" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="justifyCenter" data-legal-state="justifyCenter" aria-pressed="false" aria-label="Align center" title="Align center"><i class="bi bi-text-center" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="justifyRight" data-legal-state="justifyRight" aria-pressed="false" aria-label="Align right" title="Align right"><i class="bi bi-text-right" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="justifyFull" data-legal-state="justifyFull" aria-pressed="false" aria-label="Justify text" title="Justify text"><i class="bi bi-justify" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="insertUnorderedList" data-legal-state="insertUnorderedList" aria-pressed="false" aria-label="Bulleted list" title="Bulleted list"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="insertOrderedList" data-legal-state="insertOrderedList" aria-pressed="false" aria-label="Numbered list" title="Numbered list"><i class="bi bi-list-ol" aria-hidden="true"></i></button>
                    </div>
                    <div class="admin-legal-format-group" aria-label="Spacing and structure">
                        <div class="admin-legal-style-picker" data-legal-style-picker>
                            <button type="button" class="admin-legal-style-trigger" data-legal-style-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Paragraph style">
                                <span data-legal-style-current>Normal text</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="admin-legal-style-options" role="listbox" aria-label="Paragraph style" data-legal-style-options hidden>
                                <button type="button" role="option" data-legal-block-style="normal" aria-selected="true"><span class="admin-legal-style-preview admin-legal-style-preview--normal">Normal text</span><i class="bi bi-check2 admin-legal-style-check" aria-hidden="true"></i></button>
                                <button type="button" role="option" data-legal-block-style="title" aria-selected="false"><span class="admin-legal-style-preview admin-legal-style-preview--title">Title</span><i class="bi bi-check2 admin-legal-style-check" aria-hidden="true" hidden></i></button>
                                <button type="button" role="option" data-legal-block-style="subtitle" aria-selected="false"><span class="admin-legal-style-preview admin-legal-style-preview--subtitle">Subtitle</span><i class="bi bi-check2 admin-legal-style-check" aria-hidden="true" hidden></i></button>
                                <button type="button" role="option" data-legal-block-style="heading1" aria-selected="false"><span class="admin-legal-style-preview admin-legal-style-preview--heading1">Heading 1</span><i class="bi bi-check2 admin-legal-style-check" aria-hidden="true" hidden></i></button>
                                <button type="button" role="option" data-legal-block-style="heading2" aria-selected="false"><span class="admin-legal-style-preview admin-legal-style-preview--heading2">Heading 2</span><i class="bi bi-check2 admin-legal-style-check" aria-hidden="true" hidden></i></button>
                                <button type="button" role="option" data-legal-block-style="heading3" aria-selected="false"><span class="admin-legal-style-preview admin-legal-style-preview--heading3">Heading 3</span><i class="bi bi-check2 admin-legal-style-check" aria-hidden="true" hidden></i></button>
                            </div>
                        </div>
                        <select data-legal-action="lineHeight" aria-label="Line spacing" title="Line spacing">
                            <option value="1">Single spacing</option>
                            <option value="1.15">1.15 spacing</option>
                            <option value="1.5" selected>1.5 spacing</option>
                            <option value="2">Double spacing</option>
                            <option value="2.5">2.5 spacing</option>
                        </select>
                        <button type="button" data-legal-action="indent" data-legal-state="indent" aria-pressed="false" aria-label="Increase indentation" title="Increase indentation (Alt+Shift+Right)"><i class="bi bi-indent" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="outdent" aria-label="Decrease indentation" title="Decrease indentation (Alt+Shift+Left)"><i class="bi bi-outdent" aria-hidden="true"></i></button>
                        <button type="button" data-legal-action="removeFormat" aria-label="Clear formatting" title="Clear formatting"><i class="bi bi-eraser" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div id="policy-content-editor" class="admin-legal-content-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="policy-content-editor-label" aria-required="true" data-legal-content-editor>{!! $editorContent !!}</div>
                <textarea id="policy-content" name="content" hidden></textarea>
                <small class="admin-legal-format-help">Select text and use the toolbar or shortcuts: Ctrl+B, Ctrl+I, Ctrl+U, and Ctrl+Shift+X. Command or Ctrl works on Mac and Windows.</small>
                <small class="admin-legal-editor-error" role="alert" data-legal-editor-error hidden></small>
                <div class="admin-legal-form-row">
                    <label for="effective-at">Effective date <small>Leave blank if a date has not been confirmed.</small><input id="effective-at" type="date" name="effective_at" value="{{ old('effective_at', $sourceVersion?->effective_at?->format('Y-m-d')) }}"></label>
                    <button class="admin-legal-button" type="submit"><i class="bi bi-save" aria-hidden="true"></i> Save draft</button>
                </div>
            </form>

            @if ($draft)
                <form method="POST" action="{{ route('admin.legal-policies.publish', [$policy, $draft]) }}" class="admin-legal-publish-form" data-legal-publish-form>
                    @csrf
                    <div><strong>Draft version {{ $draft->version }}</strong><p>Publishing makes this version available publicly on its effective date.</p></div>
                    <input type="hidden" name="effective_at" value="{{ $draft->effective_at?->format('Y-m-d') }}">
                    <button class="admin-legal-button" type="submit"><i class="bi bi-globe2" aria-hidden="true"></i> Publish version</button>
                </form>
            @endif

            @if ($policy->is_published)
                <form method="POST" action="{{ route('admin.legal-policies.unpublish', $policy) }}" class="admin-legal-unpublish-form" onsubmit="return confirm('Unpublish this policy from public pages? Historical versions and acceptances will be preserved.')">
                    @csrf
                    <span>Unpublishing hides this document from public pages and new bookings.</span>
                    <button type="submit">Unpublish document</button>
                </form>
            @endif
        </section>

        <section class="admin-legal-history" aria-labelledby="policy-history-heading">
            <div class="admin-legal-history-heading"><div><p class="admin-legal-eyebrow">Version control</p><h2 id="policy-history-heading">Version history</h2></div><span>{{ $policy->versions->count() }} versions</span></div>
            <div class="admin-legal-table-wrap">
                <table class="admin-legal-table">
                    <thead><tr><th>Version</th><th>Status</th><th>Effective</th><th>Published</th><th>Content preview</th></tr></thead>
                    <tbody>
                        @forelse ($policy->versions as $version)
                            <tr>
                                <td><strong>{{ $version->version }}</strong></td>
                                <td><span class="admin-legal-status is-{{ $version->status }}">{{ ucfirst($version->status) }}</span></td>
                                <td>{{ $version->effective_at?->format('M j, Y') ?? 'Not set' }}</td>
                                <td>{{ $version->published_at?->timezone('Asia/Manila')->format('M j, Y') ?? '—' }}</td>
                                <td><a href="{{ route('admin.legal-policies.preview', [$policy, 'version' => $version->id]) }}" target="_blank" rel="noopener">Preview version <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No versions have been created.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
