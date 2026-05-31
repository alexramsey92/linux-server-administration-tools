<?php

namespace App\Livewire\Inventory;

use App\Server;
use App\Services\ServerManagement\VMScanner as VMScannerService;
use Livewire\Component;

class VMScanner extends Component
{
    public string $cidr = '';

    public float $timeout = 0.2;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $results = [];

    /**
     * @var array<int, string>
     */
    public array $selectedIps = [];

    public bool $isScanning = false;

    public int $importedCount = 0;

    /** 'subnet' | 'hostname' | 'wildcard' */
    public string $mode = 'hostname';

    /** Raw textarea input — one hostname per line */
    public string $hostnameInput = '';

    public float $hostnameTimeout = 1.0;

    /** Wildcard IP pattern e.g. 10.162.*.* or 10.162.1-50.* */
    public string $wildcardPattern = '';

    public float $wildcardTimeout = 0.5;

    public int $wildcardMaxHosts = 65536;

    public int $totalHosts = 0;

    protected function rules(): array
    {
        return [
            'cidr' => ['required_if:mode,subnet', 'regex:/^\d{1,3}(\.\d{1,3}){3}\/(1[6-9]|2[0-9]|30)$/'],
            'timeout' => ['required_if:mode,subnet', 'numeric', 'min:0.1', 'max:2'],
            'hostnameInput' => ['required_if:mode,hostname', 'string'],
            'hostnameTimeout' => ['required_if:mode,hostname', 'numeric', 'min:0.1', 'max:10'],
            'wildcardPattern' => ['required_if:mode,wildcard', 'string'],
            'wildcardTimeout' => ['required_if:mode,wildcard', 'numeric', 'min:0.1', 'max:2'],
            'wildcardMaxHosts' => ['required_if:mode,wildcard', 'integer', 'min:1', 'max:65536'],
        ];
    }

    public function scan(): void
    {
        $this->validate();
        $this->isScanning = true;
        $this->results = [];
        $this->selectedIps = [];
        $this->importedCount = 0;

        try {
            $scanner = app(VMScannerService::class);

            if ($this->mode === 'hostname') {
                $lines = array_filter(
                    array_map('trim', explode("\n", $this->hostnameInput)),
                    fn (string $l): bool => $l !== ''
                );
                $this->results = $scanner->scanHostnames(array_values($lines), $this->hostnameTimeout);
            } elseif ($this->mode === 'wildcard') {
                $this->results = $scanner->scanWildcard(
                    $this->wildcardPattern,
                    $this->wildcardTimeout,
                    $this->wildcardMaxHosts,
                    200
                );
                $this->totalHosts = count($this->results);
            } else {
                $this->results = $scanner->scanSubnet($this->cidr, $this->timeout, 256);
            }
        } catch (\Throwable $throwable) {
            $this->addError('scan', $throwable->getMessage());
        } finally {
            $this->isScanning = false;
        }
    }

    public function selectReachable(): void
    {
        $this->selectedIps = collect($this->results)
            ->filter(fn (array $row): bool => (bool) ($row['ssh_reachable'] ?? false) && ! empty($row['ip_address']))
            ->pluck('ip_address')
            ->filter()
            ->values()
            ->all();
    }

    public function clearResults(): void
    {
        $this->results = [];
        $this->selectedIps = [];
        $this->importedCount = 0;
    }

    public function importSelected(): void
    {
        if (empty($this->selectedIps)) {
            $this->addError('import', 'Select at least one VM to import.');

            return;
        }

        $rowsByIp = collect($this->results)->keyBy('ip_address');
        $created = 0;

        foreach ($this->selectedIps as $ipAddress) {
            $row = $rowsByIp->get($ipAddress);

            if (! is_array($row)) {
                continue;
            }

            if (! $ipAddress || ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (Server::query()->where('ip_address', $ipAddress)->exists()) {
                continue;
            }

            $hostname = $row['hostname'] ?? null;
            $domain = $row['domain'] ?? 'example.com';
            $baseName = $this->deriveServerName($hostname, $ipAddress);
            $name = $this->uniqueName($baseName);

            $fullHostname = $hostname ?: "{$name}.{$domain}";

            Server::create([
                'name' => $name,
                'hostname' => $fullHostname,
                'domain' => $domain,
                'ip_address' => $ipAddress,
                'status' => ($row['ssh_reachable'] ?? false) ? 'online' : 'offline',
                'os' => 'oracle',
                'os_version' => '8',
                'cpu_cores' => 1,
                'ram_gb' => 4,
                'ssh_port' => (string) ($row['ssh_port'] ?? 22),
                'ssh_user' => 'root',
                'environment' => $this->inferEnvironment($hostname),
                'description' => 'Imported from VM scan',
                'metadata' => [
                    'scan_source' => $this->scanSource(),
                    'scan_input' => $this->scanInputSummary(),
                    'scanned_at' => now()->toIso8601String(),
                ],
                'last_health_check' => now(),
            ]);

            $created++;
        }

        $this->importedCount = $created;
        $this->dispatch('scan-imported', count: $created);
    }

    private function deriveServerName(?string $hostname, string $ipAddress): string
    {
        if ($hostname && str_contains($hostname, '.')) {
            return explode('.', $hostname)[0];
        }

        if ($hostname) {
            return $hostname;
        }

        return 'vm-'.str_replace('.', '-', $ipAddress);
    }

    private function uniqueName(string $baseName): string
    {
        $candidate = strtolower($baseName);
        $suffix = 1;

        while (Server::query()->where('name', $candidate)->exists()) {
            $candidate = strtolower($baseName).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function inferEnvironment(?string $hostname): string
    {
        if (! $hostname) {
            return 'production';
        }

        $short = strtolower(explode('.', $hostname)[0]);
        $lastCharacter = substr($short, -1);

        return match ($lastCharacter) {
            'd' => 'development',
            't' => 'staging',
            default => 'production',
        };
    }

    private function scanSource(): string
    {
        return match ($this->mode) {
            'hostname' => 'hostname_scan',
            'wildcard' => 'wildcard_scan',
            default => 'subnet_scan',
        };
    }

    private function scanInputSummary(): string
    {
        return match ($this->mode) {
            'hostname' => 'hostnames:'.count(array_filter(array_map('trim', explode("\n", $this->hostnameInput)))),
            'wildcard' => $this->wildcardPattern,
            default => $this->cidr,
        };
    }

    public function render()
    {
        return view('livewire.inventory.v-m-scanner');
    }
}
