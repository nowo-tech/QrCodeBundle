<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\EventSubscriber\ClosedEntityManagerSubscriber;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ClosedEntityManagerSubscriberTest extends TestCase
{
    public function testSubscribesOnRequest(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 31]],
            ClosedEntityManagerSubscriber::getSubscribedEvents(),
        );
    }

    public function testClosedManagerFromPreviousRequestIsResetOnNextMainRequest(): void
    {
        $open = true;
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturnCallback(static function () use (&$open): bool {
            return $open;
        });
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn([]);
        $em->method('getUnitOfWork')->willReturn($uow);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $em, 'other' => $this->createMock(ObjectManager::class)]);
        $registry->expects(self::once())->method('resetManager')->with('default')
            ->willReturnCallback(static function () use (&$open, $em): EntityManagerInterface {
                $open = true;

                return $em;
            });

        $subscriber = new ClosedEntityManagerSubscriber($registry);

        // Request 1: manager open; a failed flush then closes it.
        $subscriber->onKernelRequest($this->event());
        $open = false;

        // Request 2 on the same worker, no kernel reset in between (any main route).
        $subscriber->onKernelRequest($this->event('app_home'));
        self::assertTrue($em->isOpen());
    }

    public function testDetachesStaleProfileConfigsFromIdentityMap(): void
    {
        $stale = (new QrCodeProfileConfig())->setName('compact')->setSize(200);
        $other = new stdClass();

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn([
            QrCodeProfileConfig::class => ['1' => $stale],
            stdClass::class            => ['1' => $other],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects(self::once())->method('detach')->with($stale);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $em]);
        $registry->expects(self::never())->method('resetManager');

        (new ClosedEntityManagerSubscriber($registry))->onKernelRequest($this->event('nowo_qr_code_admin_index'));
    }

    public function testSkipsDetachWhenResetManagerDoesNotReturnEntityManager(): void
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('getUnitOfWork');

        $replacement = $this->createMock(ObjectManager::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $closed]);
        $registry->expects(self::once())->method('resetManager')->with('default')->willReturn($replacement);

        (new ClosedEntityManagerSubscriber($registry))->onKernelRequest($this->event());
    }

    public function testIgnoresNonProfileEntriesUnderProfileIdentityMapKey(): void
    {
        $junk = new stdClass();
        $uow  = $this->createMock(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn([
            QrCodeProfileConfig::class => ['x' => $junk],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects(self::never())->method('detach');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $em]);

        (new ClosedEntityManagerSubscriber($registry))->onKernelRequest($this->event());
    }

    public function testIgnoresSubRequests(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagers');

        $subscriber = new ClosedEntityManagerSubscriber($registry);
        $subscriber->onKernelRequest($this->event('nowo_qr_code_admin_index', HttpKernelInterface::SUB_REQUEST));
    }

    private function event(?string $route = 'nowo_qr_code_admin_index', int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = new Request();
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }

        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $type);
    }
}
