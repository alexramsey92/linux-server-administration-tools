<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $environments = ['production', 'staging', 'development'];
        $osVersions = ['7', '8', '9', '10'];
        $domains = ['example.com', 'internal.example.com', 'dev.example.com', 'staging.example.com', 'example.org'];
        $statuses = ['online', 'offline', 'maintenance'];
        
        $baseName = $this->faker->unique()->word();
        $type = $this->faker->randomElement(['web', 'db', 'api', 'app']);
        $number = $this->faker->randomElement(range(1, 99));
        $tier = $this->faker->randomElement(['p', 't', 'd']);
        $domain = $this->faker->randomElement($domains);

        return [
            'name' => $baseName.'-'.$type.$number.$tier,
            'hostname' => 'f'.strtolower($baseName).'-'.$type.$number.$tier.'.'.$domain,
            'domain' => $domain,
            'ip_address' => $this->faker->unique()->ipv4(),
            'status' => $this->faker->randomElement($statuses),
            'os' => 'oracle',
            'os_version' => $this->faker->randomElement($osVersions),
            'cpu_cores' => $this->faker->numberBetween(2, 16),
            'cpu_model' => $this->faker->randomElement(['Intel Xeon', 'AMD EPYC']),
            'ram_gb' => $this->faker->randomElement([4, 8, 16, 32, 64]),
            'ssh_port' => $this->faker->randomElement(['22', '2222', '22022']),
            'ssh_user' => $this->faker->randomElement(['root', 'oracle', 'admin']),
            'environment' => $this->faker->randomElement($environments),
            'description' => $this->faker->sentence(),
            'last_health_check' => $this->faker->dateTimeThisMonth(),
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'online',
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offline',
        ]);
    }

    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
        ]);
    }

    public function staging(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'staging',
        ]);
    }
}
