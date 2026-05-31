<?php

namespace App\Services\ServerManagement;

use App\SslCertificate;
use Exception;

class SslChecker
{
    private const STREAM_TIMEOUT_SECONDS = 10;

    public function __construct(public SslCertificate $certificate) {}

    /**
     * Check the live SSL certificate for the stored domain/port and update the record.
     *
     * @return array<string, mixed>
     */
    public function check(): array
    {
        try {
            $certData = $this->fetchCertificate(
                $this->certificate->domain,
                $this->certificate->port ?? 443
            );

            $this->certificate->update([
                'issuer' => $certData['issuer'],
                'subject' => $certData['subject'],
                'sans' => $certData['sans'],
                'valid_from' => $certData['valid_from'],
                'expires_at' => $certData['expires_at'],
                'status' => $this->resolveStatus($certData['expires_at']),
                'last_checked_at' => now(),
                'metadata' => $certData['raw'],
            ]);

            return ['success' => true, 'data' => $certData];
        } catch (Exception $e) {
            $this->certificate->update([
                'status' => 'unknown',
                'last_checked_at' => now(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch raw certificate data from a domain without an existing record.
     *
     * @return array<string, mixed>
     */
    public static function fetchCertificate(string $domain, int $port = 443): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $stream = @stream_socket_client(
            "ssl://{$domain}:{$port}",
            $errno,
            $errstr,
            self::STREAM_TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $stream) {
            throw new Exception("Could not connect to {$domain}:{$port} — {$errstr} ({$errno})");
        }

        $params = stream_context_get_params($stream);
        fclose($stream);

        /** @var \OpenSSLCertificate|null $cert */
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            throw new Exception("No certificate returned from {$domain}:{$port}");
        }

        $parsed = openssl_x509_parse($cert);

        if (! $parsed) {
            throw new Exception("Failed to parse certificate from {$domain}:{$port}");
        }

        $validFrom = isset($parsed['validFrom_time_t'])
            ? (new \DateTime)->setTimestamp($parsed['validFrom_time_t'])
            : null;

        $expiresAt = isset($parsed['validTo_time_t'])
            ? (new \DateTime)->setTimestamp($parsed['validTo_time_t'])
            : null;

        $issuer = self::formatDn($parsed['issuer'] ?? []);
        $subject = self::formatDn($parsed['subject'] ?? []);

        $sans = self::parseSans($parsed['extensions']['subjectAltName'] ?? '');

        return [
            'issuer' => $issuer,
            'subject' => $subject,
            'sans' => $sans,
            'valid_from' => $validFrom?->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'raw' => [
                'serial_number' => $parsed['serialNumberHex'] ?? null,
                'signature_type' => $parsed['signatureTypeSN'] ?? null,
                'extensions' => $parsed['extensions'] ?? [],
            ],
        ];
    }

    /**
     * Resolve status string based on expiry timestamp.
     */
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

    /**
     * Format a DN array into a readable CN string.
     *
     * @param  array<string, string>  $dn
     */
    private static function formatDn(array $dn): string
    {
        if (isset($dn['CN'])) {
            return $dn['CN'];
        }

        return implode(', ', array_map(
            fn ($key, $value) => "{$key}={$value}",
            array_keys($dn),
            $dn
        ));
    }

    /**
     * Parse the subjectAltName extension string into an array of domain names.
     *
     * @return array<int, string>
     */
    private static function parseSans(string $sanString): array
    {
        if (empty($sanString)) {
            return [];
        }

        $sans = [];
        foreach (explode(',', $sanString) as $entry) {
            $entry = trim($entry);
            if (str_starts_with($entry, 'DNS:')) {
                $sans[] = substr($entry, 4);
            }
        }

        return $sans;
    }
}
