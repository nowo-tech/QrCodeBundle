<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

use function explode;
use function implode;
use function is_string;
use function trim;

/**
 * Admin form for a database-backed QR profile.
 *
 * @extends AbstractType<QrCodeProfileConfig>
 */
#[FormKitConfig('qr_code')]
final class QrCodeProfileConfigType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $errorChoices = [];
        foreach (QrErrorCorrection::cases() as $case) {
            $errorChoices[$case->value] = $case->value;
        }

        $this->withBuilder($builder, function () use ($errorChoices): void {
            $this->addTextField('name', [
                'label'       => 'Profile name',
                'placeholder' => false,
                'help'        => 'Must match a YAML profile name to override it; new names add DB-only profiles.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 64),
                    new Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: 'Use letters, digits, underscore or hyphen.'),
                ],
            ]);
            $this->addIntegerField('size', [
                'label'       => 'Size (px)',
                'placeholder' => false,
                'help'        => false,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Range(min: 64, max: 1024),
                ],
            ]);
            $this->addIntegerField('margin', [
                'label'       => 'Margin (px)',
                'placeholder' => false,
                'help'        => false,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Range(min: 0, max: 64),
                ],
            ]);
            $this->addChoiceField('errorCorrection', [
                'label'   => 'Error correction',
                'choices' => $errorChoices,
            ]);
            $this->addTextareaField('urlAllowlist', [
                'label'    => 'URL allowlist',
                'required' => false,
                'help'     => 'One pattern per line. Empty = any http(s) URL.',
                'attr'     => ['rows' => 5],
            ]);
        });

        $builder->get('urlAllowlist')->addModelTransformer(new CallbackTransformer(
            static fn (?array $patterns): string => $patterns === null || $patterns === [] ? '' : implode("\n", $patterns),
            static function (?string $text): array {
                if (!is_string($text) || trim($text) === '') {
                    return [];
                }

                $lines = [];
                foreach (explode("\n", $text) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }

                return $lines;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QrCodeProfileConfig::class,
        ]);
    }
}
