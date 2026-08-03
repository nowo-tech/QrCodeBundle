<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Controller\QrCodeProfileAdminController;
use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\DependencyInjection\TablePrefixListener;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Security\AllowAllQrCodeAccessChecker;
use Nowo\QrCodeBundle\Security\ConfigurableQrCodeAccessChecker;
use Nowo\QrCodeBundle\Security\QrCodeAccessCheckerInterface;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

final class NowoQrCodeExtensionDatabaseTest extends TestCase
{
    public function testLoadsDatabaseServicesAndAllowAllAccessChecker(): void
    {
        $container = $this->baseContainer();
        $extension = new NowoQrCodeExtension();
        $extension->load([[
            'use_database_config' => true,
            'doctrine'            => ['table_prefix' => 'app_'],
            'security'            => ['allow_unauthenticated' => true],
        ]], $container);

        self::assertTrue($container->getParameter('nowo_qr_code.use_database_config'));
        self::assertSame('app_', $container->getParameter('nowo_qr_code.doctrine.table_prefix'));
        self::assertTrue($container->hasDefinition(QrCodeProfileConfigRepository::class));
        self::assertTrue($container->hasDefinition(QrCodeProfileAdminService::class));
        self::assertTrue($container->hasDefinition(QrCodeProfileAdminController::class));
        self::assertTrue($container->hasDefinition(TablePrefixListener::class));
        self::assertTrue($container->hasDefinition('nowo_qr_code.access_checker.allow_all'));
        self::assertSame(
            AllowAllQrCodeAccessChecker::class,
            $container->getDefinition('nowo_qr_code.access_checker.allow_all')->getClass(),
        );

        $resolverArgs = $container->getDefinition(ProfileResolver::class)->getArguments();
        self::assertInstanceOf(Reference::class, $resolverArgs['$profileRepository'] ?? $resolverArgs[3] ?? null);
    }

    public function testRegistersConfigurableAccessCheckerWithoutSecurityService(): void
    {
        $container = $this->baseContainer();

        $extension = new NowoQrCodeExtension();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires symfony/security-bundle');

        $extension->load([[
            'use_database_config' => true,
            'security'            => [
                'allow_unauthenticated' => false,
                'access_roles'          => ['ROLE_ADMIN'],
            ],
        ]], $container);
    }

    public function testRegistersConfigurableAccessCheckerWithAuthorizationChecker(): void
    {
        $container = $this->baseContainer();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'Symfony\\Bundle\\SecurityBundle\\SecurityBundle']);
        $container->register('security.authorization_checker', stdClass::class);

        $extension = new NowoQrCodeExtension();
        $extension->load([[
            'use_database_config' => true,
            'security'            => [
                'allow_unauthenticated' => false,
                'access_roles'          => ['ROLE_ADMIN'],
            ],
        ]], $container);

        $definition = $container->getDefinition('nowo_qr_code.access_checker.default');
        self::assertSame(ConfigurableQrCodeAccessChecker::class, $definition->getClass());
        self::assertInstanceOf(Reference::class, $definition->getArgument('$authorizationChecker'));
    }

    public function testAcceptsSecurityBundleViaRegisteredExtension(): void
    {
        $container = $this->baseContainer();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });

        $extension = new NowoQrCodeExtension();
        $extension->load([[
            'use_database_config' => true,
            'security'            => [
                'allow_unauthenticated' => false,
                'access_roles'          => ['ROLE_ADMIN'],
            ],
        ]], $container);

        $definition = $container->getDefinition('nowo_qr_code.access_checker.default');
        self::assertSame(ConfigurableQrCodeAccessChecker::class, $definition->getClass());
        self::assertTrue($definition->isAutowired());
    }

    public function testRegistersCustomAccessCheckerAlias(): void
    {
        $container = $this->baseContainer();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'Symfony\\Bundle\\SecurityBundle\\SecurityBundle']);
        $container->register('app.qr_access', AllowAllQrCodeAccessChecker::class);

        $extension = new NowoQrCodeExtension();
        $extension->load([[
            'use_database_config' => true,
            'security'            => [
                'allow_unauthenticated' => false,
                'access_checker'        => 'app.qr_access',
            ],
        ]], $container);

        self::assertSame('app.qr_access', (string) $container->getAlias(QrCodeAccessCheckerInterface::class));
    }

    private function baseContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag());
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);

        return $container;
    }
}
