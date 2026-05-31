<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemMetric extends Model
{
    protected $fillable = [
        'server_id',
        'cpu_usage_percent',
        'memory_usage_percent',
        'memory_used_gb',
        'memory_available_gb',
        'disk_usage_percent',
        'disk_used_gb',
        'disk_available_gb',
        'load_average_1',
        'load_average_5',
        'load_average_15',
        'processes_running',
        'uptime_seconds',
        'network_stats',
        'additional_metrics',
    ];

    protected function casts(): array
    {
        return [
            'network_stats' => 'json',
            'additional_metrics' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function getHealthStatus(): string
    {
        if ($this->cpu_usage_percent > 90 || $this->memory_usage_percent > 90) {
            return 'critical';
        }
        if ($this->cpu_usage_percent > 75 || $this->memory_usage_percent > 75) {
            return 'warning';
        }

        return 'healthy';
    }
}
