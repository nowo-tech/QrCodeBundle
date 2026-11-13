<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration;

use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\NowoQrCodeBundle;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Nowo\QrCodeBundle\Service\QrCodeServiceInterface;
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

    public function testInterfaceIsAliasedAndAHostCanOverrideIt(): void
    {
        $container = new ContainerBuilder(new ParameterBag());
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);
        (new NowoQrCodeExtension())->load([], $container);

        self::assertSame(QrCodeService::class, (string) $container->getAlias(QrCodeServiceInterface::class));

        $container->register('app.qr', HostQrService::class);
        $container->setAlias(QrCodeServiceInterface::class, 'app.qr')->setPublic(true);
        $container->getDefinition(QrCodeExtension::class)->setPublic(true);
        $container->compile();

        $twig = $container->get(QrCodeExtension::class);
        self::assertInstanceOf(QrCodeExtension::class, $twig);
        self::assertSame('host:abc', $twig->qrCodeDataUri('abc'));
    }
}

/** Host replacement used by the alias override test. */
final class HostQrService implements QrCodeServiceInterface
{
    public function createDataUri(string $content, ?string $profile = null): string
    {
        return 'host:' . $content;
    }

    public function createDataUriForUrl(string $url, ?string $profile = null): string
    {
        return 'host-url:' . $url;
    }
}
