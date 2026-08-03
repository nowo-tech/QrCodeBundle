# QR Code Bundle — Demo (Symfony 8)

This demo runs with **FrankenPHP** (Caddy + PHP **8.5**, extension **gd** for PNG QR codes). Runtime mode is selected with **`FRANKENPHP_MODE`** (`worker` default, or `classic`).

## Quick start

```bash
make up
# Open http://localhost:8012/demo  (PORT from .env / .env.example)
```

## Demo URLs

- `/` — Redirects to `/demo`
- `/demo` — QR samples (Twig helpers, UX component, `QrCodeService`)

## Web Profiler toolbar

**Web Profiler** and **Nowo Twig Inspector** are enabled in `dev` (`APP_ENV=dev`, `APP_DEBUG=1`).

```bash
docker compose exec php php bin/console cache:clear --env=dev
```

## Commands

- `make up` — Build/start FrankenPHP, composer install, link path bundle
- `make down` — Stop the container
- `make shell` — Shell in the container
- `make update-bundle` — Sync mounted bundle source
- `make verify` — HTTP 200 smoke on `/demo`

See [docs/DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md).
