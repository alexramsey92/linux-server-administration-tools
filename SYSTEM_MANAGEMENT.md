# Linux Web Server Management System

A modern, AI-powered inventory and management system for Linux web servers. Built with Laravel, Livewire, and Claude AI.

## Features

### 🗂️ Server Inventory
- Centralized dashboard for all your Linux servers
- Track server specs (CPU, RAM, disk, OS)
- Filter by status (online/offline/maintenance) and environment (production/staging/dev)
- Real-time server status indicators
- Search across name, hostname, and IP address

### 📊 Health Monitoring
- Real-time CPU, memory, and disk usage monitoring
- Load average tracking (1, 5, 15 minute)
- Service status and uptime tracking
- Historical metric graphs
- Health status alerts (critical/warning/healthy)

### 💻 Application & Service Management
- Track installed applications with versions and status
- Manage systemd services (start/stop/restart)
- View service logs and configuration
- Port tracking and service dependencies
- Application health status

### 🤖 AI-Powered Diagnostics
- Claude-powered health analysis
- Automated troubleshooting recommendations
- Deployment capacity assessments
- Custom issue diagnosis
- Security and optimization suggestions

### 🔐 Secure SSH Integration
- Remote command execution via SSH
- Key-based authentication
- Secure credential storage
- System command execution and parsing

## Installation & Setup

