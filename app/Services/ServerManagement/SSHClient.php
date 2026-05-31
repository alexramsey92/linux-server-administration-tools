<?php

namespace App\Services\ServerManagement;

use App\Server;
use Exception;

class SSHClient
{
    public function __construct(public Server $server) {}

    /**
     * Execute a command on the remote server
     */
    public function execute(string $command): string
    {
        try {
            $ssh = $this->getConnection();

            // Build SSH command
            $sshUser = $this->server->ssh_user ?? 'root';
            $sshPort = $this->server->ssh_port ?? '22';
            $host = $this->server->ip_address;

            $sshCmd = sprintf(
                'ssh -o StrictHostKeyChecking=no -p %s %s@%s "%s" 2>&1',
                escapeshellarg($sshPort),
                escapeshellarg($sshUser),
                escapeshellarg($host),
                escapeshellarg($command)
            );

            $output = shell_exec($sshCmd);

            return $output ?? '';
        } catch (Exception $e) {
            throw new Exception("SSH execution failed: {$e->getMessage()}");
        }
    }

    /**
     * Get system metrics from the server
     */
    public function getSystemMetrics(): array
    {
        $commands = [
            'cpu' => "top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - $1}'",
            'memory' => "free -g | awk 'NR==2{print $3, $2, $7}'",
            'disk' => "df -BG / | awk 'NR==2{print $3, $2, $4}' | sed 's/G//g'",
            'load' => 'cat /proc/loadavg',
            'uptime' => "cat /proc/uptime | awk '{print $1}'",
            'processes' => 'ps aux | wc -l',
        ];

        $metrics = [];
        foreach ($commands as $key => $cmd) {
            try {
                $metrics[$key] = trim($this->execute($cmd));
            } catch (Exception $e) {
                $metrics[$key] = null;
            }
        }

        return $this->parseMetrics($metrics);
    }

    /**
     * Parse raw metrics into structured data
     */
    private function parseMetrics(array $rawMetrics): array
    {
        $memory = explode(' ', $rawMetrics['memory'] ?? '0 0 0');
        $disk = explode(' ', $rawMetrics['disk'] ?? '0 0 0');
        $load = explode(' ', $rawMetrics['load'] ?? '0 0 0');

        return [
            'cpu_usage_percent' => (float) ($rawMetrics['cpu'] ?? 0),
            'memory_used_gb' => (float) ($memory[0] ?? 0),
            'memory_available_gb' => (float) ($memory[2] ?? 0),
            'memory_usage_percent' => $memory[1] > 0 ? ((float) $memory[0] / (float) $memory[1] * 100) : 0,
            'disk_used_gb' => (float) ($disk[0] ?? 0),
            'disk_available_gb' => (float) ($disk[2] ?? 0),
            'disk_usage_percent' => $disk[1] > 0 ? ((float) $disk[0] / (float) $disk[1] * 100) : 0,
            'load_average_1' => (float) ($load[0] ?? 0),
            'load_average_5' => (float) ($load[1] ?? 0),
            'load_average_15' => (float) ($load[2] ?? 0),
            'processes_running' => (int) ($rawMetrics['processes'] ?? 0),
            'uptime_seconds' => (int) ($rawMetrics['uptime'] ?? 0),
        ];
    }

    /**
     * Check if server is online
     */
    public function isOnline(): bool
    {
        try {
            $output = shell_exec(sprintf(
                'timeout 5 ssh -o StrictHostKeyChecking=no -p %s %s@%s "echo OK" 2>&1',
                escapeshellarg($this->server->ssh_port ?? '22'),
                escapeshellarg($this->server->ssh_user ?? 'root'),
                escapeshellarg($this->server->ip_address)
            ));

            return str_contains($output ?? '', 'OK');
        } catch (Exception) {
            return false;
        }
    }

    private function getConnection()
    {
        // This is a placeholder for actual SSH connection logic
        // In production, use a proper SSH library like phpseclib
        return true;
    }
}
