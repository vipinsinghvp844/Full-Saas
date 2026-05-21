<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    protected function getCorsHeaders(Request $request): array
    {
        $allowedOrigins = array_values(array_filter(array_merge(
            [
                'http://localhost:3000',
                'http://127.0.0.1:3000',
            ],
            array_map('trim', explode(',', (string) env('FRONTEND_URL', '')))
        )));

        $origin = $request->headers->get('Origin');
        $headers = [];

        if (in_array($origin, $allowedOrigins)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Requested-With';
        $headers['Access-Control-Allow-Credentials'] = 'true';

        return $headers;
    }

    protected function jsonResponse($data, $status = 200, Request $request = null): JsonResponse
    {
        $headers = $request ? $this->getCorsHeaders($request) : [];
        return response()->json($data, $status, $headers);
    }

    protected function resourceData(JsonResource $resource): array
    {
        return $resource->resolve();
    }

    protected function paginatedData(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'data' => $resourceClass::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
