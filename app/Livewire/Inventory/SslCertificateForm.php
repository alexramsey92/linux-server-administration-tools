<?php

namespace App\Livewire\Inventory;

use App\Server;
use App\Services\ServerManagement\SslChecker;
use App\SslCertificate;
use Livewire\Component;

class SslCertificateForm extends Component
{
    public ?SslCertificate $certificate = null;

    public ?int $server_id = null;

    public string $domain = '';

    public int $port = 443;

    public bool $isChecking = false;

    public ?string $checkMessage = null;

    public ?bool $checkSuccess = null;

    // Auto-populated from discovery
    public ?string $issuer = null;

    public ?string $subject = null;

    public ?string $valid_from = null;

    public ?string $expires_at = null;

    public ?string $status = null;

    /** @var array<int, string> */
    public array $sans = [];

    public function mount(?SslCertificate $certificate = null): void
    {
        if ($certificate && $certificate->exists) {
            $this->certificate = $certificate;
            $this->server_id = $certificate->server_id;
            $this->domain = $certificate->domain;
            $this->port = $certificate->port;
            $this->issuer = $certificate->issuer;
            $this->subject = $certificate->subject;
            $this->sans = $certificate->sans ?? [];
            $this->valid_from = $certificate->valid_from?->format('Y-m-d H:i:s');
            $this->expires_at = $certificate->expires_at?->format('Y-m-d H:i:s');
            $this->status = $certificate->status;
        }

        // Allow pre-selection from ?server= query parameter (e.g. from ServerDetail)
        $serverId = request()->query('server');
        if ($serverId && ! $this->server_id) {
            $this->server_id = (int) $serverId;
        }
    }

    public function discoverCertificate(): void
    {
        $this->validate([
            'domain' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
        ]);

        $this->isChecking = true;
        $this->checkMessage = null;
        $this->checkSuccess = null;

        try {
            $certData = SslChecker::fetchCertificate($this->domain, $this->port);

            $this->issuer = $certData['issuer'];
            $this->subject = $certData['subject'];
            $this->sans = $certData['sans'];
            $this->valid_from = $certData['valid_from'];
            $this->expires_at = $certData['expires_at'];
            $this->status = $this->resolveStatus($certData['expires_at']);

            $this->checkMessage = '✓ Certificate found and populated';
            $this->checkSuccess = true;
        } catch (\Exception $e) {
            $this->checkMessage = '✗ '.$e->getMessage();
            $this->checkSuccess = false;
        } finally {
            $this->isChecking = false;
        }
    }

    public function save(): void
    {
        $this->validate([
            'server_id' => 'required|exists:servers,id',
            'domain' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
        ]);

        $data = [
            'server_id' => $this->server_id,
            'domain' => $this->domain,
            'port' => $this->port,
            'issuer' => $this->issuer,
            'subject' => $this->subject,
            'sans' => $this->sans,
            'valid_from' => $this->valid_from,
            'expires_at' => $this->expires_at,
            'status' => $this->status ?? 'unknown',
            'last_checked_at' => $this->checkSuccess ? now() : ($this->certificate?->last_checked_at),
        ];

        if ($this->certificate && $this->certificate->exists) {
            $this->certificate->update($data);
        } else {
            SslCertificate::create($data);
        }

        $this->redirectRoute('inventory.ssl.index');
    }

    private function resolveStatus(?string $expiresAt): string
    {
        if (! $expiresAt) {
            return 'unknown';
        }

        $expiry = \Carbon\Carbon::parse($expiresAt);

        if ($expiry->isPast()) {
            return 'expired';
        }

        if ($expiry->diffInDays(now()) <= 90) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    public function render()
    {
        $servers = Server::query()->orderBy('name')->get();

        return view('livewire.inventory.ssl-certificate-form', [
            'servers' => $servers,
        ]);
    }
}
