<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupportTicketController extends ApiController
{
    public function index(Request $request)
    {
        $query = SupportTicket::with([
            'tenant:id,name,slug',
            'user'
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $tickets = $query->latest()->paginate($request->query('per_page', 15));

        return $this->jsonResponse($tickets);
    }

    public function show(Request $request, $id)
    {
        $ticket = SupportTicket::with([
            'tenant:id,name,slug',
            'user',
            'replies.user',
            'replies.user.roles'
        ])->findOrFail($id);

        return $this->jsonResponse(['data' => $ticket]);
    }

    public function reply(Request $request, $id)
    {
        $userId = $request->user()->id;
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'message' => ['required_without:attachments', 'nullable', 'string'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'attachments' => ['nullable', 'array'],
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $data['message'] ?? '',
            'attachments' => $data['attachments'] ?? null,
        ]);

        $status = $data['status'] ?? 'in_progress';
        $ticket->update(['status' => $status]);

        return $this->jsonResponse([
            'message' => 'Admin reply added successfully.',
            'data' => $reply->load(['user', 'user.roles']),
            'ticket_status' => $status
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
        ]);

        $ticket->update(['status' => $data['status']]);

        return $this->jsonResponse([
            'message' => 'Ticket status updated successfully.',
            'data' => $ticket
        ]);
    }

    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // Max 20MB
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $path = $file->store('support_attachments', 'public');

            return $this->jsonResponse([
                'url' => 'storage/' . $path,
                'name' => $originalName,
                'type' => $mimeType,
            ]);
        }

        return $this->jsonResponse(['message' => 'No file uploaded.'], 400);
    }
}
