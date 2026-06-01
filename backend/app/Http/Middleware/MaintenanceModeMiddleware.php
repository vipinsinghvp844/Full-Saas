<?php

namespace App\Http\Middleware;

use App\Services\SuperAdmin\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeMiddleware
{
    public function __construct(protected PlatformSettingsService $settingsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = $this->settingsService->getAllSettings();
            $maintenanceMode = (bool) ($settings['system']['maintenance_mode'] ?? false);
        } catch (\Throwable) {
            $maintenanceMode = false;
        }

        if ($maintenanceMode) {
            $user = Auth::user();

            // Super Admins bypass maintenance mode
            if ($user && $user->hasRole('Super Admin')) {
                return $next($request);
            }

            return response()->json([
                'message'          => 'Platform is currently under maintenance. Please try again later.',
                'error'            => 'maintenance_mode',
                'maintenance_mode' => true,
            ], 503);
        }

        return $next($request);
    }
}
