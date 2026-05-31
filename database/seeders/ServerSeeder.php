<?php

namespace Database\Seeders;

use App\Application;
use App\Server;
use App\Service;
use App\SslCertificate;
use App\SystemMetric;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 5 production servers
        $prodServers = Server::factory()
            ->production()
            ->online()
            ->count(5)
            ->create();

        // Create 3 staging servers
        $stagingServers = Server::factory()
            ->staging()
            ->online()
            ->count(3)
            ->create();

        // Create 2 development servers
        $devServers = Server::factory()
            ->count(2)
            ->state(['environment' => 'development'])
            ->create();

        // All servers to seed
        $allServers = $prodServers->merge($stagingServers)->merge($devServers);

        foreach ($allServers as $server) {
            // Add sample applications
            Application::create([
                'server_id' => $server->id,
                'name' => 'nginx',
                'type' => 'web',
                'version' => '1.24.0',
                'status' => 'running',
                'package_manager' => 'apt',
                'port' => '80,443',
                'path' => '/usr/sbin/nginx',
            ]);

            Application::create([
                'server_id' => $server->id,
                'name' => 'postgresql',
                'type' => 'database',
                'version' => '14.5',
                'status' => 'running',
                'package_manager' => 'apt',
                'port' => '5432',
                'path' => '/usr/bin/postgres',
            ]);

            // Add sample services
            Service::create([
                'server_id' => $server->id,
                'name' => 'Nginx Web Server',
                'service_name' => 'nginx',
                'status' => 'running',
                'enabled' => 'enabled',
                'description' => 'High-performance web server',
            ]);

            Service::create([
                'server_id' => $server->id,
                'name' => 'PostgreSQL Database',
                'service_name' => 'postgresql',
                'status' => 'running',
                'enabled' => 'enabled',
                'description' => 'PostgreSQL database service',
            ]);

            Service::create([
                'server_id' => $server->id,
                'name' => 'SSH Server',
                'service_name' => 'ssh',
                'status' => 'running',
                'enabled' => 'enabled',
                'description' => 'OpenSSH server',
            ]);

            // Add sample metrics
            SystemMetric::create([
                'server_id' => $server->id,
                'cpu_usage_percent' => rand(5, 45),
                'memory_usage_percent' => rand(10, 60),
                'memory_used_gb' => rand(1, $server->ram_gb - 1),
                'memory_available_gb' => $server->ram_gb - rand(1, $server->ram_gb - 1),
                'disk_usage_percent' => rand(20, 70),
                'disk_used_gb' => intval($server->disk_gb * (rand(20, 70) / 100)),
                'disk_available_gb' => intval($server->disk_gb * (rand(30, 80) / 100)),
                'load_average_1' => number_format(rand(1, 4) + (rand(0, 99) / 100), 2),
                'load_average_5' => number_format(rand(1, 4) + (rand(0, 99) / 100), 2),
                'load_average_15' => number_format(rand(1, 4) + (rand(0, 99) / 100), 2),
                'processes_running' => rand(50, 200),
                'uptime_seconds' => rand(86400, 31536000),
            ]);

            SslCertificate::factory()->for($server)->valid()->create();
            SslCertificate::factory()->for($server)->expiringSoon()->create();
            SslCertificate::factory()->for($server)->expired()->create();
        }
    }
}
