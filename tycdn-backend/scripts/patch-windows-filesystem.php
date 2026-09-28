<?php

/**
 * Re-apply Windows-safe Filesystem::replace() after composer updates.
 * Laravel's Blade components call `new Filesystem` (not the container),
 * so a container binding alone is not enough on Windows.
 */
$filesystem = __DIR__.'/../vendor/laravel/framework/src/Illuminate/Filesystem/Filesystem.php';

if (! is_file($filesystem)) {
    fwrite(STDERR, "skip windows-filesystem patch: vendor file missing\n");
    exit(0);
}

$contents = file_get_contents($filesystem);

if (str_contains($contents, 'Windows antivirus can lock the destination')) {
    echo "windows-filesystem patch already applied\n";
    exit(0);
}

$search = <<<'PHP'
        file_put_contents($tempPath, $content);

        rename($tempPath, $path);
    }
PHP;

$replace = <<<'PHP'
        file_put_contents($tempPath, $content);

        // Windows antivirus can lock the destination long enough that rename()
        // fails with WinError 32. Prefer rename, then fall back to copy+unlink.
        if (@rename($tempPath, $path)) {
            return;
        }

        for ($i = 0; $i < 5; $i++) {
            usleep(50_000);

            if (@rename($tempPath, $path)) {
                return;
            }
        }

        if (! @copy($tempPath, $path)) {
            @unlink($tempPath);

            throw new \RuntimeException("Unable to write file [{$path}].");
        }

        @unlink($tempPath);
    }
PHP;

if (! str_contains($contents, $search)) {
    fwrite(STDERR, "windows-filesystem patch failed: unexpected Filesystem.php contents\n");
    exit(1);
}

file_put_contents($filesystem, str_replace($search, $replace, $contents));
echo "windows-filesystem patch applied\n";
