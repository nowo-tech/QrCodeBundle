<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Security;

use Nowo\QrCodeBundle\Security\AllowAllQrCodeAccessChecker;
use Nowo\QrCodeBundle\Security\ConfigurableQrCodeAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class QrCodeAccessCheckerTest extends TestCase
{
    public function testAllowAll(): void
    {
        self::assertTrue((new AllowAllQrCodeAccessChecker())->canAccess());
    }

    public function testConfigurableRequiresMatchingRole(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => $attribute === 'ROLE_ADMIN',
        );

        $checker = new ConfigurableQrCodeAccessChecker($auth, ['ROLE_USER', 'ROLE_ADMIN']);
        self::assertTrue($checker->canAccess());
    }

    public function testEmptyRolesAllowAccess(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects(self::never())->method('isGranted');

        self::assertTrue((new ConfigurableQrCodeAccessChecker($auth, []))->canAccess());
    }

    public function testConfigurableDeniesWithoutMatchingRole(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        self::assertFalse((new ConfigurableQrCodeAccessChecker($auth, ['ROLE_ADMIN']))->canAccess());
    }
}
