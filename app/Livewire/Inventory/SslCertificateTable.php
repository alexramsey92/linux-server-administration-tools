<?php

namespace App\Livewire\Inventory;

use App\Jobs\DiscoverInventorySslCertificates;
use App\Server;
use App\Services\ServerManagement\SslChecker;
use App\SslCertificate;
use Livewire\Component;
use Livewire\WithPagination;

class SslCertificateTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public int $filterServerId = 0;

    public string $sortBy = 'expires_at';

    public string $sortDirection = 'asc';

    public ?int $deleteCertificateId = null;

    public bool $confirmDelete = false;

    public bool $isCheckingAll = false;

    public bool $isQueueingInventoryDiscovery = false;

    public ?string $inventoryDiscoveryMessage = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterServerId' => ['except' => 0],
        'sortBy' => ['except' => 'expires_at'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterServerId(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function checkCertificate(int $certificateId): void
    {
        $certificate = SslCertificate::find($certificateId);

        if (! $certificate) {
            return;
        }

        $checker = new SslChecker($certificate);
        $checker->check();
    }

    public function queueInventoryDiscovery(): void
    {
        $this->isQueueingInventoryDiscovery = true;
        $this->inventoryDiscoveryMessage = null;

        try {
            DiscoverInventorySslCertificates::dispatch();
            $this->inventoryDiscoveryMessage = 'Inventory SSL discovery queued. Start a queue worker to process jobs.';
        } catch (\Throwable $throwable) {
            $this->inventoryDiscoveryMessage = 'Failed to queue inventory discovery: '.$throwable->getMessage();
        } finally {
            $this->isQueueingInventoryDiscovery = false;
        }
    }

    public function confirmDeleteCertificate(int $certificateId): void
    {
        $this->deleteCertificateId = $certificateId;
        $this->confirmDelete = true;
    }

    public function deleteCertificate(): void
    {
        if ($this->deleteCertificateId) {
            SslCertificate::find($this->deleteCertificateId)?->delete();
            $this->deleteCertificateId = null;
            $this->confirmDelete = false;
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteCertificateId = null;
        $this->confirmDelete = false;
    }

    public function exportCsv()
    {
        $certificates = $this->buildQuery()->with('server')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=ssl-certificates-'.now()->format('Y-m-d-His').'.csv',
        ];

        $callback = function () use ($certificates) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Server',
                'Domain',
                'Port',
                'Issuer',
                'Subject',
                'SANs',
                'Valid From',
                'Expires At',
                'Days Until Expiry',
                'Status',
                'Expires Within 30 Days',
                'Expires Within 60 Days',
                'Expires Within 90 Days',
                'Last Checked',
            ]);

            foreach ($certificates as $cert) {
                $daysLeft = $cert->getDaysUntilExpiry();
                fputcsv($file, [
                    $cert->server?->name ?? 'N/A',
                    $cert->domain,
                    $cert->port,
                    $cert->issuer ?? '',
                    $cert->subject ?? '',
                    implode('; ', $cert->sans ?? []),
                    $cert->valid_from?->format('Y-m-d H:i:s') ?? '',
                    $cert->expires_at?->format('Y-m-d H:i:s') ?? '',
                    $daysLeft !== null ? $daysLeft : '',
                    ucfirst(str_replace('_', ' ', $cert->status)),
                    $cert->expiresWithin(30) ? 'Yes' : 'No',
                    $cert->expiresWithin(60) ? 'Yes' : 'No',
                    $cert->expiresWithin(90) ? 'Yes' : 'No',
                    $cert->last_checked_at?->format('Y-m-d H:i:s') ?? 'Never',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SslCertificate>
     */
    private function buildQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return SslCertificate::query()
            ->with('server')
            ->when($this->search, fn ($q) => $q->where('domain', 'like', "%{$this->search}%")
                ->orWhere('issuer', 'like', "%{$this->search}%")
                ->orWhere('subject', 'like', "%{$this->search}%")
                ->orWhereHas('server', fn ($sq) => $sq->where('name', 'like', "%{$this->search}%")))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterServerId, fn ($q) => $q->where('server_id', $this->filterServerId))
            ->orderBy($this->sortBy, $this->sortDirection);
    }

    public function render()
    {
        $certificates = $this->buildQuery()->paginate(15);
        $servers = Server::query()->orderBy('name')->pluck('name', 'id');

        $expiringSoon30 = SslCertificate::query()->where('expires_at', '>=', now())->where('expires_at', '<=', now()->addDays(30))->count();
        $expiringSoon60 = SslCertificate::query()->where('expires_at', '>=', now())->where('expires_at', '<=', now()->addDays(60))->count();
        $expiringSoon90 = SslCertificate::query()->where('expires_at', '>=', now())->where('expires_at', '<=', now()->addDays(90))->count();

        return view('livewire.inventory.ssl-certificate-table', [
            'certificates' => $certificates,
            'servers' => $servers,
            'expiringSoon30' => $expiringSoon30,
            'expiringSoon60' => $expiringSoon60,
            'expiringSoon90' => $expiringSoon90,
        ]);
    }
}
