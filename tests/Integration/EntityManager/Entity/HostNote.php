<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\EntityManager\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A host entity mapped by the catch-all default driver of the host manager ({@see HostDefaultDriverTestKernel}).
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_note')]
class HostNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;
}
