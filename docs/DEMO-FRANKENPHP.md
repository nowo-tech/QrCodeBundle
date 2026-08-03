# Demo notes (FrankenPHP)

This bundle includes `demo/symfony8` — a sample Symfony **8** application under **FrankenPHP**.

The demo has its own `docker-compose.yml`, `Dockerfile` (PHP **8.5** + **gd** for PNG QR rendering), and `docker/frankenphp/` Caddyfile variants.

The **repository root** `docker-compose.yml` is for **bundle** development (PHPUnit, PHPStan, CS). It is not the same as launching the demo as a hosted app.

```bash
make -C demo/symfony8 up
# Open http://localhost:8012/demo  (PORT from demo/symfony8/.env.example)
```

This bundle is **FrankenPHP worker mode friendly**. Demos default to `FRANKENPHP_MODE=worker`.

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

## What the demo shows

- Twig helpers `qr_code_data_uri` / `qr_code_for_url` with profiles `default` and `compact`
- UX component `<twig:NowoQrCode>`
- PHP `QrCodeService::createDataUri` / `createDataUriForUrl`
- Web Profiler + Nowo Twig Inspector in `dev`
