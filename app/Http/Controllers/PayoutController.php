<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayoutRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PayoutController extends Controller
{
    /**
     * Create a pending payout and return its transaction id and status.
     */
    public function store(StorePayoutRequest $request, PaymentService $service): JsonResponse
    {
        $transaction = $service->createTransaction(
            'payout',
            $request->integer('merchant_id'),
            (string) $request->input('amount'),
        );

        return response()->json([
            'transaction_id' => $transaction->transaction_id,
            'status' => $transaction->status->value,
        ], 201);
    }
}
