<?php

use App\Jobs\DiscoverInventorySslCertificates;
use App\Jobs\DiscoverServerSslCertificates;
use App\Server;
use Illuminate\Support\Facades\Artisan;

Artisan::command('ssl:discover-server {serverId} {--sync}', function (int $serverId): void {
    if ($this->option('sync')) {
        $result = (new DiscoverServerSslCertificates($serverId))->handle();

        $this->info('SSL discovery completed (sync mode).');
        $this->line('Checked: '.($result['checked'] ?? 0));
        $this->line('Created: '.($result['created'] ?? 0));
        $this->line('Updated: '.($result['updated'] ?? 0));

        $errors = $result['errors'] ?? [];

        if (count($errors) > 0) {
            $this->warn('Errors:');
            foreach ($errors as $error) {
                $this->line("- {$error}");
            }
        }

        return;
    }

    DiscoverServerSslCertificates::dispatch($serverId);
    $this->info("Queued SSL discovery for server {$serverId}.");
    $this->line('Run `php artisan queue:work` to process queued jobs.');
})->purpose('Discover and save SSL certificates for a server using cURL');

Artisan::command('ssl:discover-all {--sync}', function (): void {
    if ($this->option('sync')) {
        $servers = Server::query()->select('id')->orderBy('id')->get();
        $total = $servers->count();

        foreach ($servers as $index => $server) {
            (new DiscoverServerSslCertificates((int) $server->id))->handle();
            $this->line('Processed '.($index + 1)."/{$total} server(s)");
        }

        $this->info("Completed SSL discovery across {$total} server(s) in sync mode.");

        return;
    }

    DiscoverInventorySslCertificates::dispatch();
    $this->info('Queued inventory-wide SSL discovery.');
    $this->line('Run `php artisan queue:work` to process queued jobs.');
})->purpose('Queue SSL discovery across entire inventory');
