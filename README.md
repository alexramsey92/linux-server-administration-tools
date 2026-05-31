# Linux On-Prem Webserver Tools

Internal Laravel + Livewire application for Linux server inventory, host operations visibility, SSL certificate tracking, and AI-assisted diagnostics.

## Current Project Status (as of March 27, 2026)

This project is in active prototype / implementation state and now includes working inventory management plus SSL certificate discovery and reporting.

## Core Functionality

### Server Inventory

- Server inventory table with:
  - search
  - status and environment filters
  - sorting
  - pagination
  - CSV export
  - delete confirmation flow
- Server create/edit form with:
  - hostname/domain conventions
  - environment auto-detection from hostname suffix
  - hostname-to-IP discovery helper
  - quick copy for generated full hostname
- Server detail page with:
  - key host metadata
  - quick notes
  - discovery links/status cards
  - application/service summaries
  - SSL certificate summary panel
- One-click hostname copy actions across major inventory screens

### Operational Visibility

- Health monitor page with on-demand metric collection and recent history
- Per-server application and service relationships
- System metrics persistence:
  - CPU
  - memory
  - disk usage
  - load averages
  - uptime/network metadata

### SSL Certificate Management

- Track multiple SSL certificates per server
- Create/edit SSL certificate records manually
- Live certificate metadata discovery for a domain/port
- SSL certificate list with:
  - search
  - server filter
  - status filter
  - sorting
  - pagination
  - CSV export
- Renewal visibility windows for:
  - 30 days
  - 60 days
  - 90 days
- Expiry/status visualization:
  - expired
  - expiring soon (90 days or less)
  - valid (more than 90 days)

### SSL Discovery and Queue Processing

- Discover SSL certificates for a single server using cURL/OpenSSL
- Inventory-wide SSL discovery dispatches queue jobs across all servers
- Discovery sources include:
  - server hostname
  - existing tracked certificate domains
  - SAN entries
  - domain-like values in quick notes
- Queue-backed execution through Laravel jobs table

### AI Diagnostics

- AI diagnostics page with health, deployment, and issue analysis actions

## Current Routes

Primary app routes:

- `/inventory`
- `/inventory/scan`
- `/inventory/create`
- `/inventory/server/{server}`
- `/inventory/server/{server}/edit`
- `/inventory/server/{server}/health`
- `/inventory/server/{server}/diagnostics`
- `/inventory/ssl`
- `/inventory/ssl/create`
- `/inventory/ssl/{certificate}/edit`

## Console Commands

- `php artisan ssl:discover-server {serverId}`
  - Queue SSL discovery for one server
- `php artisan ssl:discover-server {serverId} --sync`
  - Run SSL discovery inline for one server
- `php artisan ssl:discover-all`
  - Queue SSL discovery for the full inventory
- `php artisan ssl:discover-all --sync`
  - Run SSL discovery inline across all servers

## Local Setup

1) Install dependencies

```bash
composer install
npm install
```

2) Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

3) Create schema + seed sample data

```bash
php artisan migrate
php artisan db:seed --class=ServerSeeder
```

4) Build frontend assets

```bash
npm run build
```

5) Start the app

```bash
php artisan serve
```

6) Run queue worker for SSL discovery jobs

```bash
php artisan queue:work
```

With Laravel Herd, the app is also available at:

- `http://linux-onprem-webserver-tools.test`

## UI Theming Ruleset (Tailwind + Dark Mode)

Use this ruleset for all new UI (human or AI-generated) to avoid recurring dark mode regressions:

- Use neutral containers by default: `bg-white dark:bg-slate-800` with `border-slate-200 dark:border-slate-700`.
- Keep status colors in badges/chips, not full card backgrounds.
- Keep text contrast consistent:
  - Primary: `text-slate-900 dark:text-white`
  - Secondary: `text-slate-600 dark:text-slate-400`
  - Disabled: `text-slate-500 dark:text-slate-300`
- Use consistent action button pairs:
  - Primary: `bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white`
  - Secondary: `bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white`
- Ensure each `bg-*`, `text-*`, and `border-*` has a dark equivalent.
- Avoid inverting to light buttons in dark mode unless explicitly required.

These rules are also mirrored in [/.github/copilot-instructions.md](.github/copilot-instructions.md) for assistant-generated code.

## Known Gaps / Next Steps

- Authentication and authorization
- Notes/chat persistence beyond current quick notes field
- Notifications and alerting integrations
- Scheduled recurring SSL and health polling
- Automated test suite coverage
- Production hardening and operational runbooks

## Contributing

Open an issue or PR with:

- clear problem statement
- reproducible steps
- expected outcome
- screenshots (if UI-related)

