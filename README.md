# Gotcha URL

A URL shortener with visitor tracking. Create short links, share them, and see detailed analytics on every click — IP, browser, location, referrer, and more.

Built with Symfony 7.2/8.0, PHP 8.4, MySQL 8, and Bootstrap 5.

## Features

- **Short links** — 7-character alphanumeric slugs (e.g. `yourdomain.com/aB3xK9z`)
- **Visitor tracking** — Collects IP, User-Agent, referrer, screen resolution, timezone, language, platform, and cookie status per visit
- **Cloudflare geolocation** — Automatically extracts country and city from Cloudflare headers when deployed behind CF
- **Passwordless auth** — Users authenticate with an account code (UUID), stored in a long-lived HttpOnly cookie
- **Rate limiting** — Tracking API is rate-limited (20 req/min per IP, sliding window)
- **HMAC token verification** — Tracking requests are validated with time-limited HMAC tokens to prevent abuse

## Quick Start (Docker)

### Prerequisites

- Docker and Docker Compose

### 1. Clone and start

```bash
git clone <repo-url> gotcha-url
cd gotcha-url
docker compose up -d
```

This starts three services:
- **PHP-FPM** — application server
- **Nginx** — web server on port 8080
- **MySQL 8** — database on port 3307

### 2. Install dependencies

```bash
docker compose exec php composer install
```

### 3. Run database migrations

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Open the app

Visit [http://localhost:8080](http://localhost:8080). Click **Register** to generate an account code, then start creating short links from the dashboard.

## Environment Variables

| Variable | Description | Default (dev) |
|---|---|---|
| `APP_ENV` | Symfony environment (`dev` or `prod`) | `dev` |
| `APP_SECRET` | Symfony secret key for CSRF/signing | Set in `.env` |
| `DATABASE_URL` | MySQL connection string | `mysql://gotcha:gotcha@db:3306/gotcha?serverVersion=8.0&charset=utf8mb4` |

For production, set `APP_ENV=prod` and generate a strong `APP_SECRET`:

```bash
php -r "echo bin2hex(random_bytes(16));"
```

## How It Works

### Short Link Flow

1. Visitor opens `yourdomain.com/{slug}`
2. Server renders a loading page and generates an HMAC token
3. Client-side JavaScript collects browser metadata (screen size, timezone, language, etc.)
4. JS sends a `POST /api/track` with the metadata + HMAC token
5. Server validates the token, records the visit, and returns the target URL
6. JS redirects the visitor to the target URL

### Authentication

There are no passwords. Users register to get a UUID account code, which is stored in a `gotcha_account` cookie (1-year expiry, HttpOnly, SameSite=lax). To log in on another device, enter the account code manually.

## Production Deployment

### Docker Image

The root `Dockerfile` builds a self-contained production image (PHP-FPM + Nginx in one container):

```bash
docker build -t gotcha-url .
docker run -d \
  -p 80:80 \
  -e APP_ENV=prod \
  -e APP_SECRET=your-secret-here \
  -e DATABASE_URL="mysql://user:pass@host:3306/gotcha?serverVersion=8.0&charset=utf8mb4" \
  gotcha-url
```

Run migrations against your production database:

```bash
docker exec <container> bin/console doctrine:migrations:migrate --no-interaction
```

### Cloudflare

When deployed behind Cloudflare, visitor country and city are automatically extracted from `CF-IPCountry` and `CF-IPCity` headers. The Nginx config trusts `CF-Connecting-IP` for real client IPs. No extra configuration needed.

## Development

### Useful Commands

```bash
# Clear cache
docker compose exec php bin/console cache:clear

# Generate a migration after entity changes
docker compose exec php bin/console doctrine:migrations:diff

# Run migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Connect to MySQL
mysql -h 127.0.0.1 -P 3307 -u gotcha -pgotcha gotcha
```

### Running Tests

```bash
docker compose exec php ./vendor/bin/phpunit --testdox
```

### Project Structure

```
src/
  Controller/
    AuthController.php          # Login, register, logout
    DashboardController.php     # Link management + visit analytics
    RedirectController.php      # Short link → loading page
    TrackingApiController.php   # POST /api/track endpoint
  Entity/
    User.php                    # Account code auth, UUID primary key
    Link.php                    # Short link (slug + target URL)
    Visit.php                   # Visitor record (IP, UA, geo, etc.)
  Security/
    AccountCodeAuthenticator.php  # Cookie + POST login authenticator
    AccountCodeUserProvider.php   # Loads users by account code
  Service/
    SlugGenerator.php           # 7-char alphanumeric slug generator
    TrackingTokenService.php    # HMAC token generation/verification
templates/
  base.html.twig               # Shared layout (Bootstrap 5 + Matrix theme)
  redirect/loading.html.twig   # Standalone loading/tracking page
docker/
  php/Dockerfile               # Dev PHP-FPM image
  nginx/default.conf           # Nginx config with Cloudflare support
```

## License

Proprietary.
