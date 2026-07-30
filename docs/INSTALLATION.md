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
