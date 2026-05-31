<?php

namespace App\Services\ServerManagement;

use App\Server;
use App\SystemMetric;

class HealthMonitor
{
    public function __construct(public Server $server) {}

    /**
     * Check server health and record metrics
     */
    public function checkHealth(): SystemMetric
    {
        $ssh = new SSHClient($this->server);
        $metrics = $ssh->getSystemMetrics();

        $metric = SystemMetric::create([
            'server_id' => $this->server->id,
            ...$metrics,
        ]);

        // Update server status
        $this->server->update([
            'status' => $ssh->isOnline() ? 'online' : 'offline',
            'last_health_check' => now(),
        ]);

        return $metric;
    }

    /**
     * Get health status summary
     */
    public function getHealthSummary(): array
    {
        $latestMetric = $this->server->latestMetric();

        if (! $latestMetric) {
            return ['status' => 'unknown', 'message' => 'No metrics available'];
        }

        return [
            'status' => $latestMetric->getHealthStatus(),
            'cpu_usage' => round($latestMetric->cpu_usage_percent, 2),
            'memory_usage' => round($latestMetric->memory_usage_percent, 2),
            'disk_usage' => round($latestMetric->disk_usage_percent, 2),
            'memory_available_gb' => round($latestMetric->memory_available_gb, 2),
            'disk_available_gb' => round($latestMetric->disk_available_gb, 2),
            'uptime_days' => floor($latestMetric->uptime_seconds / 86400),
        ];
    }

    /**
     * Get metric history
     */
    public function getHistory(int $hours = 24): array
    {
        return $this->server->metrics()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'time' => $m->created_at->format('H:i'),
                'cpu' => $m->cpu_usage_percent,
                'memory' => $m->memory_usage_percent,
                'disk' => $m->disk_usage_percent,
            ])
            ->toArray();
    }
}
