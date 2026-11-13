<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\QrCodeBundle\Doctrine\ExcludedEntityNamespaceDriver;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use PHPUnit\Framework\TestCase;

final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    public function testMapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(QrCodeProfileConfig::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(QrCodeProfileConfig::class);
        $driver->loadMetadataForClass(QrCodeProfileConfig::class, new ClassMetadata(QrCodeProfileConfig::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the bundle entities.
     */
    public function testWinsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(QrCodeProfileConfig::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), 'Nowo\\QrCodeBundle\\Entity');

        self::assertTrue($chain->isTransient(QrCodeProfileConfig::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
