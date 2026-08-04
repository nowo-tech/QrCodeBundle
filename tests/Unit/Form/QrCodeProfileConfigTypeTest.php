<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Form\QrCodeProfileConfigType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class QrCodeProfileConfigTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $type = new QrCodeProfileConfigType();
        $type->setFormOptionsMerger($this->formOptionsMerger());

        $validator     = Validation::createValidator();
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CoreExtension())
            ->addExtension(new ValidatorExtension($validator))
            ->addType($type)
            ->getFormFactory();
    }

    public function testSubmitMapsAllowlistLines(): void
    {
        $entity = new QrCodeProfileConfig();
        $form   = $this->factory->create(QrCodeProfileConfigType::class, $entity);
        $form->submit([
            'name'            => 'wallet',
            'size'            => 256,
            'margin'          => 4,
            'errorCorrection' => QrErrorCorrection::Medium->value,
            'urlAllowlist'    => "example.com\n\ncdn.example.com\n",
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('wallet', $entity->getName());
        self::assertSame(256, $entity->getSize());
        self::assertSame(4, $entity->getMargin());
        self::assertSame('medium', $entity->getErrorCorrection());
        self::assertSame(['example.com', 'cdn.example.com'], $entity->getUrlAllowlist());
    }

    public function testEmptyAllowlistBecomesEmptyArray(): void
    {
        $entity = (new QrCodeProfileConfig())->setUrlAllowlist(['old.com']);
        $form   = $this->factory->create(QrCodeProfileConfigType::class, $entity);
        $form->submit([
            'name'            => 'default',
            'size'            => 300,
            'margin'          => 10,
            'errorCorrection' => QrErrorCorrection::High->value,
            'urlAllowlist'    => '',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame([], $entity->getUrlAllowlist());
    }

    private function formOptionsMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger(
            [
                'qr_code' => [
                    'translation_domain' => 'NowoQrCodeBundle',
                    'defaults'           => [
                        'attr'     => [],
                        'row_attr' => [],
                    ],
                    'field_types' => [],
                ],
            ],
            'qr_code',
            new ConstraintDefinitionFactory(),
        );
    }
}
