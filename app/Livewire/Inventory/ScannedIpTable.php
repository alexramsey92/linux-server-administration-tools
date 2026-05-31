<?php

namespace App\Livewire\Inventory;

use App\Server;
use Livewire\Component;
use Livewire\WithPagination;

class ScannedIpTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterSource = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterSource' => ['except' => ''],
    ];

    public function getDiscoveredCountProperty(): int
    {
        return Server::query()
            ->whereNotNull('metadata->scan_source')
            ->count();
    }

    public function getImportedCountProperty(): int
    {
        return Server::query()
            ->whereNotNull('metadata->scan_source')
            ->count();
    }

    public function getAvailableSourceFiltersProperty(): array
    {
        return Server::query()
            ->whereNotNull('metadata->scan_source')
            ->get(['metadata'])
            ->pluck('metadata.scan_source')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $servers = Server::query()
            ->whereNotNull('metadata->scan_source')
            ->when($this->search, function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('ip_address', 'like', "%{$this->search}%")
                        ->orWhere('hostname', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterSource, fn ($query) => $query->where('metadata->scan_source', $this->filterSource))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.inventory.scanned-ip-table', [
            'servers' => $servers,
        ]);
    }
}
