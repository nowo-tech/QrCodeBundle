<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function in_array;
use function is_string;
use function strtolower;
use function trim;

/**
 * When url_allowlist_required is true, empty or Flex-placeholder allowlists fail compilation (REQ-SEC-004).
 */
final class UrlAllowlistValidationPass implements CompilerPassInterface
{
    private const PARAM_ALLOWLIST          = 'nowo_qr_code.url_allowlist';
    private const PARAM_ALLOWLIST_REQUIRED = 'nowo_qr_code.url_allowlist_required';

    /** @var list<string> */
    private const PLACEHOLDER_HOSTS = ['example.com', 'www.example.com'];

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

        if ($this->isFlexPlaceholderAllowlist($allowlist)) {
            throw new InvalidConfigurationException('nowo_qr_code.url_allowlist_required is true but the default profile url_allowlist is only the Flex placeholder example.com. Replace it with your production hosts (or set url_allowlist_required: false for local demos only).');
        }
    }

    /**
     * @param list<string> $allowlist
     */
    private function isFlexPlaceholderAllowlist(array $allowlist): bool
    {
        foreach ($allowlist as $entry) {
            if (!is_string($entry)) {
                return false;
            }

            $host = strtolower(trim($entry));
            if (!in_array($host, self::PLACEHOLDER_HOSTS, true)) {
                return false;
            }
        }

        return true;
    }
}
