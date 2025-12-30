# NetworkWeathermapNG

Network visualization tool for Zabbix - a standalone PHP application that creates network weathermaps using data from Zabbix monitoring system.

## Overview

NetworkWeathermapNG is based on the popular Cacti Weathermap plugin but redesigned to work as a standalone Docker container.

## Features

- **Standalone Docker Container** - No dependencies on Cacti or other systems
- **Zabbix API Integration** - Fetches real-time data from Zabbix
- **Visual Map Editor** - Create and edit maps through web interface
- **Multiple Maps** - Organize maps into groups
- **Map Cycling** - Auto-rotate through maps for NOC displays
- **User Authentication** - Built-in authentication system
- **Responsive UI** - Modern, mobile-friendly interface

## Quick Start

### Using Docker Compose

1. Clone the repository:
   ```bash
   git clone https://github.com/nchekwa/networkweathermapng.git
   cd networkweathermapng
   ```

2. Copy and configure environment:
   ```bash
   cp .env.example .env
   # Edit .env if you want to change app/db/auth/map settings
   ```

3. Start the container:
   ```bash
   cd docker
   docker-compose up -d
   ```

4. Access the application:
   ```
   http://localhost:8080
   ```

   Default credentials: `admin` / `admin`

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Enable debug mode | `false` |
| `APP_TIMEZONE` | Application timezone | `UTC` |
| `APP_ROOT` | Application root path | - |
| `DATA_ROOT` | Persistent data root path | - |
| `DB_TYPE` | Database type (`sqlite` or `mysql`) | `sqlite` |
| `AUTH_ENABLED` | Enable authentication | `true` |
| `ADMIN_USER` | Default admin username | `admin` |
| `ADMIN_PASSWORD` | Default admin password | `admin` |
| `MAP_OUTPUT_FORMAT` | Image format (`png`, `jpg`, `gif`) | `png` |
| `MAP_REFRESH_INTERVAL` | Map refresh interval in seconds | `300` |

### Data sources (Zabbix and more)

Data sources are configured via the web UI and stored in the database:

- Go to **Admin → Data Sources**
- Add your Zabbix API endpoint and credentials there


## Map Configuration

Maps are configured using text-based configuration files. Example:

```
# Map settings
BACKGROUND images/backgrounds/network.png
WIDTH 1200
HEIGHT 800
TITLE My Network Map

# Define a node
NODE router1
    LABEL Core Router
    POSITION 100 100
    ICON images/icons/router.png
    TARGET zabbix:hostid:10084:key:system.cpu.util

# Define another node
NODE switch1
    LABEL Main Switch
    POSITION 300 100
    TARGET zabbix:hostid:10085:key:system.cpu.util

# Define a link between nodes
LINK router1-switch1
    NODES router1 switch1
    TARGET zabbix:hostid:10084:key:net.if.in[eth0]:net.if.out[eth0]
    BANDWIDTH 1G
```

### Zabbix TARGET Formats

| Format | Description |
|--------|-------------|
| `zabbix:itemid:12345` | Single item by ID |
| `zabbix:itemid:12345:12346` | Two items (in/out) by ID |
| `zabbix:host:hostname:key:item.key` | Item by hostname and key |
| `zabbix:hostid:10084:key:item.key` | Item by host ID and key |
| `zabbix:hostid:10084:key:in.key:out.key` | Two items by host ID and keys |

## Directory Structure

```
networkweathermapng/
├── docker/                 # Docker configuration
│   ├── Dockerfile
│   ├── docker-compose.yml
│   ├── nginx/
│   ├── php/
│   └── supervisor/
├── src/
│   ├── public/            # Web root
│   ├── app/               # Application code
│   │   ├── Core/          # Core framework
│   │   ├── Controllers/   # HTTP controllers
│   │   ├── Services/      # Business logic
│   │   └── Views/         # Templates
│   └── lib/               # Libraries
│       ├── WeatherMap/    # Map engine
│       └── Zabbix/        # Zabbix API client
├── data/
│   ├── configs/           # Map configuration files
│   ├── output/            # Generated images
│   └── cache/             # Cache files
└── docs/                  # Documentation
```

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/maps` | GET | List all maps |
| `/api/map/{id}` | GET | Get map details |
| `/api/zabbix/hosts` | GET | List Zabbix hosts |
| `/api/zabbix/items/{hostId}` | GET | List items for a host |
| `/api/zabbix/groups` | GET | List Zabbix host groups |

## GitHub Actions: Build & Publish Docker image

This repository includes a GitHub Actions workflow that builds and publishes a Docker image to GitHub Container Registry (GHCR).

- **Workflow file**: `.github/workflows/docker-publish.yml`
- **Image name**: `ghcr.io/<owner>/<repo>`

### Triggers

- Push to `main`:
  - Publishes `latest` and a `sha-...` tag
- Push a version tag (e.g. `v1.2.3`):
  - Publishes `v1.2.3` and a `sha-...` tag
- Manual run:
  - You can run the workflow from the Actions tab

### Required GitHub settings

- Ensure the repository has **Workflow permissions** allowing writing packages (the workflow uses `GITHUB_TOKEN` with `packages: write`).
- After the first push, you will see the package under:
  - `https://github.com/<owner>?tab=packages`

## Development

### Local Development

```bash
# Install dependencies
composer install

# Start development server
composer start
# or
php -S localhost:8080 -t src/public
```

### Running Tests

```bash
composer test
```

## Migration from Cacti Weathermap

If you're migrating from Cacti Weathermap:

1. Export your map configuration files
2. Update TARGET strings from RRD format to Zabbix format
3. Copy configuration files to `data/configs/`
4. Import maps through the admin interface

See `docs/migration_plan.md` for detailed migration instructions.

## Requirements

- PHP 8.1+
- GD extension
- PDO extension (SQLite or MySQL)
- cURL extension
- Zabbix 5.0+ with API access

## License

This project is based on PHP Network Weathermap by Howard Jones and The Cacti Group.

Licensed under the MIT License. See LICENSE file for details.

## Credits

- Original Weathermap by Howard Jones
- Cacti Weathermap Plugin by The Cacti Group
- Zabbix integration and Docker containerization by the Zabbix Weathermap Team

## Support

- [Documentation](docs/)
- [Issue Tracker](https://github.com/nchekwa/networkweathermapng/issues)
