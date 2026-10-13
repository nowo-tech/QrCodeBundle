<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Default role-based access checker driven by nowo_qr_code.security.access_roles.
 *
 * Empty access_roles = deny (fail-closed). Use security.allow_unauthenticated (demo/dev) to open the admin.
 */
final readonly class ConfigurableQrCodeAccessChecker implements QrCodeAccessCheckerInterface
{
    /**
     * @param list<string> $accessRoles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
    ) {
    }

    public function canAccess(): bool
    {
        // Empty access_roles = deny (fail-closed). Use allow_unauthenticated or AllowAll* for demos.
        if ($this->accessRoles === []) {
            return false;
        }

        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
