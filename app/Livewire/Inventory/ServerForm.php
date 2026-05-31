<?php

namespace App\Livewire\Inventory;

use App\Server;
use Livewire\Component;

class ServerForm extends Component
{
    public ?Server $server = null;

    public string $fullHostname = '';

    public string $hostname = '';

    public string $domain = 'example.com';

    public string $name = '';

    public string $ip_address = '';

    public string $status = 'offline';

    public string $environment = 'production';

    public string $os = 'oracle';

    public string $os_version = '8';

    public int $cpu_cores = 1;

    public string $cpu_model = '';

    public int $ram_gb = 4;

    public int $ssh_port = 22;

    public string $ssh_user = 'root';

    public ?string $description = null;

    public bool $isDiscovering = false;

    public ?string $discoveryMessage = null;

    public ?bool $discoverySuccess = null;

    public array $domainOptions = [
        'example.com' => '*.example.com',
        'internal.example.com' => '*.internal.example.com',
        'dev.example.com' => '*.dev.example.com',
        'staging.example.com' => '*.staging.example.com',
        'example.org' => '*.example.org',
    ];

    public function getFullHostnameProperty(): string
    {
        return $this->hostname && $this->domain ? $this->hostname.'.'.$this->domain : '';
    }

    public function getSplunkQueryProperty(): string
    {
        if (! $this->fullHostname && ! $this->ip_address) {
            return '';
        }

        $queries = [];
        if ($this->fullHostname) {
            $queries[] = "host={$this->fullHostname}";
        }
        if ($this->ip_address) {
            $queries[] = "host={$this->ip_address}";
        }

        return implode(' OR ', $queries);
    }

    public function getServiceNowSearchUrlProperty(): ?string
    {
        $baseUrl = config('app.tools.servicenow_url');
        if (! $baseUrl || ! ($this->fullHostname || $this->name)) {
            return null;
        }

        $query = $this->fullHostname ?: $this->name;

        return $baseUrl.urlencode($query);
    }

    public function getSplunkSearchUrlProperty(): ?string
    {
        $baseUrl = config('app.tools.splunk_url');
        if (! $baseUrl || ! $this->splunkQuery) {
            return null;
        }

        return $baseUrl.urlencode($this->splunkQuery);
    }

    public function getMachineAccessUrlProperty(): ?string
    {
        if (! $this->fullHostname || ! $this->ssh_user) {
            return null;
        }

        return "ssh://{$this->ssh_user}@{$this->fullHostname}";
    }

    public function mount(?Server $server = null): void
    {
        if ($server) {
            [$hostname, $detectedDomain] = array_pad(explode('.', $server->hostname, 2), 2, null);

            $this->server = $server;
            $this->hostname = $hostname ?? '';
            $this->name = $server->name ?? ($hostname ?? '');
            $this->domain = $server->domain ?: ($detectedDomain ?? $this->domain);
            $this->ip_address = $server->ip_address;
            $this->status = $server->status;
            $this->environment = $server->environment;
            $this->os = $server->os;
            $this->os_version = $server->os_version;
            $this->cpu_cores = (int) ($server->cpu_cores ?? 1);
            $this->cpu_model = $server->cpu_model ?? '';
            $this->ram_gb = (int) ($server->ram_gb ?? 4);
            $this->ssh_port = (int) ($server->ssh_port ?? 22);
            $this->ssh_user = $server->ssh_user ?? 'root';
            $this->description = $server->description ?? '';
        }
    }

    public function updatedHostname(): void
    {
        if ($this->hostname) {
            // Extract the machine name (before any dot)
            $parts = explode('.', $this->hostname);
            $this->name = reset($parts);

            // Auto-detect environment from hostname tier letter (last character)
            $this->detectEnvironmentFromHostname();
        }
    }

    public function updatedDomain(): void
    {
        if ($this->hostname && $this->domain) {
            $parts = explode('.', $this->hostname);
            $this->name = reset($parts);
        }
    }

    private function detectEnvironmentFromHostname(): void
    {
        if (! $this->hostname) {
            return;
        }

        // Get the last character of the hostname (before any domain)
        $hostnamePart = strtolower(explode('.', $this->hostname)[0]);
        $lastChar = substr($hostnamePart, -1);

        // Map tier letters to environment
        $tierMap = [
            'd' => 'development',
            't' => 'staging',
            'p' => 'production',
        ];

        if (isset($tierMap[$lastChar])) {
            $this->environment = $tierMap[$lastChar];
        } else {
            // Default to production if no recognized tier letter
            $this->environment = 'production';
        }
    }

