<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\QrCodeBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\QrCodeBundle\DependencyInjection\Compiler\EntityManagerOptionPass;
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
        $container->addCompilerPass(new CsrfProtectionPass());

        $entityDir = __DIR__ . '/Entity';
        if (class_exists(DoctrineOrmMappingsPass::class) && is_dir($entityDir)) {
            // 1.12.0: mapped on nowo_qr_code.doctrine.entity_manager when set (else the default manager), and removed
            // from every other manager (EntityManagerOptionPass, audit LD-QR-M1).
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\QrCodeBundle\\Entity'],
                [$entityDir],
                [EntityManagerOptionPass::PARAMETER],
                // 1.13: only when Doctrine ORM is configured or the profile table is used (rule 8).
                NowoQrCodeExtension::MAPPING_ENABLED_PARAMETER,
            ));
            $container->addCompilerPass(new EntityManagerOptionPass());
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
        $this->extension ??= new NowoQrCodeExtension();

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
