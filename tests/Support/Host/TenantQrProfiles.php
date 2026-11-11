<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Support\Host;

use Nowo\QrCodeBundle\Config\QrCodeProfile;
use Nowo\QrCodeBundle\Config\QrCodeProfileProviderInterface;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Symfony\Component\HttpFoundation\RequestStack;

use function is_string;

/**
 * A host's own profile source: the `default` profile of the current request's tenant (size and allowed host per
 * tenant), resolved per call from the request — no state kept between requests.
 */
final readonly class TenantQrProfiles implements QrCodeProfileProviderInterface
{
    /** tenant => [size, allowed host] */
    public const TENANTS = ['acme' => [120, 'acme.example'], 'globex' => [240, 'globex.example']];

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function find(string $name): ?QrCodeProfile
    {
        $tenant = $this->requests->getCurrentRequest()?->attributes->get('tenant');
        if ($name !== 'default' || !is_string($tenant) || !isset(self::TENANTS[$tenant])) {
            return null;
        }
        [$size, $host] = self::TENANTS[$tenant];

        return new QrCodeProfile('default', $size, 0, QrErrorCorrection::Medium, [$host]);
    }

    public function names(): array
    {
        return ['default'];
    }
}
