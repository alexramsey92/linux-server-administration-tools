<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'domain',
        'port',
        'issuer',
        'subject',
        'sans',
        'valid_from',
        'expires_at',
        'status',
        'last_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sans' => 'json',
            'metadata' => 'json',
            'valid_from' => 'datetime',
            'expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $withinDays = 90): bool
    {
        if (! $this->expires_at || $this->isExpired()) {
            return false;
        }

        return $this->expires_at->diffInDays(now()) <= $withinDays;
    }

    public function expiresWithin(int $days): bool
    {
        $daysLeft = $this->getDaysUntilExpiry();

        if ($daysLeft === null) {
            return false;
        }

        return $daysLeft >= 0 && $daysLeft <= $days;
    }
}
