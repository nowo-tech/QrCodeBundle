- **FormKitBundle** (`nowo-tech/form-kit-bundle` ^2.0) — dashboard/admin Symfony forms (`FormOptionsTrait`, profile `qr_code`). Register `NowoFormKitBundle` in `config/bundles.php` (Flex / demo). Optional host YAML: `config/packages/nowo_form_kit.yaml`.

# Installation

```bash
composer require nowo-tech/qr-code-bundle
```

`endroid/qr-code` is required and pulled in automatically. Enable PHP **gd** for PNG rendering.

Also pulls `symfony/twig-bundle` and `symfony/ux-twig-component` for Twig helpers and the `<twig:NowoQrCode>` component. Optionally install [`symfony/ux-toolkit`](https://symfony.com/bundles/ux-toolkit/current/index.html) in the host app (PHP 8.4+) for design-system kit components around the QR image.

## Symfony Flex

With Symfony Flex, the recipe under `.symfony/recipe/nowo-tech/qr-code-bundle/` registers the bundle, copies `config/packages/nowo_qr_code.yaml`, and imports admin routes via `config/routes/nowo_qr_code.yaml`.

## Optional database profiles

To store profiles in Doctrine (override YAML by name) and use `/admin/qr-code-profiles`:

```bash
composer require doctrine/orm doctrine/doctrine-bundle symfony/form symfony/validator
```

Then set `use_database_config: true` and create the `qr_code_profile` table. See [Configuration](CONFIGURATION.md#database-overrides-optional) and [Upgrading](UPGRADING.md).

## Without Flex

Add to `config/bundles.php`:

```php
Nowo\QrCodeBundle\NowoQrCodeBundle::class => ['all' => true],
```

And create `config/packages/nowo_qr_code.yaml` (see [Configuration](CONFIGURATION.md)).

## FrankenPHP demo

A Symfony **8** demo with FrankenPHP (PHP **8.5** + **gd**) lives under `demo/symfony8`:

```bash
make -C demo/symfony8 up
# http://localhost:8012/demo
```

See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.
