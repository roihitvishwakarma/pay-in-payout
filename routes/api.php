<?php

use App\Http\Controllers\PayInController;
use App\Http\Controllers\PayoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Payment intake endpoints. Each one creates a pending transaction and returns
| its transaction id and status.
|
*/

Route::post('/pay-in', [PayInController::class, 'store']);
Route::post('/payout', [PayoutController::class, 'store']);
