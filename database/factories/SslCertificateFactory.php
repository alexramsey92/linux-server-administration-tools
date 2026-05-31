<?php

namespace Database\Factories;

use App\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\SslCertificate>
 */
class SslCertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expiresAt = $this->faker->dateTimeBetween('+1 days', '+365 days');
        $validFrom = $this->faker->dateTimeBetween('-90 days', 'now');

        return [
            'server_id' => Server::factory(),
            'domain' => $this->faker->domainName(),
            'port' => 443,
            'issuer' => $this->faker->randomElement([
                "Let's Encrypt Authority X3",
                'DigiCert SHA2 Secure Server CA',
                'Sectigo RSA Domain Validation Secure Server CA',
                'GlobalSign Organization Validation CA - SHA256 - G2',
            ]),
            'subject' => 'CN='.$this->faker->domainName(),
            'sans' => [$this->faker->domainName(), $this->faker->domainName()],
            'valid_from' => $validFrom,
            'expires_at' => $expiresAt,
            'status' => 'valid',
            'last_checked_at' => $this->faker->dateTimeThisMonth(),
        ];
    }

    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('+31 days', '+365 days'),
            'status' => 'valid',
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('+1 days', '+30 days'),
            'status' => 'expiring_soon',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-90 days', '-1 days'),
            'status' => 'expired',
        ]);
    }
}
