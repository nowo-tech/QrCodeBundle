<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Security;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final class AllowAllQrCodeAccessChecker implements QrCodeAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
