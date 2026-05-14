<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\NotificationResource;
use App\Services\Gym\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends ApiController
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    /**
     * GET /api/gym/notifications
     * Paginated list of notifications with filters.
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $filters = $request->validate([
            'category' => ['nullable', Rule::in(['renewal', 'payment', 'alert', 'system'])],
            'read' => ['nullable', 'in:true,false,0,1'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->notificationService->getNotifications($tenantId, $filters);

        return $this->jsonResponse(
            $this->paginatedData($paginator, NotificationResource::class),
            200,
            $request
        );
    }

    /**
     * GET /api/gym/notifications/counts
     * Alert counts for dashboard badges.
     */
    public function counts(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $counts = $this->notificationService->getAlertCounts($tenantId);

        return $this->jsonResponse([
            'data' => $counts,
        ], 200, $request);
    }

    /**
     * POST /api/gym/notifications/generate
     * Trigger notification generation (simulated scheduler).
     */
    public function generate(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $results = $this->notificationService->runAllChecks($tenantId);

        return $this->jsonResponse([
            'message' => 'Notification generation completed.',
            'data' => [
                'generated' => $results,
                'counts' => $this->notificationService->getAlertCounts($tenantId),
            ],
        ], 200, $request);
    }

    /**
     * PUT /api/gym/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id)
    {
        $tenantId = $request->user()->tenant_id;

        $notification = $this->notificationService->markAsRead($tenantId, $id);

        if (! $notification) {
            return $this->jsonResponse([
                'message' => 'Notification not found.',
            ], 404, $request);
        }

        return $this->jsonResponse([
            'message' => 'Notification marked as read.',
            'data' => (new NotificationResource($notification))->resolve(),
        ], 200, $request);
    }

    /**
     * PUT /api/gym/notifications/read-all
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $count = $this->notificationService->markAllAsRead($tenantId);

        return $this->jsonResponse([
            'message' => "{$count} notifications marked as read.",
            'data' => [
                'updated_count' => $count,
            ],
        ], 200, $request);
    }
}
