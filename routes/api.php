<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MbPaymentController;

Route::post('/mb/payment', [
    MbPaymentController::class,
    'store',
]);
