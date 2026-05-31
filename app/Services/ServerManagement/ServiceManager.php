<?php

namespace App\Services\ServerManagement;

use App\Server;
use App\Service;

class ServiceManager
{
    public function __construct(public Server $server) {}

    /**
     * Start a service
     */
    public function start(Service $service): bool
    {
        try {
            $ssh = new SSHClient($this->server);
            $output = $ssh->execute("sudo systemctl start {$service->service_name}");

            $service->update(['status' => 'running']);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Stop a service
     */
    public function stop(Service $service): bool
    {
        try {
            $ssh = new SSHClient($this->server);
            $output = $ssh->execute("sudo systemctl stop {$service->service_name}");

            $service->update(['status' => 'stopped']);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Restart a service
     */
    public function restart(Service $service): bool
    {
        try {
            $ssh = new SSHClient($this->server);
            $output = $ssh->execute("sudo systemctl restart {$service->service_name}");

            $service->update(['status' => 'running']);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get service status
     */
    public function getStatus(Service $service): array
    {
        try {
            $ssh = new SSHClient($this->server);
            $output = $ssh->execute("sudo systemctl status {$service->service_name}");

            $status = str_contains($output, 'active (running)') ? 'running' : 'stopped';
            $enabled = str_contains($output, 'enabled') ? 'enabled' : 'disabled';

            $service->update([
                'status' => $status,
                'enabled' => $enabled,
                'last_checked' => now(),
            ]);

            return [
                'status' => $status,
                'enabled' => $enabled,
                'output' => $output,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'enabled' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get service logs
     */
    public function getLogs(Service $service, int $lines = 50): string
    {
        try {
            $ssh = new SSHClient($this->server);

            return $ssh->execute("sudo journalctl -u {$service->service_name} -n $lines");
        } catch (\Exception $e) {
            return "Failed to retrieve logs: {$e->getMessage()}";
        }
    }

    /**
     * List all services on the server
     */
    public function listServices(): array
    {
        try {
            $ssh = new SSHClient($this->server);
            $output = $ssh->execute('systemctl list-units --type=service --all --plain --no-pager');

            $services = [];
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 3) {
                    $services[] = [
                        'name' => $parts[0],
                        'status' => $parts[1],
                        'enabled' => $parts[2],
                    ];
                }
            }

            return $services;
        } catch (\Exception) {
            return [];
        }
    }
}
