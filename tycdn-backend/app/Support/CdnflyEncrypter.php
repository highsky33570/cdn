<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

/**
 * CDNfly 凭证专用加密器。
 *
 * 使用独立的 CDNFLY_ENCRYPTION_KEY 加密 API Key / Secret，
 * 使即使 APP_KEY 泄露也不会暴露 CDNfly 凭证。
 *
 * 如果 CDNFLY_ENCRYPTION_KEY 未配置，回退到 APP_KEY（向下兼容）。
 */
class CdnflyEncrypter
{
    private ?Encrypter $encrypter = null;

    private bool $useDedicatedKey = false;

    public function __construct()
    {
        $key = config('services.cdnfly.encryption_key');

        if ($key && $key !== '') {
            $decoded = base64_decode(str_starts_with($key, 'base64:') ? substr($key, 7) : $key, true);

            if ($decoded !== false && strlen($decoded) >= 16) {
                $cipher = strlen($decoded) === 32 ? 'aes-256-cbc' : 'aes-128-cbc';
                $this->encrypter = new Encrypter($decoded, $cipher);
                $this->useDedicatedKey = true;
            }
        }
    }

    public function usesDedicatedKey(): bool
    {
        return $this->useDedicatedKey;
    }

    public function encrypt(string $value): string
    {
        if ($this->encrypter) {
            return $this->encrypter->encrypt($value);
        }

        return Crypt::encrypt($value);
    }

    public function decrypt(string $payload): string
    {
        // 优先尝试独立密钥解密
        if ($this->encrypter) {
            try {
                return $this->encrypter->decrypt($payload);
            } catch (DecryptException) {
                // 如果独立密钥解密失败，尝试用 APP_KEY 解密（迁移过渡期）
                return Crypt::decrypt($payload);
            }
        }

        return Crypt::decrypt($payload);
    }

    /**
     * 使用 APP_KEY 解密旧数据（用于 re-key 迁移）。
     */
    public function decryptWithAppKey(string $payload): string
    {
        return Crypt::decrypt($payload);
    }
}
