<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $query = Notification::whereNull('tenant_id')
            ->whereNull('user_id');

        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->latest()->paginate(20);

        return $this->jsonResponse(
            $this->paginatedData($notifications, \App\Http\Resources\Gym\NotificationResource::class)
        );
    }

    public function counts()
    {
        $unreadCount = Notification::whereNull('tenant_id')
            ->whereNull('user_id')
            ->unread()
            ->count();

        return $this->jsonResponse([
            'data' => [
                'unread_notifications' => $unreadCount
            ]
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::whereNull('tenant_id')
            ->whereNull('user_id')
            ->findOrFail($id);

        $notification->update(['read' => true]);

        return $this->jsonResponse([
            'message' => 'Notification marked as read successfully'
        ]);
    }

    public function markAllAsRead()
    {
        Notification::whereNull('tenant_id')
            ->whereNull('user_id')
            ->unread()
            ->update(['read' => true]);

        return $this->jsonResponse([
            'message' => 'All notifications marked as read successfully'
        ]);
    }
}
