<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Security;

/**
 * Decides whether the current request may access QR profile admin CRUD.
 */
interface QrCodeAccessCheckerInterface
{
    public function canAccess(): bool;
}
