<?php

namespace App\Jobs;

use App\Server;
use App\SslCertificate;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverServerSslCertificates implements ShouldQueue
{
    use Queueable;

    private const DEFAULT_PORT = 443;

    private const MAX_DISCOVERY_HOSTS = 50;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $serverId) {}

    /**
     * Execute the job.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $server = Server::query()
            ->with('sslCertificates')
            ->find($this->serverId);

        if (! $server) {
            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'checked' => 0,
                'errors' => ['Server not found.'],
            ];
        }

        $queue = $this->buildInitialDomainQueue($server);
        $visited = [];
        $created = 0;
        $updated = 0;
        $checked = 0;
        $errors = [];

        while (! empty($queue) && count($visited) < self::MAX_DISCOVERY_HOSTS) {
            $domain = array_shift($queue);

            if (! $domain || isset($visited[$domain])) {
                continue;
            }

            $visited[$domain] = true;

            try {
                $certData = $this->fetchCertificateWithCurl($domain, self::DEFAULT_PORT);

                $certificate = SslCertificate::query()->firstOrNew([
                    'server_id' => $server->id,
                    'domain' => $domain,
                    'port' => self::DEFAULT_PORT,
                ]);

                $isNew = ! $certificate->exists;

                $certificate->fill([
                    'issuer' => $certData['issuer'],
                    'subject' => $certData['subject'],
                    'sans' => $certData['sans'],
                    'valid_from' => $certData['valid_from'],
                    'expires_at' => $certData['expires_at'],
                    'status' => $this->resolveStatus($certData['expires_at']),
                    'last_checked_at' => now(),
                    'metadata' => $certData['raw'],
                ]);

                $certificate->save();

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }

                $checked++;

                foreach ($certData['sans'] as $sanDomain) {
                    $normalizedSanDomain = $this->normalizeDomain($sanDomain);

                    if (! $normalizedSanDomain || isset($visited[$normalizedSanDomain])) {
                        continue;
                    }

                    $queue[] = $normalizedSanDomain;
                }
            } catch (Exception $exception) {
                $errors[] = "{$domain}: {$exception->getMessage()}";
            }
        }

        return [
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'checked' => $checked,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildInitialDomainQueue(Server $server): array
    {
        $domains = [];

        $domains[] = $this->normalizeDomain($server->hostname);

        foreach ($server->sslCertificates as $certificate) {
            $domains[] = $this->normalizeDomain($certificate->domain);

            foreach (($certificate->sans ?? []) as $sanDomain) {
                $domains[] = $this->normalizeDomain($sanDomain);
            }
        }

        foreach ($this->extractDomainsFromText((string) $server->quick_notes) as $domainFromNotes) {
            $domains[] = $this->normalizeDomain($domainFromNotes);
        }

        return array_values(array_unique(array_filter($domains)));
    }

    /**
     * @return array<int, string>
     */
    private function extractDomainsFromText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\b/i', $text, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @return array{issuer: string|null, subject: string|null, sans: array<int, string>, valid_from: string|null, expires_at: string|null, raw: array<string, mixed>}
     */
    private function fetchCertificateWithCurl(string $domain, int $port): array
    {
        $url = "https://{$domain}:{$port}";

        $curlHandle = curl_init($url);

        if (! $curlHandle) {
            throw new Exception('Unable to initialize cURL handle.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CERTINFO => true,
            CURLOPT_USERAGENT => 'Linux-SSL-Discovery/1.0',
        ]);

        curl_exec($curlHandle);

        $curlError = curl_error($curlHandle);
        $certInfo = curl_getinfo($curlHandle, CURLINFO_CERTINFO);
        $effectiveUrl = (string) curl_getinfo($curlHandle, CURLINFO_EFFECTIVE_URL);

        curl_close($curlHandle);

        if ($curlError !== '') {
            throw new Exception($curlError);
        }

        if (! is_array($certInfo) || empty($certInfo[0]) || ! is_array($certInfo[0])) {
            throw new Exception('No certificate details returned by cURL.');
        }

        $leafCert = $certInfo[0];
        $pem = $leafCert['Cert'] ?? null;

        if (! is_string($pem) || $pem === '') {
            throw new Exception('Leaf certificate PEM not available from cURL response.');
        }

        $opensslCert = openssl_x509_read($pem);

        if (! $opensslCert) {
            throw new Exception('Unable to read PEM certificate from cURL response.');
        }

        $parsed = openssl_x509_parse($opensslCert);

        if (! is_array($parsed)) {
            throw new Exception('Unable to parse certificate metadata.');
        }

        $validFrom = isset($parsed['validFrom_time_t']) ? Carbon::createFromTimestamp((int) $parsed['validFrom_time_t']) : null;
        $expiresAt = isset($parsed['validTo_time_t']) ? Carbon::createFromTimestamp((int) $parsed['validTo_time_t']) : null;

        return [
            'issuer' => $this->formatDn($parsed['issuer'] ?? []),
            'subject' => $this->formatDn($parsed['subject'] ?? []),
            'sans' => $this->parseSans($parsed['extensions']['subjectAltName'] ?? ''),
            'valid_from' => $validFrom?->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'raw' => [
                'discovery_method' => 'curl',
                'checked_url' => $effectiveUrl,
                'serial_number' => $parsed['serialNumberHex'] ?? null,
                'signature_type' => $parsed['signatureTypeSN'] ?? null,
                'extensions' => $parsed['extensions'] ?? [],
            ],
        ];
    }

    private function resolveStatus(?string $expiresAt): string
    {
        if (! $expiresAt) {
            return 'unknown';
        }

        $expiry = Carbon::parse($expiresAt);

        if ($expiry->isPast()) {
            return 'expired';
        }

        if ($expiry->diffInDays(now()) <= 90) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    /**
     * @param  array<string, string>  $dn
     */
    private function formatDn(array $dn): ?string
    {
        if ($dn === []) {
            return null;
        }

        if (isset($dn['CN'])) {
            return $dn['CN'];
        }

        return implode(', ', array_map(
            static fn ($key, $value) => "{$key}={$value}",
            array_keys($dn),
            $dn
        ));
    }

    /**
     * @return array<int, string>
     */
    private function parseSans(string $sanString): array
    {
        if ($sanString === '') {
            return [];
        }

        $sans = [];

        foreach (explode(',', $sanString) as $entry) {
            $entry = trim($entry);

            if (! str_starts_with($entry, 'DNS:')) {
                continue;
            }

            $domain = $this->normalizeDomain(substr($entry, 4));

            if ($domain) {
                $sans[] = $domain;
            }
        }

        return array_values(array_unique($sans));
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if (! $domain) {
            return null;
        }

        $normalizedDomain = strtolower(trim($domain));
        $normalizedDomain = preg_replace('/^https?:\/\//', '', $normalizedDomain);
        $normalizedDomain = preg_replace('/\/.*$/', '', $normalizedDomain);
        $normalizedDomain = trim((string) $normalizedDomain, ' .');

        if ($normalizedDomain === '' || str_contains($normalizedDomain, '*')) {
            return null;
        }

        if (! filter_var($normalizedDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        return $normalizedDomain;
    }
}
