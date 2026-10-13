<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Entity;

use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validation;

final class QrCodeProfileConfigTest extends TestCase
{
    public function testToProfileArray(): void
    {
        $entity = (new QrCodeProfileConfig())
            ->setName('wallet')
            ->setSize(256)
            ->setMargin(4)
            ->setErrorCorrection(QrErrorCorrection::Medium->value)
            ->setUrlAllowlist(['example.com', 'cdn.example.com']);

        self::assertNull($entity->getId());
        self::assertSame('wallet', $entity->getName());
        self::assertSame([
            'size'             => 256,
            'margin'           => 4,
            'error_correction' => 'medium',
            'url_allowlist'    => ['example.com', 'cdn.example.com'],
        ], $entity->toProfileArray());
    }

    public function testDeclaresUniqueEntityOnNameSoDuplicatesFailValidationInsteadOf500(): void
    {
        $metadata = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->getMetadataFor(QrCodeProfileConfig::class);
        self::assertInstanceOf(ClassMetadata::class, $metadata);

        $unique = array_values(array_filter(
            $metadata->getConstraints(),
            static fn (object $c): bool => $c instanceof UniqueEntity,
        ));

        self::assertCount(1, $unique);
        self::assertSame(['name'], (array) $unique[0]->fields);
    }
}
