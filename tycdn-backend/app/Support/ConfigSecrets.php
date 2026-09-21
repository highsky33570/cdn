<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ConfigSecrets
{
    public const MASK = '••••••••';

    public static function sensitive(string $name): bool
    {
        return (bool) preg_match('/password|passwd|secret|token|private.?key|https_key|smtp_pass|api.?key|access_key/i', $name);
    }

    public static function containsMask(mixed $value): bool
    {
        if (is_string($value)) {
            if (str_contains($value, self::MASK)) {
                return true;
            }
            $decoded = json_decode($value);

            return (is_array($decoded) || is_object($decoded)) && self::containsMask($decoded);
        }
        if (is_array($value) || is_object($value)) {
            foreach ($value as $item) {
                if (self::containsMask($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function mask(mixed $value, string $name = ''): mixed
    {
        if (self::sensitive($name) && $value !== '' && $value !== null) {
            return self::MASK;
        }
        if (is_string($value)) {
            if (str_contains($value, 'PRIVATE KEY-----')) {
                return self::MASK;
            }
            $decoded = json_decode($value);
            if (is_array($decoded) || is_object($decoded)) {
                return json_encode(self::mask($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if (! is_array($value) && ! is_object($value)) {
            return $value;
        }
        $wasObject = is_object($value);
        $value = (array) $value;
        $result = [];
        foreach ($value as $key => $item) {
            $hint = $key === 'value' && isset($value['name']) ? (string) $value['name'] : (string) $key;
            $result[$key] = self::mask($item, $hint);
        }

        return $wasObject ? (object) $result : $result;
    }

    /** Replace display-only masks using the current server value, never browser secrets. */
    public static function restore(mixed $submitted, mixed $current): mixed
    {
        if ($submitted === self::MASK) {
            if ($current === null || $current === self::MASK) {
                throw ValidationException::withMessages(['value' => '原配置已变更，请重新加载后保存。']);
            }

            return $current;
        }
        if (is_string($submitted)) {
            $decoded = json_decode($submitted);
            if (is_array($decoded) || is_object($decoded)) {
                $old = is_string($current) ? json_decode($current) : $current;

                return json_encode(self::restore($decoded, $old), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if (is_array($submitted) || is_object($submitted)) {
            $wasObject = is_object($submitted);
            $submitted = (array) $submitted;
            $current = is_array($current) || is_object($current) ? (array) $current : [];
            foreach ($submitted as $key => $value) {
                $submitted[$key] = self::restore($value, $current[$key] ?? null);
            }

            return $wasObject ? (object) $submitted : $submitted;
        }

        return $submitted;
    }
}
