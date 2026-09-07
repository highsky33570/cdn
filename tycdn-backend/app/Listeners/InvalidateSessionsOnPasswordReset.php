<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;

class InvalidateSessionsOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $event->user->getAuthIdentifier())
            ->delete();
    }
}
