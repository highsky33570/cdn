<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Windows Defender (and similar scanners) often lock newly written .php files
 * long enough that Laravel's atomic rename() fails with error 32. Fall back to
 * copy + unlink so Blade/view compilation can complete locally on Windows.
 */
class WindowsSafeFilesystem extends Filesystem
{
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        if (! is_null($mode)) {
            @chmod($tempPath, $mode);
        } else {
            @chmod($tempPath, 0777 - umask());
        }

        file_put_contents($tempPath, $content);

        if (@rename($tempPath, $path)) {
            return;
        }

        if (! @copy($tempPath, $path)) {
            @unlink($tempPath);

            throw new \RuntimeException("Unable to write file [{$path}].");
        }

        @unlink($tempPath);
    }
}
