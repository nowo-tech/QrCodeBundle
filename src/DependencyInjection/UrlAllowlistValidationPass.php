<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * When url_allowlist_required is true, empty default-profile allowlist fails compilation (REQ-SEC-004).
 */
final class UrlAllowlistValidationPass implements CompilerPassInterface
{
    private const PARAM_ALLOWLIST          = 'nowo_qr_code.url_allowlist';
    private const PARAM_ALLOWLIST_REQUIRED = 'nowo_qr_code.url_allowlist_required';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAM_ALLOWLIST)) {
            return;
        }

        $allowlistRequired = $container->hasParameter(self::PARAM_ALLOWLIST_REQUIRED)
            && (bool) $container->getParameter(self::PARAM_ALLOWLIST_REQUIRED);

        if (!$allowlistRequired) {
            return;
        }

        /** @var list<string> $allowlist */
        $allowlist = $container->getParameter(self::PARAM_ALLOWLIST);

        if ($allowlist === []) {
            throw new InvalidConfigurationException('nowo_qr_code.url_allowlist_required is true but the default profile url_allowlist is empty. Add host patterns (or set url_allowlist_required: false for local demos only).');
        }
    }
}
