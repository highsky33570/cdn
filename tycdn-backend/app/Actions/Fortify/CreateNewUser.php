<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => 'user',
        ]);

        // 同步到 CDNfly
        try {
            $cdnflyResult = $this->cdnfly->createCdnflyUser(
                $input['name'],
                $input['email'],
                $input['password'],
            );

            $user->cdnfly_user_id = $cdnflyResult['cdnfly_user_id'];

            // 开通 API 密钥
            try {
                $apiKeyResult = $this->cdnfly->enableUserApiKey($cdnflyResult['cdnfly_user_id']);
                $user->cdnfly_api_key = $apiKeyResult['api_key'];
                $user->cdnfly_api_secret = $apiKeyResult['api_secret'];
            } catch (\Throwable $e) {
                Log::warning('Fortify 注册: CDNfly API 密钥开通失败', [
                    'user_id' => $user->id,
                    'cdnfly_user_id' => $cdnflyResult['cdnfly_user_id'],
                    'error' => $e->getMessage(),
                ]);
            }

            $user->cdnfly_synced_at = now();
            $user->save();
        } catch (\Throwable $e) {
            Log::error('Fortify 注册: CDNfly 用户创建失败', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
}
