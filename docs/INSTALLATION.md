# Installation

```bash
composer require nowo-tech/qr-code-bundle
```

`endroid/qr-code` is required and pulled in automatically. Enable PHP **gd** for PNG rendering.

## Symfony Flex

With Symfony Flex, the recipe under `.symfony/recipe/nowo-tech/qr-code-bundle/` registers the bundle and copies `config/packages/nowo_qr_code.yaml`.

## Without Flex

Add to `config/bundles.php`:

```php
Nowo\QrCodeBundle\NowoQrCodeBundle::class => ['all' => true],
```

And create `config/packages/nowo_qr_code.yaml` (see [Configuration](CONFIGURATION.md)).
