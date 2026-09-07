<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDnsController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllDnsApis($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'auth' => ['required', 'array'],
            'des'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->cdnfly->adminCreateDnsApi($validated);
            return response()->json(['ok' => true, 'data' => $result], 201);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string'],
            'auth' => ['sometimes', 'array'],
            'des'  => ['nullable', 'string', 'max:500'],
            'enable' => ['sometimes', 'integer', 'in:0,1'],
        ]);

        try {
            $result = $this->cdnfly->adminUpdateDnsApi($id, $validated);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminDeleteDnsApi($id);
            return response()->json(['ok' => true]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function lines(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listLines($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }
}
