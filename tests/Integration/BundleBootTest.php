<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration;

use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\NowoQrCodeBundle;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Nowo\QrCodeBundle\Twig\QrCodeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Boots the DI extension and resolves the public QR service graph.
 */
final class BundleBootTest extends TestCase
{
    public function testBundleExposesExtension(): void
    {
        $bundle = new NowoQrCodeBundle();

        self::assertInstanceOf(NowoQrCodeExtension::class, $bundle->getContainerExtension());
    }

    public function testExtensionWiresServiceGraph(): void
    {
        $container = new ContainerBuilder(new ParameterBag());
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);

        $extension = new NowoQrCodeExtension();
        $extension->load([], $container);

        self::assertTrue($container->hasDefinition(QrCodeService::class));
        self::assertTrue($container->hasDefinition(QrCodeExtension::class));

        $container->getDefinition(QrCodeService::class)->setPublic(true);
        $container->getDefinition(QrCodeExtension::class)->setPublic(true);

        $container->compile();

        /** @var QrCodeService $service */
        $service = $container->get(QrCodeService::class);
        $dataUri = $service->createDataUri('integration-boot');

        self::assertStringStartsWith('data:image/png;base64,', $dataUri);

        /** @var QrCodeExtension $twig */
        $twig = $container->get(QrCodeExtension::class);
        self::assertSame($dataUri, $twig->qrCodeDataUri('integration-boot'));
    }
}
