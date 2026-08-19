<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\QrCodeBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\DependencyInjection\UrlAllowlistValidationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function class_exists;
use function is_dir;

/**
 * Symfony bundle for PNG QR codes as data URIs with optional URL policy.
 */
final class NowoQrCodeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new UrlAllowlistValidationPass());

        $entityDir = __DIR__ . '/Entity';
        if (class_exists(DoctrineOrmMappingsPass::class) && is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\QrCodeBundle\\Entity'],
                [$entityDir],
            ));
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new NowoQrCodeExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
