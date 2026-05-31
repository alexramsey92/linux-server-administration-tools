<?php

namespace App\Jobs;

use App\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverInventorySslCertificates implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 100;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Server::query()
            ->select('id')
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($servers): void {
                foreach ($servers as $server) {
                    DiscoverServerSslCertificates::dispatch((int) $server->id);
                }
            });
    }
}
