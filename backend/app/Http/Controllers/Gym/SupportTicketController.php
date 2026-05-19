<?php

namespace App\Http\Controllers\Gym;

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
        $tenantId = $request->user()->tenant_id;
        
        $tickets = SupportTicket::with(['user'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate($request->query('per_page', 15));

        return $this->jsonResponse($tickets);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'attachments' => ['nullable', 'array'],
        ]);

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'],
            'status' => 'open',
            'attachments' => $data['attachments'] ?? null,
        ]);

        return $this->jsonResponse([
            'message' => 'Support ticket created successfully.',
            'data' => $ticket->load('user')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        
        $ticket = SupportTicket::with([
            'user',
            'replies.user',
            'replies.user.roles'
        ])
        ->where('tenant_id', $tenantId)
        ->findOrFail($id);

        return $this->jsonResponse(['data' => $ticket]);
    }

    public function reply(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'message' => ['required_without:attachments', 'nullable', 'string'],
            'attachments' => ['nullable', 'array'],
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $data['message'] ?? '',
            'attachments' => $data['attachments'] ?? null,
        ]);

        // Reopen ticket if resolved/closed on user reply
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open']);
        }

        return $this->jsonResponse([
            'message' => 'Reply added successfully.',
            'data' => $reply->load(['user', 'user.roles'])
        ], 201);
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
