<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CdnflyGenerateKey extends Command
{
    protected $signature = 'cdnfly:generate-key
        {--show : Display the key instead of modifying .env}';

    protected $description = 'Generate a dedicated encryption key for CDNfly credentials (CDNFLY_ENCRYPTION_KEY).';

    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if ($this->option('show')) {
            $this->components->info("CDNfly encryption key: {$key}");
            $this->newLine();
            $this->line('Add this to your .env file:');
            $this->line("CDNFLY_ENCRYPTION_KEY={$key}");

            return self::SUCCESS;
        }

        $envPath = $this->laravel->environmentFilePath();
        $envContents = file_get_contents($envPath);

        if ($envContents === false) {
            $this->components->error("Unable to read .env file at: {$envPath}");

            return self::FAILURE;
        }

        if (str_contains($envContents, 'CDNFLY_ENCRYPTION_KEY=') && ! $this->components->confirm(
            'CDNFLY_ENCRYPTION_KEY already exists in .env. Overwrite?',
            false,
        )) {
            $this->components->info('Aborted.');

            return self::SUCCESS;
        }

        if (str_contains($envContents, 'CDNFLY_ENCRYPTION_KEY=')) {
            $envContents = preg_replace(
                '/^CDNFLY_ENCRYPTION_KEY=.*$/m',
                "CDNFLY_ENCRYPTION_KEY={$key}",
                $envContents,
            );
        } else {
            // 插入到 CDNFLY_BASE_URL 之前
            $envContents = preg_replace(
                '/^(#.*CDNFLY.*主控|CDNFLY_BASE_URL)/m',
                "CDNFLY_ENCRYPTION_KEY={$key}\n$1",
                $envContents,
                1,
            );

            // 如果没找到锚点，追加到文件末尾
            if (! str_contains($envContents, "CDNFLY_ENCRYPTION_KEY={$key}")) {
                $envContents .= "\nCDNFLY_ENCRYPTION_KEY={$key}\n";
            }
        }

        file_put_contents($envPath, $envContents);

        $this->components->info('CDNfly encryption key set successfully.');
        $this->components->warn('Run `php artisan cdnfly:rekey` to re-encrypt existing credentials with the new key.');

        return self::SUCCESS;
    }
}
