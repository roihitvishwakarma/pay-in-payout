<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayInRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PayInController extends Controller
{
    /**
     * Create a pending pay-in and return its transaction id and status.
     */
    public function store(StorePayInRequest $request, PaymentService $service): JsonResponse
    {
        $transaction = $service->createTransaction(
            'payin',
            $request->integer('merchant_id'),
            (string) $request->input('amount'),
        );

        return response()->json([
            'transaction_id' => $transaction->transaction_id,
            'status' => $transaction->status->value,
        ], 201);
    }
}
