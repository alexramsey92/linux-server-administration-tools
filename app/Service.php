<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'service_name',
        'status',
        'enabled',
        'description',
        'path',
        'port',
        'metadata',
        'last_checked',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'last_checked' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isEnabled(): bool
    {
        return $this->enabled === 'enabled';
    }
}
