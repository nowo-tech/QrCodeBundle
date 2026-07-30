<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\QrCodeBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function dirname;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class TwigPathsPassTest extends TestCase
{
    public function testProcessAddsVendorPath(): void
    {
        $tmp = sys_get_temp_dir() . '/qr_twig_' . uniqid('', true);
        self::assertTrue(mkdir($tmp, 0777, true));

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $tmp);
            $loaderDef = new Definition();
            $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

            (new TwigPathsPass())->process($container);

            $calls = $loaderDef->getMethodCalls();
            self::assertCount(1, $calls);
            self::assertSame('addPath', $calls[0][0]);
            self::assertSame('NowoQrCodeBundle', $calls[0][1][1]);
            self::assertStringEndsWith('/Resources/views', $calls[0][1][0]);
            self::assertSame(
                dirname(__DIR__, 4) . '/src/Resources/views',
                $calls[0][1][0],
            );
        } finally {
            rmdir($tmp);
        }
    }

    public function testProcessPrependsOverrideDirectoryWhenPresent(): void
    {
        $tmp          = sys_get_temp_dir() . '/qr_twig_' . uniqid('', true);
        $overridePath = $tmp . '/templates/bundles/NowoQrCodeBundle';
        self::assertTrue(mkdir($overridePath, 0777, true));

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $tmp);
            $loaderDef = new Definition();
            $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

            (new TwigPathsPass())->process($container);

            $calls = $loaderDef->getMethodCalls();
            self::assertSame('prependPath', $calls[0][0]);
            self::assertSame([$overridePath, 'NowoQrCodeBundle'], $calls[0][1]);
            self::assertSame('addPath', $calls[1][0]);
            self::assertSame('NowoQrCodeBundle', $calls[1][1][1]);
        } finally {
            rmdir($overridePath);
            rmdir($tmp . '/templates/bundles');
            rmdir($tmp . '/templates');
            rmdir($tmp);
        }
    }

    public function testProcessUsesTwigLoaderNativeAlias(): void
    {
        $tmp = sys_get_temp_dir() . '/qr_twig_' . uniqid('', true);
        self::assertTrue(mkdir($tmp, 0777, true));

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $tmp);
            $loaderDef = new Definition();
            $container->setDefinition('twig.loader.native_filesystem', $loaderDef);
            $container->setAlias('twig.loader.native_mid', 'twig.loader.native_filesystem');
            $container->setAlias('twig.loader.native', 'twig.loader.native_mid');

            (new TwigPathsPass())->process($container);

            $calls = $loaderDef->getMethodCalls();
            self::assertCount(1, $calls);
            self::assertSame('addPath', $calls[0][0]);
            self::assertSame('NowoQrCodeBundle', $calls[0][1][1]);
        } finally {
            rmdir($tmp);
        }
    }

    public function testProcessUsesTwigLoaderNativeDefinition(): void
    {
        $tmp = sys_get_temp_dir() . '/qr_twig_' . uniqid('', true);
        self::assertTrue(mkdir($tmp, 0777, true));

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $tmp);
            $loaderDef = new Definition();
            $container->setDefinition('twig.loader.native', $loaderDef);

            (new TwigPathsPass())->process($container);

            $calls = $loaderDef->getMethodCalls();
            self::assertCount(1, $calls);
            self::assertSame('addPath', $calls[0][0]);
        } finally {
            rmdir($tmp);
        }
    }

    public function testProcessSkipsOverrideWhenProjectDirIsNotString(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', 123);
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

        (new TwigPathsPass())->process($container);

        $calls = $loaderDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
    }

    public function testProcessIsNoOpWithoutTwigLoader(): void
    {
        $container = new ContainerBuilder();
        (new TwigPathsPass())->process($container);
        $this->addToAssertionCount(1);
    }
}
