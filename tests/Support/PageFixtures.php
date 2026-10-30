<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Form\QrCodeProfileConfigType;
use Nowo\QrCodeBundle\Ui\QrCodePageFactory;
use Nowo\QrCodeBundle\Ui\QrCodePages;
use Nowo\UiKitBundle\View\Pagination;
use ReflectionProperty;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

use function http_build_query;

/**
 * Page contexts of the REQ-UI-003 page contract test: the view models come from {@see QrCodePageFactory} (the service
 * the controller uses), the FormViews from the real form type (FormKit profile merger, CSRF), the other variables
 * mirror the controller.
 */
final class PageFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function index(bool $empty = false, bool $useDatabaseConfig = true, ?QrCodePageFactory $factory = null): array
    {
        $profiles = $empty ? [] : [
            self::profile(1, 'default', 300, 10, 'high', []),
            self::profile(2, 'wallet', 256, 4, 'medium', ['example.com', 'pass.example.com']),
        ];
        $deleteForms = [];
        foreach ($profiles as $profile) {
            $deleteForms[(int) $profile->getId()] = self::csrfForm('/admin/qr-code-profiles/' . $profile->getId() . '/delete', 'delete_qr_profile_' . $profile->getId());
        }
        $pagination = $empty
            ? Pagination::fromTotal(0, 1, 50, [50], 50, 'nowo_qr_code_admin_index')
            : Pagination::fromTotal(3, 1, 2, [2], 2, 'nowo_qr_code_admin_index');
        $factory ??= self::factory();

        return $factory->context([
            'page'                => $factory->index($profiles, $pagination, $deleteForms, self::csrfForm('/admin/qr-code-profiles/import-yaml', 'import_qr_yaml')),
            'profiles'            => $profiles,
            'yaml_profiles'       => ['default', 'compact'],
            'use_database_config' => $useDatabaseConfig,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function form(bool $edit = false, bool $useDatabaseConfig = true, ?QrCodePageFactory $factory = null): array
    {
        $profile = $edit
            ? self::profile(2, 'wallet', 256, 4, 'medium', ['example.com'])
            : (new QrCodeProfileConfig())->setName('custom')->setSize(300)->setMargin(10)->setErrorCorrection('high')->setUrlAllowlist([]);
        $form = self::formFactory()->create(QrCodeProfileConfigType::class, $profile)->createView();
        $factory ??= self::factory();

        return $factory->context([
            'page'                => $factory->form($form, $profile, !$edit, $edit ? self::csrfForm('/admin/qr-code-profiles/2/delete', 'delete_qr_profile_2') : null),
            'profile'             => $profile,
            'is_new'              => !$edit,
            'use_database_config' => $useDatabaseConfig,
        ]);
    }

    public static function factory(?QrCodePages $pages = null): QrCodePageFactory
    {
        return new QrCodePageFactory(self::urlGenerator(), $pages ?? new QrCodePages());
    }

    /**
     * @param list<string> $allowlist
     */
    public static function profile(int $id, string $name, int $size, int $margin, string $errorCorrection, array $allowlist): QrCodeProfileConfig
    {
        $profile = (new QrCodeProfileConfig())
            ->setName($name)
            ->setSize($size)
            ->setMargin($margin)
            ->setErrorCorrection($errorCorrection)
            ->setUrlAllowlist($allowlist);
        (new ReflectionProperty(QrCodeProfileConfig::class, 'id'))->setValue($profile, $id);

        return $profile;
    }

    /**
     * Unnamed POST form carrying only the `_token` field, as built by the controller.
     */
    private static function csrfForm(string $action, string $tokenId): FormView
    {
        return self::formFactory()->createNamed('', FormType::class, null, [
            'action'          => $action,
            'method'          => 'POST',
            'csrf_field_name' => '_token',
            'csrf_token_id'   => $tokenId,
        ])->createView();
    }

    private static function formFactory(): FormFactoryInterface
    {
        $type = new QrCodeProfileConfigType();
        $type->setFormOptionsMerger(new FormOptionsMerger(
            ['qr_code' => ['translation_domain' => 'NowoQrCodeBundle', 'defaults' => ['attr' => ['class' => 'nowo-ui-input'], 'row_attr' => []], 'field_types' => []]],
            'qr_code',
            new ConstraintDefinitionFactory(),
        ));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new CsrfExtension(new class implements CsrfTokenManagerInterface {
                public function getToken(string $tokenId): CsrfToken
                {
                    return new CsrfToken($tokenId, 'token-' . $tokenId);
                }

                public function refreshToken(string $tokenId): CsrfToken
                {
                    return $this->getToken($tokenId);
                }

                public function removeToken(string $tokenId): ?string
                {
                    return null;
                }

                public function isTokenValid(CsrfToken $token): bool
                {
                    return true;
                }
            }))
            ->addType($type)
            ->getFormFactory();
    }

    private static function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/' . $name . ($parameters === [] ? '' : '?' . http_build_query($parameters));
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };
    }
}
