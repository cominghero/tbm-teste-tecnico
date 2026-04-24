<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCheckinRequest;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use Illuminate\Http\JsonResponse;

class CheckinController extends Controller
{
    public function store(StoreCheckinRequest $request): JsonResponse
    {
        $checkin = Checkin::create([
            ...$request->validated(),
            'checked_in_at' => now(),
        ]);

        return CheckinResource::make($checkin->load(['profissional', 'paciente']))
            ->response()
            ->setStatusCode(201);
    }
}
