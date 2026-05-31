<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'domain',
        'ip_address',
        'status',
        'os',
        'os_version',
        'cpu_cores',
        'cpu_model',
        'ram_gb',
        'ssh_port',
        'ssh_user',
        'environment',
        'description',
        'quick_notes',
        'metadata',
        'last_health_check',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'last_health_check' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SystemMetric::class);
    }

    public function sslCertificates(): HasMany
    {
        return $this->hasMany(SslCertificate::class);
    }

    public function latestMetric()
    {
        return $this->metrics()->latest()->first();
    }

    public function isHealthy(): bool
    {
        return $this->status === 'online';
    }
}
