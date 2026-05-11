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
        $payload = $this->paginatedData($this->paymentRepository->paginate($request->all()), PaymentResource::class);
        $payload['filters'] = [
            'statuses' => ['pending', 'completed', 'failed', 'refunded'],
        ];

        return $this->jsonResponse($payload, 200, $request);
    }
}
