<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        DeviceToken::query()->updateOrCreate(
            ['token' => $request->validated('token')],
            ['user_id' => $request->user()->id],
        );

        return response()->json(['saved' => true]);
    }
}
