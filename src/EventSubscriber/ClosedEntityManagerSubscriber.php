<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps Doctrine usable for this bundle when the kernel is not reset between requests.
 *
 * Under FrankenPHP worker mode without `services_resetter` / kernel reboot:
 * - a failed flush leaves the EntityManager closed for later requests of that worker;
 * - managed {@see QrCodeProfileConfig} rows stay in the identity map and hide admin edits
 *   made in another worker.
 *
 * On every main request this subscriber resets closed managers and detaches only
 * {@see QrCodeProfileConfig} instances so the next admin load / import sees fresh rows.
 * Other managed entities of the host application are left untouched.
 */
final readonly class ClosedEntityManagerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After RouterListener (32) is unnecessary for manager hygiene; keep 31 for stable ordering.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 31],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        foreach ($this->registry->getManagers() as $name => $manager) {
            if (!$manager instanceof EntityManagerInterface) {
                continue;
            }

            if (!$manager->isOpen()) {
                $manager = $this->registry->resetManager($name);
                if (!$manager instanceof EntityManagerInterface) {
                    continue;
                }
            }

            $this->detachProfileConfigs($manager);
        }
    }

    private function detachProfileConfigs(EntityManagerInterface $manager): void
    {
        $identityMap = $manager->getUnitOfWork()->getIdentityMap();
        $managed     = $identityMap[QrCodeProfileConfig::class] ?? [];
        foreach ($managed as $entity) {
            if ($entity instanceof QrCodeProfileConfig) {
                $manager->detach($entity);
            }
        }
    }
}
