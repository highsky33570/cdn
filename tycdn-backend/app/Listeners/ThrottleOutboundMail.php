<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThrottleOutboundMail
{
    private const MINUTE_KEY = 'mail_throttle:minute';

    private const HOUR_KEY = 'mail_throttle:hour';

    public function handle(MessageSending $event): ?bool
    {
        $perMinute = (int) config('mail.throttle.per_minute', 20);
        $perHour = (int) config('mail.throttle.per_hour', 200);

        $minuteCount = (int) Cache::get(self::MINUTE_KEY, 0);
        $hourCount = (int) Cache::get(self::HOUR_KEY, 0);

        if ($perMinute > 0 && $minuteCount >= $perMinute) {
            Log::warning('outbound mail throttled (per-minute limit)', [
                'limit' => $perMinute,
                'to' => $this->extractRecipients($event),
                'subject' => $event->message->getSubject(),
            ]);

            return false;
        }

        if ($perHour > 0 && $hourCount >= $perHour) {
            Log::warning('outbound mail throttled (per-hour limit)', [
                'limit' => $perHour,
                'to' => $this->extractRecipients($event),
                'subject' => $event->message->getSubject(),
            ]);

            return false;
        }

        Cache::add(self::MINUTE_KEY, 0, 60);
        Cache::increment(self::MINUTE_KEY);

        Cache::add(self::HOUR_KEY, 0, 3600);
        Cache::increment(self::HOUR_KEY);

        $warnMinute = (int) ($perMinute * 0.8);
        $warnHour = (int) ($perHour * 0.8);

        if ($warnMinute > 0 && $minuteCount + 1 === $warnMinute) {
            Log::warning('outbound mail approaching per-minute limit', [
                'count' => $minuteCount + 1,
                'limit' => $perMinute,
            ]);
        }

        if ($warnHour > 0 && $hourCount + 1 === $warnHour) {
            Log::warning('outbound mail approaching per-hour limit', [
                'count' => $hourCount + 1,
                'limit' => $perHour,
            ]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractRecipients(MessageSending $event): array
    {
        $to = $event->message->getTo();

        return array_map(fn ($address) => $address->getAddress(), $to);
    }
}
