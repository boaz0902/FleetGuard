<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessagesController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ContactMessage::class);

        $query = ContactMessage::query()->with(['user', 'customer', 'handledBy'])->latest('created_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($subjectFilter = $request->query('subject')) {
            $query->where('subject', $subjectFilter);
        }

        $messages = $query->paginate(20)->withQueryString();

        $statuses = ['new', 'read', 'replied', 'archived'];
        $counts = ContactMessage::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => ContactMessage::query()->count(),
            'new' => (int) ($counts->get('new') ?? 0),
            'read' => (int) ($counts->get('read') ?? 0),
            'replied' => (int) ($counts->get('replied') ?? 0),
            'archived' => (int) ($counts->get('archived') ?? 0),
        ];

        return view('admin.messages.index', [
            'messages' => $messages,
            'stats' => $stats,
            'statusFilter' => $status,
            'subjectFilter' => $subjectFilter,
            'search' => $search,
            'statuses' => $statuses,
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        $this->authorize('view', $contactMessage);

        if ($contactMessage->status === ContactMessage::STATUS_NEW && ! $contactMessage->read_at) {
            $contactMessage->forceFill([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => now(),
            ])->save();
            $contactMessage->refresh();
        }

        return view('admin.messages.show', [
            'message' => $contactMessage->load(['user', 'customer', 'handledBy']),
        ]);
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $this->authorize('updateStatus', $contactMessage);

        $status = $request->input('status');
        if (! in_array($status, [ContactMessage::STATUS_NEW, ContactMessage::STATUS_READ, ContactMessage::STATUS_REPLIED, ContactMessage::STATUS_ARCHIVED], true)) {
            return redirect()->back()->withErrors(['status' => 'Invalid status selected.']);
        }

        $previousStatus = $contactMessage->status;
        $contactMessage->status = $status;

        if ($status === ContactMessage::STATUS_READ && ! $contactMessage->read_at) {
            $contactMessage->read_at = now();
        }

        if ($status === ContactMessage::STATUS_REPLIED && ! $contactMessage->replied_at) {
            $contactMessage->replied_at = now();
        }

        if ($status === ContactMessage::STATUS_ARCHIVED && ! $contactMessage->archived_at) {
            $contactMessage->archived_at = now();
        }

        if ($previousStatus !== $status) {
            $contactMessage->handled_by = $request->user()->id;
        }

        $contactMessage->save();

        if ($previousStatus !== $status) {
            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'contact_message_status_changed',
                'subject_type' => ContactMessage::class,
                'subject_id' => $contactMessage->id,
                'changes' => [
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                    'message_id' => $contactMessage->id,
                ],
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.messages.show', $contactMessage)
            ->with('success', 'Message status updated.');
    }
}
