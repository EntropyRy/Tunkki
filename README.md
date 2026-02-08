![coverage](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/EntropyRy/Tunkki/main/symfony/coverage.json)

# Entropy Tunkki

Association management platform built with Symfony 7 and PHP 8.4+. Runs in Docker (nginx + PHP-FPM + MariaDB).

## Setup

### 1. Configure environment

Copy the example files and adjust defaults:

```bash
cp .env.example .env
cp symfony/.env symfony/.env.dev.local
```

Edit both files to set database credentials, ports, and other local settings.

### 2. Build and start containers

```bash
docker compose build
docker compose up -d
```

### 3. Install dependencies

```bash
docker compose exec fpm composer install
```

### 4. Set up the database

From a dump:
```bash
docker compose exec fpm ./bin/console doctrine:database:import dump.sql
```

Or create from schema:
```bash
docker compose exec fpm ./bin/console doctrine:schema:update --force
```

### 5. Seed the CMS

Creates the required Sonata Page sites (FI default + EN `/en/`) and root pages:

```bash
docker compose exec fpm ./bin/console entropy:cms:seed
```

### 6. Create an admin user

```bash
docker compose exec fpm ./bin/console entropy:member --password --create-user your@email.com --super-admin
```

### 7. Access Tunkki

Open http://localhost:9090/ in your browser. Login at http://localhost:9090/login.

## Development

| Resource | Purpose |
|----------|---------|
| `make help` | List all available Makefile targets |
| `make test` | Run the full test suite |
| `make stan` | Run PHPStan static analysis |
| [CLAUDE.md](CLAUDE.md) | Conventions, commands, and AI assistant guidance |
| [TESTING.md](TESTING.md) | Comprehensive testing guide, factory catalog, patterns |

### Console commands

```bash
docker compose exec fpm ./bin/console
```
