<?php

namespace App\Casts;

use App\Support\CdnflyEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * 使用 CDNfly 专用密钥加解密的 Eloquent Cast。
 *
 * 替代 Laravel 内置 'encrypted' cast，使凭证与 APP_KEY 解耦。
 */
class CdnflyEncrypted implements CastsAttributes
{
    private CdnflyEncrypter $encrypter;

    public function __construct()
    {
        $this->encrypter = app(CdnflyEncrypter::class);
    }

    /**
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->encrypter->decrypt($value);
    }

    /**
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->encrypter->encrypt($value);
    }
}
