<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSiteResourceController extends Controller
{
    use ReportsCdnflyFailures;

    public function handle(Request $request, string $resource, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        $methods = [
            'site-groups' => ['GET', 'POST', 'PUT', 'DELETE'],
            'user-configs' => ['GET', 'POST', 'PUT', 'DELETE'],
            'dnsapis' => ['GET', 'POST', 'PUT', 'DELETE'],
            'domains' => ['GET', 'POST'],
            'cname-check' => ['POST'],
        ];
        abort_unless(isset($methods[$resource]), 404);
        abort_unless(in_array($request->method(), $methods[$resource], true), 405);
        abort_if(in_array($request->method(), ['PUT', 'DELETE'], true) && $id === null, 405);
        abort_if($id !== null && (in_array($resource, ['domains', 'cname-check'], true) || $request->isMethod('POST')), 405);
        $payload = $request->isMethod('GET') ? $request->query() : [];
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            if ($resource === 'domains') {
                $payload = $request->validate(['*' => ['required', 'array:id'], '*.id' => ['required', 'integer', 'min:1']]);
                abort_unless(array_is_list($payload) && count($payload) > 0 && count($payload) <= 1000, 422);
            } elseif ($resource === 'cname-check') {
                $payload = $request->validate(['*' => ['required', 'array:domain,cname'], '*.domain' => ['required', 'string', 'max:255'], '*.cname' => ['required', 'string', 'max:255']]);
                abort_unless(count($payload) > 0 && count($payload) <= 1000 && ! array_is_list($payload), 422);
            } else {
                $rules = [
                    'name' => [$request->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
                    'uid' => [$request->isMethod('POST') ? 'required' : 'sometimes', 'integer', 'min:1'],
                    'des' => ['nullable', 'string', 'max:1000'],
                ];
                if ($resource === 'user-configs') {
                    $rules += [
                        'type' => ['required', 'in:site'],
                        'value' => ['sometimes', 'nullable', 'string', 'max:65535'],
                        'scope_name' => ['sometimes', 'in:global,group'],
                        'scope_id' => ['sometimes', 'integer', 'min:0'],
                        'enable' => ['sometimes', 'integer', 'in:0,1'],
                    ];
                }
                if ($resource === 'dnsapis') {
                    $rules += [
                        'type' => [$request->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100'],
                        'auth' => [$request->isMethod('POST') ? 'required' : 'sometimes', 'array', 'min:1'],
                        'auth.*' => ['required', 'string', 'max:4096'],
                    ];
                }
                $payload = $request->validate($rules);
                if ($resource === 'user-configs' && array_key_exists('value', $payload)) {
                    $payload['value'] = $payload['value'] ?? '';
                }
            }
        }
        if ($resource === 'user-configs' && $request->isMethod('GET')) {
            $payload['type'] = 'site';
        }
        try {
            $data = $cdnfly->proxyAdminRequest($request->method(), '/v1/'.$resource.($id === null ? '' : '/'.$id), $payload);
            // Credentials are replaced explicitly in the editor, never echoed in lists.
            if ($resource === 'dnsapis') {
                $data = $this->withoutAuth($data);
            }

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    private function withoutAuth(array $data): array
    {
        unset($data['auth']);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->withoutAuth($value);
            }
        }

        return $data;
    }
}
