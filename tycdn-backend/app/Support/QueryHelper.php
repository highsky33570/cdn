<?php

namespace App\Support;

class QueryHelper
{
    /**
     * 转义 LIKE 查询中的通配符 % 和 _，防止用户输入被解释为通配符。
     *
     * 例如：escapeLike('100%') => '100\%'
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\%', '\_'],
            $value,
        );
    }
}
