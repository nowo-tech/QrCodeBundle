<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle;

use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for PNG QR codes as data URIs with optional URL policy.
 */
final class NowoQrCodeBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new NowoQrCodeExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
