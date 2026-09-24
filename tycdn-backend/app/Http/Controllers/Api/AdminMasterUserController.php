<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMasterUserController extends Controller
{
    use ReportsCdnflyFailures;

    private const FIELDS = ['id', 'email', 'name', 'des', 'phone', 'qq', 'user_group', 'cert_name', 'cert_no', 'cert_verified', 'auth2_verified', 'auth2_enable', 'auth2_end_at', 'auth2_expire_action', 'company_name', 'company_credit_code', 'company_verified', 'white_ip', 'login_captcha', 'balance', 'create_at', 'enable', 'type'];

    public function index(Request $request, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        $query = $id === null ? $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'integer', 'min:1'], 'user_group' => ['sometimes', 'integer', 'min:1'],
            'cert_verified' => ['sometimes', 'in:0,1'],
            'name' => ['sometimes', 'string', 'max:255'], 'cert_name' => ['sometimes', 'string', 'max:255'],
            'des' => ['sometimes', 'string', 'max:1000'], 'email' => ['sometimes', 'string', 'max:255'],
            'qq' => ['sometimes', 'string', 'max:32'], 'phone' => ['sometimes', 'string', 'max:32'],
        ]) : [];
        try {
            $result = $cdnfly->proxyAdminRequest('GET', '/v1/users'.($id === null ? '' : '/'.$id), $query);
            $clean = static fn (array $row) => array_intersect_key($row, array_flip(self::FIELDS));
            $result['data'] = $id === null ? array_map($clean, $result['data'] ?? []) : $clean($result['data'] ?? []);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function save(Request $request, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        $required = $id === null ? 'required' : 'sometimes';
        $rules = [
            'email' => [$required, 'email', 'max:255'], 'name' => [$required, 'string', 'max:255'],
            'password' => [$required, 'string', 'min:6', 'max:255', 'regex:/[a-zA-Z]/', 'regex:/[0-9]/'],
            'type' => [$required, 'integer', 'in:1,2'], 'user_group' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'login_captcha' => ['sometimes', 'nullable', 'in:sms,email'],
            'auth2_expire_action' => ['sometimes', 'in:none,lock'],
            'auth2_end_at' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s'],
        ];
        foreach (['enable', 'cert_verified', 'company_verified', 'auth2_enable', 'auth2_verified'] as $field) {
            $rules[$field] = ['sometimes', 'integer', 'in:0,1'];
        }
        foreach (['des', 'phone', 'qq', 'cert_name', 'cert_no', 'company_name', 'company_credit_code', 'white_ip'] as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'string', 'max:4096'];
        }
        $data = $request->validate($rules);
        // Editing the account type is deliberately not offered by the native UI.
        if ($id !== null) {
            unset($data['type']);
        }
        foreach ($data as $key => $value) {
            if ($value === null && $key !== 'user_group') {
                $data[$key] = '';
            }
        }

        return $this->forward($cdnfly, $id === null ? 'POST' : 'PUT', '/v1/users'.($id === null ? '' : '/'.$id), $data);
    }

    public function destroy(int $id, CdnflyApiService $cdnfly): JsonResponse
    {
        return $this->forward($cdnfly, 'DELETE', '/v1/users/'.$id);
    }

    public function storeGroup(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        return $this->groups($request, $cdnfly);
    }

    public function groups(Request $request, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:0', 'max:100']]);
        } elseif ($request->isMethod('DELETE')) {
            abort_if($id === null, 405);
            $data = [];
        } else {
            $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'des' => ['nullable', 'string', 'max:4096']]);
            $data['des'] ??= '';
        }

        return $this->forward($cdnfly, $request->method(), '/v1/user-groups'.($id === null ? '' : '/'.$id), $data);
    }

    public function switchUser(int $id, CdnflyApiService $cdnfly): JsonResponse
    {
        try {
            // Native panel login remains separate from the Laravel admin session.
            $base = parse_url((string) config('services.cdnfly.base_url'));
            if (! isset($base['host']) || ! in_array($base['scheme'] ?? '', ['http', 'https'], true)) {
                throw new \RuntimeException('用户控制台地址无效');
            }
            $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');
            $config = $cdnfly->proxyAdminRequest('GET', '/v1/configs/global-0-system-user_domain');
            $domain = preg_split('/\s+/', trim((string) ($config['data']['value'] ?? '')))[0];
            if ($domain !== '') {
                $panel = parse_url(str_contains($domain, '://') ? $domain : $base['scheme'].'://'.$domain);
                if (! isset($panel['host']) || ! in_array($panel['scheme'] ?? '', ['http', 'https'], true) || isset($panel['user']) || isset($panel['pass'])) {
                    throw new \RuntimeException('用户控制台地址无效');
                }
                $origin = $panel['scheme'].'://'.$panel['host'].(isset($panel['port']) ? ':'.$panel['port'] : '');
            }
            $result = $cdnfly->proxyAdminRequest('GET', '/v1/users/'.$id, ['token' => 1]);
            $account = $result['data'] ?? [];
            if (empty($account['access_token'])) {
                throw new \RuntimeException('未取得用户登录信息');
            }
            $query = http_build_query(['access_token' => str_replace(['/', '='], ['_', ','], $account['access_token']), 'username' => $account['username'] ?? '', 'uid' => $id]);

            return response()->json(['ok' => true, 'data' => ['url' => $origin.'/dashboard/login?'.$query]])->header('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    private function forward(CdnflyApiService $cdnfly, string $method, string $path, array $data = []): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest($method, $path, $data)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
