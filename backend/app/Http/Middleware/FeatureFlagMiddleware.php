<?php

namespace App\Http\Middleware;

use App\Services\SuperAdmin\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagMiddleware
{
    /**
     * Map of flag key => friendly feature name.
     * Usage in routes: ->middleware('feature:enable_classes')
     */
    protected const FLAG_LABELS = [
        'enable_classes'    => 'Class Scheduling',
        'enable_trainers'   => 'Trainer Portal',
        'enable_store'      => 'Inventory & Store',
        'enable_diet_plans' => 'Diet Plans',
    ];

    public function __construct(protected PlatformSettingsService $settingsService)
    {
    }

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        try {
            $features = $this->settingsService->getAllSettings()['features'] ?? [];
            $enabled  = (bool) ($features[$flag] ?? true);
        } catch (\Throwable) {
            $enabled = true;
        }

        if (! $enabled) {
            $label = self::FLAG_LABELS[$flag] ?? $flag;
            return response()->json([
                'message' => "{$label} is currently disabled by the platform administrator.",
                'error'   => 'feature_disabled',
                'flag'    => $flag,
            ], 403);
        }

        return $next($request);
    }
}
