# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Gotcha URL is a URL shortener with visitor tracking, built on Symfony 7.2/8.0 with PHP 8.4, MySQL 8, Doctrine ORM, and Twig templates. Users authenticate via account codes (no passwords), create short links, and view detailed visitor analytics.

## Development Environment

```bash
# Start all services (PHP-FPM, Nginx, MySQL)
docker-compose up -d

# Install dependencies (run inside php container)
docker-compose exec php composer install

# Run migrations
docker-compose exec php bin/console doctrine:migrations:migrate

# Clear cache
docker-compose exec php bin/console cache:clear

# Generate a new migration after entity changes
docker-compose exec php bin/console doctrine:migrations:diff
```

App runs at `http://localhost:8080`. MySQL is exposed on port 3307 (user: gotcha, pass: gotcha, db: gotcha).

## Architecture

### Request Flow

1. **Short link visit:** `GET /{slug}` → `RedirectController` renders `loading.html.twig` (standalone, no base layout) → client JS collects browser metadata → `POST /api/track` → `TrackingApiController` creates Visit record → JS redirects to target URL
2. **Dashboard:** Authenticated users manage links and view visit analytics via `DashboardController`
3. **Auth:** Cookie-based (`gotcha_account`, 1-year, HttpOnly). `AccountCodeAuthenticator` + `AccountCodeUserProvider` in `src/Security/`

### Entities & Relationships

```
User (1) ---> (M) Link (1) ---> (M) Visit
```

All entities use UUID v7 binary primary keys (`Symfony\Component\Uid\UuidV7`). Visits have a compound index on `(link_id, created_at)`.

### Security Firewalls

Routes `/{slug}` (pattern `^/[a-zA-Z0-9]{7}$`) and `/api/track` are **public** (no auth). Dashboard routes require `IS_AUTHENTICATED_FULLY`. The redirect route has `priority: -100` so it doesn't shadow other routes.

### Frontend

Templates use Bootstrap 5 (served locally from `public/css/` and `public/js/`) with a Matrix green-on-black theme overlay (`public/css/matrix-theme.css`). `base.html.twig` is the shared layout; `redirect/loading.html.twig` is standalone. The copy-to-clipboard button uses inline JS that swaps `btn-outline-secondary` ↔ `btn-success`.

### Key Conventions

- Attribute-based routing on controllers (`#[Route(...)]`)
- Autowiring enabled; services registered in `config/services.yaml`
- Repositories contain paginated query methods (20 links/page, 50 visits/page)
- `SlugGenerator` service produces 7-char alphanumeric slugs, checks uniqueness against DB
- No test suite exists yet