    public function getHostnameValidationMessage(): string
    {
        if (! $this->hostname) {
            return '';
        }

        $hostnamePart = strtolower(explode('.', $this->hostname)[0]);
        $lastChar = substr($hostnamePart, -1);

        return match ($lastChar) {
            'd' => '✓ Hostname ends with D (Development)',
            't' => '✓ Hostname ends with T (Test/Staging)',
            'p' => '✓ Hostname ends with P (Production)',
            default => '(No tier letter detected - defaulting to Production)',
        };
    }

    public function discoverHostname(): void
    {
        if (! $this->hostname || ! $this->domain) {
            $this->discoveryMessage = 'Please enter hostname and select a domain first';
            $this->discoverySuccess = false;

            return;
        }

        $this->isDiscovering = true;
        $this->discoveryMessage = 'Discovering IP address...';
        $this->discoverySuccess = false;

        try {
            $fullHostname = $this->hostname.'.'.$this->domain;

            // Try DNS resolution
            $ipAddress = $this->resolveHostname($fullHostname);

            if ($ipAddress) {
                $this->ip_address = $ipAddress;
                $this->status = 'online';
                $this->discoveryMessage = "✓ Resolved to {$ipAddress}";
                $this->discoverySuccess = true;
            } else {
                $this->status = 'offline';
                $this->discoveryMessage = "✗ Could not resolve {$fullHostname}";
                $this->discoverySuccess = false;
            }
        } catch (\Exception $e) {
            $this->status = 'unknown';
            $this->discoveryMessage = "✗ Discovery failed: {$e->getMessage()}";
            $this->discoverySuccess = false;
        } finally {
            $this->isDiscovering = false;
        }
    }

    private function resolveHostname(string $hostname): ?string
    {
        // Try gethostbyname (pure PHP)
        $ip = gethostbyname($hostname);

        if ($ip !== $hostname && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Try dig command if available
        if ($this->commandExists('dig')) {
            $output = shell_exec("dig +short {$hostname} @8.8.8.8 2>/dev/null");
            $lines = array_filter(array_map('trim', explode("\n", $output ?? '')));
            foreach ($lines as $line) {
                if (filter_var($line, FILTER_VALIDATE_IP)) {
                    return $line;
                }
            }
        }

        // Try nslookup if available
        if ($this->commandExists('nslookup')) {
            $output = shell_exec("nslookup {$hostname} 2>/dev/null");
            if (preg_match('/Address:\s+(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                return $matches[1];
            }
        }

        // Try host command if available
        if ($this->commandExists('host')) {
            $output = shell_exec("host {$hostname} 2>/dev/null");
            if (preg_match('/has address (\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function commandExists(string $command): bool
    {
        $command = escapeshellcmd($command);

        return shell_exec("command -v {$command}") !== null;
    }

    public function save(): void
    {
        $this->validate([
            'hostname' => 'required|string|max:255',
            'domain' => 'required|in:'.implode(',', array_keys($this->domainOptions)),
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'status' => 'required|in:online,offline,maintenance,error',
            'environment' => 'required|string|max:255',
            'os' => 'required|in:oracle',
            'os_version' => 'required|in:7,8,9,10',
        ]);

        $fullHostname = $this->hostname.'.'.$this->domain;

        if ($this->server) {
            $this->server->update([
                'name' => $this->name,
                'hostname' => $fullHostname,
                'ip_address' => $this->ip_address,
                'status' => $this->status,
                'environment' => $this->environment,
                'os' => $this->os,
                'os_version' => $this->os_version,
                'cpu_cores' => $this->cpu_cores,
                'cpu_model' => $this->cpu_model,
                'ram_gb' => $this->ram_gb,
                'ssh_port' => $this->ssh_port,
                'ssh_user' => $this->ssh_user,
                'description' => $this->description,
            ]);

            $this->dispatch('server-updated');
            $this->redirectRoute('inventory.index');
        } else {
            Server::create([
                'name' => $this->name,
                'hostname' => $fullHostname,
                'domain' => $this->domain,
                'ip_address' => $this->ip_address,
                'status' => $this->status,
                'environment' => $this->environment,
                'os' => $this->os,
                'os_version' => $this->os_version,
                'cpu_cores' => $this->cpu_cores,
                'cpu_model' => $this->cpu_model,
                'ram_gb' => $this->ram_gb,
                'ssh_port' => $this->ssh_port,
                'ssh_user' => $this->ssh_user,
                'description' => $this->description ?? '',
            ]);

            $this->dispatch('server-created');
            $this->redirectRoute('inventory.index');
        }
    }

    public function render()
    {
        return view('livewire.inventory.server-form');
    }
}
