<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Resources\SuperAdmin\PaymentResource;
use App\Repositories\SuperAdmin\PaymentRepository;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(protected PaymentRepository $paymentRepository)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:paid,pending,failed'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'in:created_at,amount,status,payment_method'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ]);

        $payload = $this->paginatedData($this->paymentRepository->paginate($filters), PaymentResource::class);
        $payload['summary'] = $this->paymentRepository->summary($filters);
        $payload['filters'] = $this->paymentRepository->filterOptions();

        return $this->jsonResponse($payload, 200, $request);
    }

    public function show(Request $request, int $payment)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new PaymentResource($this->paymentRepository->findPlatformPayment($payment))),
        ], 200, $request);
    }
}