### Prerequisites
- PHP 8.4+
- Laravel 11
- Livewire 3
- Node.js & npm
- Claude API key (from https://platform.anthropic.com/settings/keys)
- SSH access to your Linux servers

### Quick Start

1. **Clone and install dependencies**
   ```bash
   composer install
   npm install
   npm run build
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Add Claude API key to `.env`**
   ```env
   ANTHROPIC_API_KEY=sk-ant-api03-xxxxxxxxxxxxx
   ```

4. **Run migrations**
   ```bash
   php artisan migrate
   ```

5. **Access the application**
   ```
   http://linux-onprem-webserver-tools.test
   ```

## Usage

### Adding Your First Server

1. Navigate to **Server Inventory**
2. Click **Add Server**
3. Fill in server details:
   - **Name**: Descriptive name (e.g., "prod-web-01")
   - **Hostname**: Full hostname
   - **IP Address**: Server IP
   - **SSH Port**: Usually 22
   - **SSH User**: Usually "root" or deployment user
   - **OS**: Ubuntu, Debian, CentOS, etc.
   - **CPU Cores**: Number of cores
   - **RAM**: Total RAM in GB
   - **Disk**: Total disk in GB
   - **Environment**: production/staging/development

4. Set up SSH authentication:
   - **Option 1**: Password-based (configure SSH)
   - **Option 2**: Key-based (upload your private key)

### Monitoring Server Health

1. Go to **Server Inventory → [Server Name]**
2. Click **Health Monitor** tab
3. View real-time metrics:
   - CPU, Memory, Disk usage
   - Load averages
   - Service status
   - Historical graphs

4. Click **Refresh** to pull latest data

### Using AI Diagnostics

1. Go to **Inventory → [Server] → Diagnostics**
2. Choose diagnostic type:
   - **Health Analysis**: Overall server health assessment
   - **Deployment**: Capacity and optimization recommendations
   - **Custom Issue**: Describe a specific problem

3. Claude AI will provide:
   - Root cause analysis
   - Recommended solutions
   - Linux commands to investigate
   - Optimization suggestions

## Database Schema

### Servers Table
```
- id
- name (unique)
- hostname
- ip_address (unique)
- status (online/offline/maintenance/error)
- os (ubuntu/debian/centos/rhel/fedora/other)
- os_version
- cpu_cores
- cpu_model
- ram_gb
- disk_gb
- ssh_port
- ssh_user
- ssh_key_path
- environment (production/staging/development)
- description
- metadata (JSON)
- last_health_check
- timestamps
```

### Applications Table
```
- id
- server_id (FK)
- name
- version
- type (web/database/cache/queue/monitoring/other)
- status (running/stopped/failed/unknown)
- package_manager
- port
- path
- config_path
- log_path
- metadata (JSON)
- last_checked
- timestamps
```

### Services Table
```
- id
- server_id (FK)
- name
- service_name (systemd name)
- status (running/stopped/failed/unknown)
- enabled (enabled/disabled/unknown)
- description
- path
- port
- metadata (JSON)
- last_checked
- timestamps
```

### System Metrics Table
```
- id
- server_id (FK)
- cpu_usage_percent
- memory_usage_percent
- memory_used_gb
- memory_available_gb
- disk_usage_percent
- disk_used_gb
- disk_available_gb
- load_average_1/5/15
- processes_running
- uptime_seconds
- network_stats (JSON)
- additional_metrics (JSON)
- timestamps
```

## API & Services

### SSHClient Service
```php
$client = new SSHClient($server);
$client->execute('whoami');
$metrics = $client->getSystemMetrics();
$isOnline = $client->isOnline();
```

### HealthMonitor Service
```php
$monitor = new HealthMonitor($server);
$metric = $monitor->checkHealth();
$summary = $monitor->getHealthSummary();
$history = $monitor->getHistory(24); // last 24 hours
```

### ServiceManager Service
```php
$manager = new ServiceManager($server);
$manager->start($service);
$manager->stop($service);
$manager->restart($service);
$status = $manager->getStatus($service);
$logs = $manager->getLogs($service, 50);
```

### ServerDiagnostics Service
```php
$diagnostics = new ServerDiagnostics();
$analysis = $diagnostics->analyzeHealth($server);
$diagnosis = $diagnostics->diagnoseIssue($server, 'Issue description');
$recommendations = $diagnostics->getDeploymentRecommendations($server);
```

## Configuration

### .env Variables
```env
# Database
DB_CONNECTION=sqlite

# Anthropic API (for AI diagnostics)
ANTHROPIC_API_KEY=sk-ant-api03-xxxxxxxxxxxxx

# SSH Configuration
SSH_TIMEOUT=30
SSH_PORT=22
```

### config/services.php
```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],
```

## Livewire Components

### ServerTable
- Paginated server listing
- Filtering by status and environment
- Real-time search
- Sortable columns

### ServerDetail
- Server specifications
- Recent applications
- Recent services
- System metrics

### HealthMonitor
- Real-time metrics display
- Historical graphs
- Health status summary
- Auto-refresh functionality

### Diagnostics
- Health analysis
- Deployment recommendations
- Custom issue diagnosis
- Claude AI integration

## Security Considerations

1. **SSH Keys**: Store SSH keys securely in `storage/` directory
2. **API Keys**: Never commit `.env` files with real API keys
3. **HTTPS**: Always use HTTPS in production
4. **Permissions**: Limit database user permissions
5. **Rate Limiting**: Consider implementing rate limits on diagnostics

## Development

### Running Tests
```bash
php artisan test
```

### Formatting Code
```bash
vendor/bin/pint
```

### Database Seeding
```bash
php artisan db:seed
```

## Architecture

```
app/
├── Livewire/
│   ├── Inventory/
│   │   ├── ServerTable.php
│   │   ├── ServerDetail.php
│   │   └── HealthMonitor.php
│   └── AI/
│       └── Diagnostics.php
├── Services/
│   ├── ServerManagement/
│   │   ├── SSHClient.php
│   │   ├── HealthMonitor.php
│   │   └── ServiceManager.php
│   └── AI/
│       └── ServerDiagnostics.php
├── Models/
│   ├── Server.php
│   ├── Application.php
│   ├── Service.php
│   └── SystemMetric.php
└── Http/
    └── Controllers/
```

## Roadmap

- [ ] Authentication & authorization system
- [ ] User roles and permissions
- [ ] Alert notifications (email/Slack)
- [ ] Automated health checks (schedule)
- [ ] Backup management integration
- [ ] Log aggregation and analysis
- [ ] Deployment management
- [ ] Cost analytics
- [ ] Performance baselines
- [ ] API documentation

## Troubleshooting

### SSH Connection Issues
- Verify IP address is reachable
- Check SSH port (usually 22)
- Ensure SSH credentials are correct
- Check firewall rules

### Health Check Failures
- Verify network connectivity
- Check SSH credentials
- Ensure required Linux commands are available
- Check server logs

### AI Diagnostics Not Working
- Verify Claude API key is set
- Check API quota and credits
- Ensure API key has necessary permissions
- Check internet connectivity

## Support & Contributing

For issues, feature requests, or contributions, please open an issue or pull request.

## License

This project is open source and available under the MIT License.

## Credits

Built with:
- Laravel 11
- Livewire 3
- Tailwind CSS 3
- Claude AI (Anthropic)
- PHP 8.4

Created for infrastructure management.
