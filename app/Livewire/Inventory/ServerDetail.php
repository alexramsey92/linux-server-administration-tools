<?php

namespace App\Livewire\Inventory;

use App\Application;
use App\Jobs\DiscoverServerSslCertificates;
use App\Server;
use App\Service;
use Livewire\Component;

class ServerDetail extends Component
{
    public Server $server;

    public array $serverStats = [];

    public array $applications = [];

    public array $services = [];

    public array $recentMetrics = [];

    public string $quickNotes = '';

    public bool $isEditingNotes = false;

    public array $discoveryCards = [];

    public int $discoveryIntervalSeconds = 15;

    public ?string $discoveryLastCheckedAt = null;

    public bool $isDiscoveringSslCertificates = false;

    public ?string $sslDiscoveryMessage = null;

    public bool $sslDiscoverySuccess = false;

    public function getSplunkQueryProperty(): string
    {
        $queries = [];
        if ($this->server->hostname) {
            $queries[] = "host={$this->server->hostname}";
        }
        if ($this->server->ip_address) {
            $queries[] = "host={$this->server->ip_address}";
        }

        return implode(' OR ', $queries);
    }

    public function getServiceNowSearchUrlProperty(): ?string
    {
        $baseUrl = config('app.tools.servicenow_url');
        if (! $baseUrl) {
            return null;
        }

        return $baseUrl.urlencode($this->server->hostname ?? $this->server->name);
    }

    public function getSplunkSearchUrlProperty(): ?string
    {
        $baseUrl = config('app.tools.splunk_url');
        if (! $baseUrl || ! $this->splunkQuery) {
            return null;
        }

        return $baseUrl.urlencode($this->splunkQuery);
    }

    public function mount(Server $server): void
    {
        $this->server = $server;
        $this->quickNotes = $server->quick_notes ?? '';
        $this->loadServerData();
        $this->refreshDiscoveryStatus();
    }

    public function refreshDiscoveryStatus(): void
    {
        $serviceNowHost = parse_url((string) config('app.tools.servicenow_url'), PHP_URL_HOST);
        $splunkHost = parse_url((string) config('app.tools.splunk_url'), PHP_URL_HOST);

        $serviceNowConfigured = (bool) $this->serviceNowSearchUrl;
        $splunkConfigured = (bool) $this->splunkSearchUrl;
        $machineLinkReady = (bool) ($this->server->hostname && $this->server->ssh_user);

        $serviceNowResolvable = $this->isHostResolvable($serviceNowHost);
        $splunkResolvable = $this->isHostResolvable($splunkHost);
        $machineResolvable = $this->isHostResolvable($this->server->hostname);

        $this->discoveryCards = [
            [
                'name' => 'ServiceNow',
                'description' => 'CMDB / asset lookup',
                'url' => $this->serviceNowSearchUrl,
                'button' => 'Search in ServiceNow',
                'status' => $this->resolveCardStatus($serviceNowConfigured, $serviceNowResolvable),
                'message' => $this->resolveCardMessage($serviceNowConfigured, $serviceNowResolvable, 'ServiceNow URL not configured'),
            ],
            [
                'name' => 'Splunk',
                'description' => 'Logs for host and IP',
                'url' => $this->splunkSearchUrl,
                'button' => 'View in Splunk',
                'status' => $this->resolveCardStatus($splunkConfigured, $splunkResolvable),
                'message' => $this->resolveCardMessage($splunkConfigured, $splunkResolvable, 'Splunk URL not configured'),
            ],
            [
                'name' => 'SSH',
                'description' => 'Direct host access',
                'url' => $machineLinkReady ? "ssh://{$this->server->ssh_user}@{$this->server->hostname}" : null,
                'button' => 'Open SSH Link',
                'status' => $this->resolveCardStatus($machineLinkReady, $machineResolvable),
                'message' => $this->resolveCardMessage($machineLinkReady, $machineResolvable, 'Hostname or SSH user missing'),
            ],
        ];

        $this->discoveryLastCheckedAt = now()->toIso8601String();
    }

    private function isHostResolvable(?string $host): bool
    {
        if (! $host) {
            return false;
        }

        return gethostbyname($host) !== $host;
    }

    private function resolveCardStatus(bool $configured, bool $resolvable): string
    {
        if (! $configured) {
            return 'error';
        }

        if (! $resolvable) {
            return 'warning';
        }

        return 'ready';
    }

    private function resolveCardMessage(bool $configured, bool $resolvable, string $missingConfigText): string
    {
        if (! $configured) {
            return $missingConfigText;
        }

        if (! $resolvable) {
            return 'Host could not be resolved during latest check';
        }

        return 'Ready';
    }

    public function updateQuickNotes(): void
    {
        $this->server->update(['quick_notes' => $this->quickNotes]);
        $this->isEditingNotes = false;
        $this->dispatch('notes-updated');
    }

    public function cancelEditingNotes(): void
    {
        $this->quickNotes = $this->server->quick_notes ?? '';
        $this->isEditingNotes = false;
    }

    public function loadServerData(): void
    {
        $this->serverStats = [
            'cpu_cores' => $this->server->cpu_cores,
            'ram_gb' => $this->server->ram_gb,
            'disk_gb' => $this->server->disk_gb,
            'status' => $this->server->status,
            'environment' => $this->server->environment,
            'os' => $this->server->os,
            'last_health_check' => $this->server->last_health_check?->diffForHumans(),
        ];

        $this->applications = $this->server->applications()
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Application $app) => [
                'id' => $app->id,
                'name' => $app->name,
                'type' => $app->type,
                'version' => $app->version,
                'status' => $app->status,
                'port' => $app->port,
            ])
            ->toArray();

        $this->services = $this->server->services()
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Service $svc) => [
                'id' => $svc->id,
                'name' => $svc->name,
                'service_name' => $svc->service_name,
                'status' => $svc->status,
                'enabled' => $svc->enabled,
            ])
            ->toArray();

        $this->recentMetrics = $this->server->metrics()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($metric) => [
                'created_at' => $metric->created_at->format('H:i'),
                'cpu' => round($metric->cpu_usage_percent, 1),
                'memory' => round($metric->memory_usage_percent, 1),
                'disk' => round($metric->disk_usage_percent, 1),
            ])
            ->toArray();
    }

    public function discoverServerSslCertificates(): void
    {
        $this->isDiscoveringSslCertificates = true;
        $this->sslDiscoveryMessage = null;
        $this->sslDiscoverySuccess = false;

        try {
            DiscoverServerSslCertificates::dispatch($this->server->id);
            $this->sslDiscoverySuccess = true;
            $this->sslDiscoveryMessage = 'SSL discovery job queued for this server.';
        } catch (\Throwable $throwable) {
            $this->sslDiscoverySuccess = false;
            $this->sslDiscoveryMessage = 'SSL discovery failed: '.$throwable->getMessage();
        } finally {
            $this->isDiscoveringSslCertificates = false;
        }
    }

    public function render()
    {
        return view('livewire.inventory.server-detail');
    }
}
