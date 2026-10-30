<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when the admin UI is enabled without CSRF protection (REQ-SEC-005, cross-bundle pattern P2).
 *
 * The profile admin (`use_database_config: true`) validates the delete / import tokens with isCsrfTokenValid() and
 * relies on Symfony's form CSRF extension for the new / edit form. A host that disables
 * `framework.form.csrf_protection` (or has no CSRF token manager) would silently turn the form check off, so the
 * container refuses to compile instead.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_qr_code.use_database_config') || $container->getParameter('nowo_qr_code.use_database_config') !== true) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoQrCodeBundle: the profile admin (use_database_config: true) requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf symfony/form), or set nowo_qr_code.use_database_config: false.');
        }
    }
}
