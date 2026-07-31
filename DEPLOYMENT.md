# Production deployment

This guide deploys the application to one Debian VDS. It uses Docker, PostgreSQL, Redis, Laravel Horizon, the Laravel scheduler, Nginx and Let's Encrypt.

## 1. Server and DNS

- Create a Debian 13 VDS with Docker, 4 vCPU, 4 GB RAM and at least 40 GB NVMe.
- Assign an IPv4 address.
- Point the `A` record for the production domain to that address.
- Allow only SSH, HTTP and HTTPS in the firewall. Do not publish PostgreSQL or Redis.

Create a non-root deployment account and add it to the Docker group:

```bash
adduser deploy
usermod -aG sudo,docker deploy
```

Log in again as `deploy` before running Docker commands.

## 2. Application secrets

Clone the repository to `/srv/auction-bot`, then create the real environment file:

```bash
sudo mkdir -p /srv/auction-bot
sudo chown deploy:deploy /srv/auction-bot
sudo -iu deploy
git clone <REPOSITORY_URL> /srv/auction-bot
cd /srv/auction-bot
cp .env.production.example .env.production
chmod 600 .env.production
```

Fill every `CHANGE_TO_...` value. Set `DOMAIN` to the DNS name and `CERTBOT_EMAIL` to a monitored mailbox. `APP_URL` must use the same HTTPS domain.

Generate an application key after the first container start; do not generate it locally. The production environment file is injected into containers and is not mounted as `/var/www/html/.env`, so generate the value with `--show` and paste it into `.env.production` on the host.

For external database backups, create an S3-compatible bucket and credentials. Set `RESTIC_REPOSITORY`, `RESTIC_PASSWORD`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`. The bucket must not be on this VDS.

## 3. First startup

```bash
docker compose --env-file .env.production -f compose.prod.yml up -d --build
```

Generate the key and add the displayed value to `APP_KEY` in `/srv/auction-bot/.env.production`:

```bash
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan key:generate --show
nano .env.production
docker compose --env-file .env.production -f compose.prod.yml up -d --force-recreate app horizon scheduler
```

Then run migrations and cache application metadata:

```bash
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan migrate --force
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan config:cache
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan event:cache
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan view:cache
docker compose --env-file .env.production -f compose.prod.yml restart app horizon scheduler
```

Nginx starts with a temporary one-day self-signed certificate only until Certbot obtains the real certificate. The temporary certificate is stored separately from Let's Encrypt data. The HTTP ACME challenge is available immediately. Verify that DNS points to the server and that ports 80 and 443 are public.

Check certificate issuance and containers:

```bash
docker compose --env-file .env.production -f compose.prod.yml logs -f certbot
docker compose --env-file .env.production -f compose.prod.yml ps
```

The Certbot service checks renewal every 12 hours and signals Nginx to reload the renewed certificate.

## 4. Create the first administrator

```bash
docker compose --env-file .env.production -f compose.prod.yml exec app php artisan admin:create admin@example.com "Administrator"
```

Open `https://DOMAIN/admin` and sign in.

The Horizon dashboard is available at `https://DOMAIN/horizon`. It is protected by the same active administrator session as the admin panel: sign in at `/admin` first, then open `/horizon` in the same browser.

## 5. Telegram webhook

Set the Telegram webhook URL to:

```text
https://DOMAIN/api/telegram/webhook
```

Use the same secret that is set in `TELEGRAM_WEBHOOK_SECRET`.

## 6. Updates

Before each update, confirm that the latest external backup completed. Then:

```bash
cd /srv/auction-bot
git pull --ff-only
docker compose --env-file .env.production -f compose.prod.yml up -d --build
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan migrate --force
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan optimize:clear
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan package:discover --ansi
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan config:cache
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan event:cache
docker compose --env-file .env.production -f compose.prod.yml exec -T app php artisan view:cache
docker compose --env-file .env.production -f compose.prod.yml restart app horizon scheduler
```

Do not use the local `compose.yaml` on the server.
