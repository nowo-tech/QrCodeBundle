<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Security\UrlAllowlistPattern;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function explode;
use function implode;
use function is_array;
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
            $errorChoices['nowo_qr_code.admin.error_correction.' . $case->value] = $case->value;
        }

        $this->withBuilder($builder, function () use ($errorChoices): void {
            $this->addTextField('name', [
                'label'       => 'nowo_qr_code.admin.form.fields.name.label',
                'placeholder' => false,
                'help'        => 'nowo_qr_code.admin.form.fields.name.help',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 64),
                    new Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: 'Use letters, digits, underscore or hyphen.'),
                ],
            ]);
            $this->addIntegerField('size', [
                'label'       => 'nowo_qr_code.admin.form.fields.size.label',
                'placeholder' => false,
                'help'        => false,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Range(min: 64, max: 1024),
                ],
            ]);
            $this->addIntegerField('margin', [
                'label'       => 'nowo_qr_code.admin.form.fields.margin.label',
                'placeholder' => false,
                'help'        => false,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Range(min: 0, max: 64),
                ],
            ]);
            $this->addChoiceField('errorCorrection', [
                'label'   => 'nowo_qr_code.admin.form.fields.error_correction.label',
                'help'    => false,
                'choices' => $errorChoices,
            ]);
            $this->addTextareaField('urlAllowlist', [
                'label'       => 'nowo_qr_code.admin.form.fields.url_allowlist.label',
                'placeholder' => false,
                'required'    => false,
                'help'        => 'nowo_qr_code.admin.form.fields.url_allowlist.help',
                'attr'        => ['rows' => 5],
                'constraints' => [new Assert\Callback(static function (mixed $patterns, ExecutionContextInterface $context): void {
                    // Same rules as the YAML config: host-only `#…#`, anchored `url#^…#`, compiling, no nested quantifiers.
                    foreach (is_array($patterns) ? $patterns : [] as $pattern) {
                        $error = is_string($pattern) ? UrlAllowlistPattern::validate($pattern) : null;
                        if ($error !== null) {
                            $context->buildViolation($error)->addViolation();
                        }
                    }
                })],
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
            'data_class'         => QrCodeProfileConfig::class,
            'translation_domain' => 'NowoQrCodeBundle',
        ]);
    }
}
