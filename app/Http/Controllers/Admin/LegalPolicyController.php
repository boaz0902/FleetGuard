<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalPolicy;
use App\Models\LegalPolicyAcceptance;
use App\Models\LegalPolicyVersion;
use App\Services\LegalPolicyContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LegalPolicyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', LegalPolicy::class);

        return view('admin.legal-policies.index', [
            'policies' => LegalPolicy::query()
                ->with(['versions' => fn ($query) => $query->latest('id')])
                ->orderBy('title')
                ->get()
                ->each(function (LegalPolicy $policy): void {
                    if (! $policy->is_published) {
                        $policy->setRelation('currentPublishedVersion', null);
                    }
                }),
        ]);
    }

    public function edit(LegalPolicy $legalPolicy): View
    {
        $this->authorize('view', $legalPolicy);
        $legalPolicy->load(['versions' => fn ($query) => $query->latest('id')]);

        return view('admin.legal-policies.edit', [
            'policy' => $legalPolicy,
            'draft' => $legalPolicy->versions->firstWhere('status', 'draft'),
        ]);
    }

    public function update(Request $request, LegalPolicy $legalPolicy, LegalPolicyContent $policyContent): RedirectResponse
    {
        $this->authorize('update', $legalPolicy);
        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string', 'min:20', 'max:50000'],
            'effective_at' => ['nullable', 'date'],
        ]);
        $validator->after(function ($validator) use ($request, $policyContent): void {
            $content = $request->input('content');

            if (is_string($content) && $policyContent->containsUnsafeMarkup($content)) {
                $validator->errors()->add('content', 'Embedded media, scripts, and active HTML are not allowed in policy content.');
            }

            if (is_string($content) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)) {
                $validator->errors()->add('content', 'Control characters are not allowed.');
            }
        });
        $validated = $validator->validate();
        $validated['content'] = $policyContent->sanitizeForStorage($validated['content']);

        if (trim(strip_tags($validated['content'])) === '') {
            throw ValidationException::withMessages([
                'content' => ['Policy content must include readable text.'],
            ]);
        }

        $version = DB::transaction(function () use ($legalPolicy, $validated, $request): LegalPolicyVersion {
            $policy = LegalPolicy::query()->lockForUpdate()->findOrFail($legalPolicy->id);
            $latest = $policy->versions()->lockForUpdate()->latest('id')->first();

            if ($latest?->status === 'draft') {
                $latest->update([
                    'content' => trim($validated['content']),
                    'effective_at' => $validated['effective_at'] ?? null,
                ]);
                $version = $latest;
            } else {
                $version = $policy->versions()->create([
                    'version' => $this->nextVersionLabel($latest?->version),
                    'content' => trim($validated['content']),
                    'status' => 'draft',
                    'effective_at' => $validated['effective_at'] ?? null,
                ]);
            }

            $this->recordAudit($request, 'legal_policy_draft_updated', $policy, [
                'version' => $version->version,
            ]);

            return $version;
        });

        return redirect()
            ->route('admin.legal-policies.edit', $legalPolicy)
            ->with('status', 'Draft version '.$version->version.' saved.');
    }

    public function publish(Request $request, LegalPolicy $legalPolicy, LegalPolicyVersion $version): RedirectResponse
    {
        $this->authorize('update', $legalPolicy);
        abort_unless($version->legal_policy_id === $legalPolicy->id, 404);

        $validated = $request->validate([
            'effective_at' => ['nullable', 'date'],
        ]);

        if ($version->status !== 'draft') {
            throw ValidationException::withMessages([
                'version' => ['Only a draft version can be published.'],
            ]);
        }

        DB::transaction(function () use ($request, $legalPolicy, $version, $validated): void {
            $policy = LegalPolicy::query()->lockForUpdate()->findOrFail($legalPolicy->id);
            $lockedVersion = $policy->versions()->lockForUpdate()->findOrFail($version->id);

            if ($lockedVersion->status !== 'draft') {
                throw ValidationException::withMessages([
                    'version' => ['Only a draft version can be published.'],
                ]);
            }

            $lockedVersion->update([
                'status' => 'published',
                'effective_at' => $validated['effective_at'] ?? $lockedVersion->effective_at,
                'published_at' => now(),
            ]);
            $policy->update(['is_published' => true]);
            $this->recordAudit($request, 'legal_policy_published', $policy, [
                'version' => $lockedVersion->version,
                'effective_at' => $lockedVersion->effective_at?->toDateString(),
            ]);
        });

        return redirect()->route('admin.legal-policies.edit', $legalPolicy)->with('status', 'Policy version published.');
    }

    public function unpublish(Request $request, LegalPolicy $legalPolicy): RedirectResponse
    {
        $this->authorize('update', $legalPolicy);

        DB::transaction(function () use ($request, $legalPolicy): void {
            $policy = LegalPolicy::query()->lockForUpdate()->findOrFail($legalPolicy->id);
            $policy->update(['is_published' => false]);
            $this->recordAudit($request, 'legal_policy_unpublished', $policy);
        });

        return redirect()->route('admin.legal-policies.edit', $legalPolicy)->with('status', 'Policy unpublished from public pages.');
    }

    public function preview(Request $request, LegalPolicy $legalPolicy): View
    {
        $this->authorize('view', $legalPolicy);
        $versionId = $request->integer('version');
        $version = $legalPolicy->versions()
            ->when($versionId > 0, fn ($query) => $query->whereKey($versionId))
            ->latest('id')
            ->firstOrFail();
        $legalPolicy->setRelation('currentPublishedVersion', $version);

        return view('legal.show', [
            'policy' => $legalPolicy,
            'version' => $version,
            'policies' => LegalPolicy::query()->where('is_published', true)->orderBy('id')->get(),
            'isPreview' => true,
        ]);
    }

    public function acceptances(): View
    {
        $this->authorize('viewAny', LegalPolicy::class);

        return view('admin.legal-policies.acceptances', [
            'acceptances' => LegalPolicyAcceptance::query()
                ->with(['customer:id,full_name', 'booking:id,booking_reference', 'policy:id,title', 'version:id,legal_policy_id,version'])
                ->latest('accepted_at')
                ->paginate(30),
        ]);
    }

    private function nextVersionLabel(?string $latest): string
    {
        if (! $latest || ! preg_match('/^(\d+)\.(\d+)/', $latest, $matches)) {
            return '1.0';
        }

        return $matches[1].'.'.((int) $matches[2] + 1);
    }

    private function recordAudit(Request $request, string $action, LegalPolicy $policy, array $changes = []): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => LegalPolicy::class,
            'subject_id' => (string) $policy->id,
            'changes' => array_merge(['policy_slug' => $policy->slug], $changes),
            'created_at' => now(),
        ]);
    }
}
