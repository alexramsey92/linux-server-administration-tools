<?php

namespace App\Livewire\Intranet;

use App\Server;
use App\SslCertificate;
use Livewire\Component;

class Index extends Component
{
    public function getOfflineServersCountProperty(): int
    {
        return Server::query()
            ->where('status', 'offline')
            ->count();
    }

    public function getUnknownStatusServersCountProperty(): int
    {
        return Server::query()
            ->whereIn('status', ['unknown', 'maintenance'])
            ->count();
    }

    public function getExpiredCertificatesCountProperty(): int
    {
        return SslCertificate::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
    }

    public function getExpiringCertificatesCountProperty(): int
    {
        return SslCertificate::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->copy()->addDays(30)])
            ->count();
    }

    public function render()
    {
        return view('livewire.intranet.index');
    }
}
