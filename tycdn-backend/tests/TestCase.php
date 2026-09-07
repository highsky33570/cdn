<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Portal login URL. Pass the path the guest was originally after to get the
     * ?redirect= form that the unauthenticated handler produces.
     */
    protected function frontendLoginUrl(?string $intended = null): string
    {
        $url = $this->frontendUrl('/login');

        return $intended === null ? $url : $url.'?redirect='.urlencode($intended);
    }

    protected function frontendUrl(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
