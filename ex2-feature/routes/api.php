<?php

use App\Http\Controllers\Api\V1\CheckinController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('v1')
    ->group(function () {
        Route::post('/checkin', [CheckinController::class, 'store']);
    });
