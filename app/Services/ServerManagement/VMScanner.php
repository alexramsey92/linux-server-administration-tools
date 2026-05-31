<?php

namespace App\Services\ServerManagement;

use InvalidArgumentException;

class VMScanner
{
    /**
     * Scan a CIDR block for reachable SSH hosts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanSubnet(string $cidr, float $timeout = 0.2, int $maxHosts = 256): array
    {
        if (! $this->isValidCidr($cidr)) {
            throw new InvalidArgumentException('Invalid CIDR format. Example: 192.168.1.0/24');
        }

        [$start, $end] = $this->cidrToRange($cidr);

        if (($end - $start + 1) > $maxHosts) {
            throw new InvalidArgumentException("CIDR range too large. Limit is {$maxHosts} hosts per scan.");
        }

        $results = [];

        for ($ipLong = $start; $ipLong <= $end; $ipLong++) {
            $ip = long2ip($ipLong);

            if ($ip === false) {
                continue;
            }

            $hostname = @gethostbyaddr($ip);
            $hostname = $hostname !== false && $hostname !== $ip ? strtolower($hostname) : null;

            $isSshReachable = $this->isPortOpen($ip, 22, $timeout);

            $results[] = [
                'ip_address' => $ip,
                'hostname' => $hostname,
                'domain' => $this->extractDomain($hostname),
                'ssh_port' => 22,
                'ssh_reachable' => $isSshReachable,
                'status' => $isSshReachable ? 'online' : 'offline',
            ];
        }

        return $results;
    }

    /**
     * Validate CIDR input.
     */
    private function isValidCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$ip, $mask] = explode('/', $cidr, 2);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $maskValue = (int) $mask;

        return $maskValue >= 16 && $maskValue <= 30;
    }

    /**
     * Convert CIDR to host range.
     *
     * @return array{0:int,1:int}
     */
    private function cidrToRange(string $cidr): array
    {
        [$ip, $mask] = explode('/', $cidr, 2);

        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            throw new InvalidArgumentException('Unable to parse IP address from CIDR.');
        }

        $maskInt = (int) $mask;
        $networkMask = -1 << (32 - $maskInt);
        $network = $ipLong & $networkMask;
        $broadcast = $network + (~$networkMask & 0xFFFFFFFF);

        $start = $network + 1;
        $end = $broadcast - 1;

        if ($start > $end) {
            $start = $network;
            $end = $broadcast;
        }

        return [$start, $end];
    }

    /**
     * Check if a single TCP port is open (used by hostname/subnet modes).
     */
    private function isPortOpen(string $ipAddress, int $port, float $timeout): bool
    {
        $ctx = @stream_socket_client(
            "tcp://{$ipAddress}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (is_resource($ctx)) {
            fclose($ctx);

            return true;
        }

        return false;
    }

    /**
     * Expand a wildcard IP pattern and scan all matching addresses using
     * parallel non-blocking sockets so large ranges finish quickly.
     * Accepts: 10.162.*.*, 10.162.5.*, 10.162.1-20.*, 10.162.5.1-50
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanWildcard(
        string $pattern,
        float $timeout = 0.5,
        int $maxHosts = 65536,
        int $concurrency = 200,
        int $port = 22
    ): array {
        set_time_limit(0);

        $ips = $this->expandWildcard($pattern, $maxHosts);

        if (empty($ips)) {
            throw new InvalidArgumentException('No valid IPs generated from that pattern. Check format (e.g. 10.162.*.* or 10.162.5.1-50).');
        }

        // Probe all IPs concurrently in chunks
        $reachable = $this->parallelPortScan($ips, $port, $timeout, $concurrency);

        $results = [];

        foreach ($ips as $ip) {
            $isSshReachable = $reachable[$ip] ?? false;

            // Only do reverse-DNS on reachable hosts to keep things fast
            $hostname = null;
            if ($isSshReachable) {
                $resolved = @gethostbyaddr($ip);
                $hostname = ($resolved !== false && $resolved !== $ip) ? strtolower($resolved) : null;
            }

            $results[] = [
                'ip_address' => $ip,
                'hostname' => $hostname,
                'domain' => $this->extractDomain($hostname),
                'ssh_port' => $port,
                'ssh_reachable' => $isSshReachable,
                'status' => $isSshReachable ? 'online' : 'offline',
            ];
        }

        return $results;
    }

    /**
     * Probe a list of IPs against a TCP port in parallel using non-blocking
     * stream_socket_client + stream_select.
     *
     * @param  array<int, string>  $ips
     * @return array<string, bool> keyed by IP
     */
    private function parallelPortScan(array $ips, int $port, float $timeout, int $concurrency): array
    {
        $reachable = [];
        $pending = [];   // int(socket_id) => ['sock'=>resource, 'ip'=>string, 'opened_at'=>float]
        $queue = $ips;

        $fill = function () use (&$queue, &$pending, $port, $concurrency): void {
            while (count($pending) < $concurrency && ! empty($queue)) {
                $ip = array_shift($queue);
                $ctx = @stream_socket_client(
                    "tcp://{$ip}:{$port}",
                    $errno,
                    $errstr,
                    0,
                    STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT
                );
                if (is_resource($ctx)) {
                    stream_set_blocking($ctx, false);
                    $pending[(int) $ctx] = ['sock' => $ctx, 'ip' => $ip, 'opened_at' => microtime(true)];
                } else {
                    $reachable[$ip] = false;
                }
            }
        };

        $fill();

        while (! empty($pending)) {
            $read = [];
            $write = array_column($pending, 'sock');
            $except = null;

            // Short poll interval — collect whatever is ready without blocking long
            $pollUsec = min(50_000, (int) ($timeout * 1_000_000));
            @stream_select($read, $write, $except, 0, $pollUsec);

            // Writable = connection succeeded
            foreach ($write as $sock) {
                $key = (int) $sock;
                if (! isset($pending[$key])) {
                    continue;
                }
                $reachable[$pending[$key]['ip']] = true;
                @fclose($sock);
                unset($pending[$key]);
            }

            // Expire sockets that have been open longer than the timeout
            $now = microtime(true);
            foreach ($pending as $key => $entry) {
                if (($now - $entry['opened_at']) >= $timeout) {
                    $reachable[$entry['ip']] = false;
                    @fclose($entry['sock']);
                    unset($pending[$key]);
                }
            }

            $fill();
        }

        // Fill in any IPs that never got a socket (OS limit hit, etc.)
        foreach ($ips as $ip) {
            if (! array_key_exists($ip, $reachable)) {
                $reachable[$ip] = false;
            }
        }

        return $reachable;
    }

    /**
     * Expand a wildcard / range pattern into a list of IPv4 strings.
     * Each octet can be: a number (10), a wildcard (*), or a range (1-50).
     *
     * @return array<int, string>
     */
    public function expandWildcard(string $pattern, int $maxHosts = 1024): array
    {
        $octets = explode('.', trim($pattern));

        if (count($octets) !== 4) {
            throw new InvalidArgumentException('Pattern must have 4 octets (e.g. 10.162.*.* or 10.162.5.1-50).');
        }

        /** @var array<int, array<int,int>> $ranges */
        $ranges = [];

        foreach ($octets as $octet) {
            if ($octet === '*') {
                $ranges[] = range(0, 255);
            } elseif (str_contains($octet, '-')) {
                [$from, $to] = array_map('intval', explode('-', $octet, 2));
                if ($from < 0 || $to > 255 || $from > $to) {
                    throw new InvalidArgumentException("Invalid octet range: {$octet}");
                }
                $ranges[] = range($from, $to);
            } elseif (ctype_digit($octet) && (int) $octet >= 0 && (int) $octet <= 255) {
                $ranges[] = [(int) $octet];
            } else {
                throw new InvalidArgumentException("Invalid octet value: {$octet}");
            }
        }

        $total = array_product(array_map('count', $ranges));

        if ($total > $maxHosts) {
            throw new InvalidArgumentException(
                "Pattern would generate {$total} IPs which exceeds the limit of {$maxHosts}. Narrow the range (e.g. 10.162.1-50.*) or increase Max Hosts."
            );
        }

        $ips = [];

        foreach ($ranges[0] as $a) {
            foreach ($ranges[1] as $b) {
                foreach ($ranges[2] as $c) {
                    foreach ($ranges[3] as $d) {
                        $ips[] = "{$a}.{$b}.{$c}.{$d}";
                    }
                }
            }
        }

        return $ips;
    }

    /**
     * Resolve and probe a list of FQDNs.
     *
     * @param  array<int,string>  $hostnames
     * @return array<int, array<string, mixed>>
     */
    public function scanHostnames(array $hostnames, float $timeout = 1.0): array
    {
        $results = [];

        foreach ($hostnames as $rawHostname) {
            $fqdn = strtolower(trim($rawHostname));

            if ($fqdn === '') {
                continue;
            }

            $ip = $this->resolveHostname($fqdn);
            $isSshReachable = false;

            if ($ip) {
                $isSshReachable = $this->isPortOpen($ip, 22, $timeout);
            }

            $results[] = [
                'input' => $rawHostname,
                'fqdn' => $fqdn,
                'ip_address' => $ip,
                'hostname' => $fqdn,
                'domain' => $this->extractDomain($fqdn),
                'ssh_port' => 22,
                'ssh_reachable' => $isSshReachable,
                'resolved' => $ip !== null,
                'status' => $isSshReachable ? 'online' : ($ip ? 'offline' : 'unresolved'),
            ];
        }

        return $results;
    }

    /**
     * Resolve a hostname to an IPv4 address using multiple strategies.
     */
    private function resolveHostname(string $hostname): ?string
    {
        $ip = gethostbyname($hostname);

        if ($ip !== $hostname && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        if (function_exists('shell_exec')) {
            $output = @shell_exec('dig +short '.escapeshellarg($hostname).' @8.8.8.8 2>/dev/null');
            foreach (array_filter(array_map('trim', explode("\n", $output ?? ''))) as $line) {
                if (filter_var($line, FILTER_VALIDATE_IP)) {
                    return $line;
                }
            }
        }

        return null;
    }

    /**
     * Extract domain from FQDN.
     */
    private function extractDomain(?string $hostname): ?string
    {
        if (! $hostname || ! str_contains($hostname, '.')) {
            return null;
        }

        $parts = explode('.', $hostname);
        array_shift($parts);

        return implode('.', $parts);
    }
}
