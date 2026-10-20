# Demo notes (FrankenPHP)

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`). Caddyfile: Mercure + `hot_reload` (and `worker { file …; watch }` in worker mode). Do not enable Hot Reload in production.

This bundle includes `demo/symfony8` — a sample Symfony **8** application under **FrankenPHP**.

The demo has its own `docker-compose.yml`, `Dockerfile` (PHP **8.5** + **gd** for PNG QR rendering), and `docker/frankenphp/` Caddyfile variants.

The **repository root** `docker-compose.yml` is for **bundle** development (PHPUnit, PHPStan, CS). It is not the same as launching the demo as a hosted app.

```bash
make -C demo/symfony8 up
# Open http://localhost:8012/demo  (PORT from demo/symfony8/.env.example)
```

This bundle is **FrankenPHP worker mode friendly**, including the strict case where the kernel is **not** reset between requests. Demos default to `FRANKENPHP_MODE=worker`. Full audit: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Table of contents

- [Demo smoke (REQ-TEST-011)](#demo-smoke-req-test-011)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Start the demo in development](#start-the-demo-in-development)
- [Start the demo in production mode](#start-the-demo-in-production-mode)
- [Troubleshooting](#troubleshooting)
- [What the demo shows](#what-the-demo-shows)

## Demo smoke (REQ-TEST-011)

From the repository root:

```bash
make demo-smoke
```

Boots `demo/symfony8`, asserts **HTTP 200** on `http://127.0.0.1:<PORT>/demo` (from `.env` / `.env.example`, default **8012**), then tears down.

Per-demo: `make -C demo/symfony8 verify`.

## Switching classic vs worker (`FRANKENPHP_MODE`)

Demos select the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`, hot-reload friendly) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.

## Start the demo in development

Development is the default: the demo compose file sets `APP_ENV=dev` / `APP_DEBUG=1`, mounts `docker/php-dev.ini` (`opcache.revalidate_freq=0`) and the bundle at `/var/qr-code-bundle`.

```bash
make -C demo/symfony8 up          # build + start, composer install, prints the URL
make -C demo/symfony8 logs        # follow FrankenPHP logs
make -C demo/symfony8 update-bundle   # re-sync the mounted bundle after editing src/
```

For edit-and-refresh without worker restarts set `FRANKENPHP_MODE=classic` in `demo/symfony8/.env` and recreate the container (`docker compose up -d`): the entrypoint then uses `Caddyfile.dev` (plain `php_server` with `hot_reload`).

## Start the demo in production mode

The demo is not a production deployment, but the production runtime can be reproduced locally:

1. Keep `FRANKENPHP_MODE=worker` (default): `Caddyfile` runs `php_server { worker { file /app/public/index.php; watch } }`.
2. Run with `APP_ENV=prod` and `APP_DEBUG=0` (override the `environment:` block or use a compose override file), then install without dev dependencies and warm the cache inside the container:

```bash
docker compose -f demo/symfony8/docker-compose.yml exec php composer install --no-dev --optimize-autoloader
docker compose -f demo/symfony8/docker-compose.yml exec php php bin/console cache:clear --env=prod
```

Twig Inspector, Hot Reload and the Web Profiler are dev-only bundles and are not loaded in `prod`.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| Twig or PHP changes do not appear | In worker mode the kernel stays in memory: switch to `FRANKENPHP_MODE=classic`, or restart the container (`make -C demo/symfony8 restart`). |
| `FRANKENPHP_MODE` change has no effect | Env is read at container creation: run `docker compose up -d` (recreate), not `docker compose restart`. |
| Port already in use | Change `PORT` in `demo/symfony8/.env` (default **8012**) and run `make -C demo/symfony8 up` again. |
| Blank QR / `gd` errors | The demo image ships PHP **8.5** + **gd**; rebuild it with `make -C demo/symfony8 build` after Dockerfile changes. |
| `composer install` fails with curl error 6 | Docker/WSL DNS issue; the compose file pins public DNS servers — retry, or check the host network. |
| Bundle changes not picked up | Run `make -C demo/symfony8 update-bundle` (re-syncs the mounted bundle, autoload and cache). |

## What the demo shows

- Twig helpers `qr_code_data_uri` / `qr_code_for_url` with profiles `default` and `compact`
- UX component `<twig:NowoQrCode>`
- PHP `QrCodeService::createDataUri` / `createDataUriForUrl`
- Web Profiler + Nowo Twig Inspector in `dev`
