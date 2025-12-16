<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile with balances.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('assets');
        
        return response()->json([
            'user' => new ProfileResource($user),
        ], 200);
    }
}
