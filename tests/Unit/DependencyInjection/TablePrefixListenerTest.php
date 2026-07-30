<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\QrCodeBundle\DependencyInjection\TablePrefixListener;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use PHPUnit\Framework\TestCase;

final class TablePrefixListenerTest extends TestCase
{
    public function testEmptyPrefixIsNoOp(): void
    {
        $listener = new TablePrefixListener('');
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects(self::never())->method('setPrimaryTable');

        $event = $this->createMock(LoadClassMetadataEventArgs::class);
        $event->method('getClassMetadata')->willReturn($metadata);

        $listener->loadClassMetadata($event);
    }

    public function testAppliesPrefixToBundleEntities(): void
    {
        $listener = new TablePrefixListener('nowo_');
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(QrCodeProfileConfig::class);
        $metadata->method('getTableName')->willReturn('qr_code_profile');
        $metadata->expects(self::once())->method('setPrimaryTable')->with([
            'name' => 'nowo_qr_code_profile',
        ]);

        $event = $this->createMock(LoadClassMetadataEventArgs::class);
        $event->method('getClassMetadata')->willReturn($metadata);

        $listener->loadClassMetadata($event);
    }

    public function testIgnoresForeignEntities(): void
    {
        $listener = new TablePrefixListener('nowo_');
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn('App\\Entity\\User');
        $metadata->expects(self::never())->method('setPrimaryTable');

        $event = $this->createMock(LoadClassMetadataEventArgs::class);
        $event->method('getClassMetadata')->willReturn($metadata);

        $listener->loadClassMetadata($event);
    }
}
