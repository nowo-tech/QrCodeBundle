<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_search;
use function is_string;

/**
 * Keeps Doctrine usable for this bundle when the kernel is not reset between requests.
 *
 * Under FrankenPHP worker mode without `services_resetter` / kernel reboot:
 * - a failed flush leaves the EntityManager closed for later requests of that worker;
 * - managed {@see QrCodeProfileConfig} rows stay in the identity map and hide admin edits
 *   made in another worker.
 *
 * On every main request this subscriber resets the bundle's manager (the one that manages
 * {@see QrCodeProfileConfig}) when closed, and detaches only {@see QrCodeProfileConfig} instances from it so the next
 * admin load / import sees fresh rows. Since 1.11.2 no other manager of the host, closed or open, is touched.
 */
final readonly class ClosedEntityManagerSubscriber implements EventSubscriberInterface
{
    /**
     * @param string|null $entityManagerName `nowo_qr_code.doctrine.entity_manager` (null: the manager of QrCodeProfileConfig)
     */
    public function __construct(
        private ManagerRegistry $registry,
        private ?string $entityManagerName = null,
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

        $name = $this->bundleManagerName();
        if ($name === null) {
            return;
        }

        $manager = $this->registry->getManager($name);
        if (!$manager instanceof EntityManagerInterface) {
            return;
        }
        if (!$manager->isOpen()) {
            $manager = $this->registry->resetManager($name);
            if (!$manager instanceof EntityManagerInterface) {
                return;
            }
        }

        $this->detachProfileConfigs($manager);
    }

    /**
     * The manager of QrCodeProfileConfig: `doctrine.entity_manager`, else resolved with getManagerForClass().
     */
    private function bundleManagerName(): ?string
    {
        if ($this->entityManagerName !== null) {
            return $this->entityManagerName;
        }

        $manager = $this->registry->getManagerForClass(QrCodeProfileConfig::class);
        $name    = $manager instanceof ObjectManager ? array_search($manager, $this->registry->getManagers(), true) : false;

        return is_string($name) ? $name : null;
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
