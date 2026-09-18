<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicNetworkSummary;
use Illuminate\Http\JsonResponse;

class PublicNetworkController extends Controller
{
    public function index(PublicNetworkSummary $network): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $network->get()])
            ->header('Cache-Control', 'no-store');
    }
}
