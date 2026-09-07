<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStreamController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllStreams($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function setEnabled(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'enable' => ['required', 'integer', 'in:0,1'],
        ]);

        try {
            $result = $this->cdnfly->adminUpdateStream($id, $validated);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminUpdateStream($id, ['enable' => 0]);
            $this->cdnfly->adminDeleteStream($id);
            return response()->json(['ok' => true]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminCreateStream($request->all());
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminUpdateStream($id, $request->all());
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function groups(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listStreamGroups($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function storeGroup(Request $request): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminCreateStreamGroup($request->all());
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminUpdateStreamGroup($id, $request->all());
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function destroyGroup(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminDeleteStreamGroup($id);
            return response()->json(['ok' => true]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }
}
