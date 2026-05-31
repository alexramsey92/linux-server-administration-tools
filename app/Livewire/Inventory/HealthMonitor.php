<?php

namespace App\Livewire\Inventory;

use App\Server;
use App\Services\ServerManagement\HealthMonitor as HealthMonitorService;
use Livewire\Component;

class HealthMonitor extends Component
{
    public Server $server;

    public array $healthSummary = [];

    public array $historyData = [];

    public bool $isLoading = false;

    public function mount(Server $server): void
    {
        $this->server = $server;
        $this->loadHealthData();
    }

    public function loadHealthData(): void
    {
        $this->isLoading = true;

        try {
            $monitor = new HealthMonitorService($this->server);
            $monitor->checkHealth();

            $this->server->refresh();
            $this->healthSummary = $monitor->getHealthSummary();
            $this->historyData = $monitor->getHistory(24);
        } catch (\Exception $e) {
            $this->addError('health', "Failed to load health data: {$e->getMessage()}");
        } finally {
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.inventory.health-monitor');
    }
}
