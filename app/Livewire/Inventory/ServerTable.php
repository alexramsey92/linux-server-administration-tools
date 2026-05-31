<?php

namespace App\Livewire\Inventory;

use App\Server;
use Livewire\Component;
use Livewire\WithPagination;

class ServerTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterSubnet = '';

    public string $filterStatus = '';

    public string $filterEnvironment = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public ?int $deleteServerId = null;

    public bool $confirmDelete = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterSubnet' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterEnvironment' => ['except' => ''],
        'sortBy' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function getTotalServerCountProperty(): int
    {
        return Server::query()->count();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSubnet(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEnvironment(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterSubnet = '';
        $this->filterStatus = '';
        $this->filterEnvironment = '';

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

    public function exportCsv()
    {
        $servers = Server::query()
            ->when($this->search, function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery->where('name', 'like', "%{$this->search}%")
                        ->orWhere('hostname', 'like', "%{$this->search}%")
                        ->orWhere('ip_address', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterSubnet, fn ($query) => $query->where('ip_address', 'like', str_replace('*', '%', $this->filterSubnet)))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterEnvironment, fn ($q) => $q->where('environment', $this->filterEnvironment))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=servers-export-'.now()->format('Y-m-d-His').'.csv',
        ];

        $callback = function () use ($servers) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, ['Name', 'Hostname', 'IP Address', 'Status', 'Environment', 'OS', 'OS Version', 'CPU Cores', 'RAM (GB)', 'Disk (GB)', 'SSH Port', 'SSH User', 'Last Health Check']);

            // Write data rows
            foreach ($servers as $server) {
                fputcsv($file, [
                    $server->name,
                    $server->hostname,
                    $server->ip_address,
                    ucfirst($server->status),
                    ucfirst($server->environment),
                    $server->os,
                    $server->os_version,
                    $server->cpu_cores,
                    $server->ram_gb,
                    $server->disk_gb,
                    $server->ssh_port,
                    $server->ssh_user,
                    $server->last_health_check?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function confirmDeleteServer(int $serverId): void
    {
        $this->deleteServerId = $serverId;
        $this->confirmDelete = true;
    }

    public function deleteServer(): void
    {
        if ($this->deleteServerId) {
            Server::find($this->deleteServerId)?->delete();
            $this->deleteServerId = null;
            $this->confirmDelete = false;
            $this->dispatch('server-deleted');
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteServerId = null;
        $this->confirmDelete = false;
    }

    public function render()
    {
        $availableSubnets = Server::query()
            ->pluck('ip_address')
            ->filter()
            ->map(fn (string $ipAddress): ?string => $this->subnetPatternForIpAddress($ipAddress))
            ->filter()
            ->unique()
            ->sort(function (string $firstSubnet, string $secondSubnet): int {
                return $this->compareIpAddresses(
                    str_replace('*', '0', $firstSubnet),
                    str_replace('*', '0', $secondSubnet)
                );
            })
            ->values();

        $servers = Server::query()
            ->when($this->search, function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery->where('name', 'like', "%{$this->search}%")
                        ->orWhere('hostname', 'like', "%{$this->search}%")
                        ->orWhere('ip_address', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterSubnet, fn ($query) => $query->where('ip_address', 'like', str_replace('*', '%', $this->filterSubnet)))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterEnvironment, fn ($q) => $q->where('environment', $this->filterEnvironment))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);

        return view('livewire.inventory.server-table', [
            'servers' => $servers,
            'availableSubnets' => $availableSubnets,
        ]);
    }

    private function subnetPatternForIpAddress(string $ipAddress): ?string
    {
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $octets = explode('.', $ipAddress);

        return sprintf('%s.%s.%s.*', $octets[0], $octets[1], $octets[2]);
    }

    private function compareIpAddresses(string $firstIp, string $secondIp): int
    {
        $firstBinaryIp = @inet_pton($firstIp);
        $secondBinaryIp = @inet_pton($secondIp);

        if ($firstBinaryIp === false || $secondBinaryIp === false) {
            return strnatcasecmp($firstIp, $secondIp);
        }

        return strcmp($firstBinaryIp, $secondBinaryIp);
    }
}
