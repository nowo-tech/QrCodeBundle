<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\QrCodeBundle\Controller\QrCodeProfileAdminController;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Security\QrCodeAccessCheckerInterface;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use Nowo\QrCodeBundle\Ui\QrCodePageFactory;
use Nowo\QrCodeBundle\Ui\QrCodePages;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function array_map;
use function dirname;

final class QrCodeProfileAdminControllerTest extends TestCase
{
    private Session $session;

    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->session       = new Session(new MockArraySessionStorage());
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testDeleteWithInvalidCsrfTokenAddsErrorFlashAndKeepsProfile(): void
    {
        $this->entityManager->expects(self::never())->method('remove');
        $this->entityManager->expects(self::never())->method('flush');

        $response = $this->createController(false)->delete(
            new Request(request: ['_token' => 'bad']),
            (new QrCodeProfileConfig())->setName('custom'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/qr-code-profiles', $response->getTargetUrl());
        self::assertSame(['Invalid CSRF token.'], $this->session->getFlashBag()->get('error'));
        self::assertSame([], $this->session->getFlashBag()->get('success'));
    }

    public function testDeleteWithValidCsrfTokenRemovesProfile(): void
    {
        $profile = (new QrCodeProfileConfig())->setName('custom');

        $this->entityManager->expects(self::once())->method('remove')->with($profile);
        $this->entityManager->expects(self::once())->method('flush');

        $response = $this->createController(true)->delete(new Request(request: ['_token' => 'ok']), $profile);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['Profile "custom" deleted.'], $this->session->getFlashBag()->get('success'));
        self::assertSame([], $this->session->getFlashBag()->get('error'));
    }

    public function testIndexIsPaginatedAndRendersSymfonyActionForms(): void
    {
        $profiles = [];
        foreach ([1, 2] as $id) {
            $profile = (new QrCodeProfileConfig())->setName('p' . $id);
            (new ReflectionProperty(QrCodeProfileConfig::class, 'id'))->setValue($profile, $id);
            $profiles[] = $profile;
        }
        $repository = $this->createMock(QrCodeProfileConfigRepository::class);
        $repository->method('count')->willReturn(5);
        $repository->expects(self::once())->method('findPageOrderedByName')->with(2, 2)->willReturn($profiles);

        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(true);
        $formFactory = Forms::createFormFactoryBuilder()->addExtension(new CsrfExtension(new CsrfTokenManager()))->getFormFactory();
        $controller  = new QrCodeProfileAdminController($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, [], 'default'), $accessChecker, true, 2, $formFactory);

        $captured = [];
        $twig     = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $view, array $context) use (&$captured): string {
            $captured = $context;

            return 'ok';
        });
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/qr-code-profiles/x');
        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->index(new Request(['page' => '2']));

        self::assertSame('ok', $response->getContent());
        $page = $captured['page'];
        self::assertInstanceOf(ListPage::class, $page);
        self::assertInstanceOf(Pagination::class, $page->pagination);
        self::assertSame([2, 2, 5, 3], [$page->pagination->page, $page->pagination->perPage, $page->pagination->total, $page->pagination->totalPages]);
        self::assertSame($profiles, $captured['profiles']);
        self::assertSame(['create', 'import'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
        self::assertInstanceOf(FormView::class, $page->header->actions[1]->form);
        $rowActions = $page->rowActions($profiles[0]);
        self::assertSame(['edit', 'delete'], array_map(static fn (Action $a): string => $a->kind, $rowActions));
        self::assertInstanceOf(FormView::class, $rowActions[1]->form);
        self::assertSame('POST', $rowActions[1]->form->vars['method']);
        self::assertNull($captured['nowo_qr_code_legacy_layout']);
    }

    public function testIndexUsesTheInjectedPageFactoryAndTemplateOverride(): void
    {
        $repository = $this->createStub(QrCodeProfileConfigRepository::class);
        $repository->method('findPageOrderedByName')->willReturn([]);
        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(true);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/x');
        $factory     = new QrCodePageFactory($router, new QrCodePages(null, ['index' => 'host/qr_index.html.twig']));
        $formFactory = Forms::createFormFactoryBuilder()->addExtension(new CsrfExtension(new CsrfTokenManager()))->getFormFactory();
        $controller  = new QrCodeProfileAdminController($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, [], 'default'), $accessChecker, false, 50, $formFactory, $factory);

        $view = null;
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $template) use (&$view): string {
            $view = $template;

            return 'ok';
        });
        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $controller->setContainer($container);

        $controller->index(new Request());

        self::assertSame('host/qr_index.html.twig', $view);
    }

    public function testIndexWithoutFormFactoryFails(): void
    {
        $repository = $this->createStub(QrCodeProfileConfigRepository::class);
        $repository->method('findPageOrderedByName')->willReturn([]);
        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(true);
        $controller = new QrCodeProfileAdminController($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, [], 'default'), $accessChecker, true);
        $router     = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/x');
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $this->expectException(LogicException::class);
        $controller->index(new Request());
    }

    public function testDeniedUsersCannotReachTheAdmin(): void
    {
        $repository    = $this->createStub(QrCodeProfileConfigRepository::class);
        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(false);
        $controller = new QrCodeProfileAdminController($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, [], 'default'), $accessChecker, true);

        $this->expectException(AccessDeniedException::class);
        $controller->index(new Request());
    }

    public function testImportYamlRejectsInvalidCsrfToken(): void
    {
        $response = $this->createController(false)->importYaml(new Request(request: ['_token' => 'bad']));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['Invalid CSRF token.'], $this->session->getFlashBag()->get('error'));
    }

    public function testImportYamlReportsWhenNothingToImport(): void
    {
        $response = $this->createController(true)->importYaml(new Request(request: ['_token' => 'ok']));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['No YAML profiles found to import.'], $this->session->getFlashBag()->get('success'));
    }

    public function testImportYamlReportsTouchedProfiles(): void
    {
        $this->entityManager->expects(self::once())->method('flush');
        $controller = $this->createController(true, ['compact' => ['size' => 200, 'margin' => 4, 'error_correction' => 'low', 'url_allowlist' => []]]);

        $controller->importYaml(new Request(request: ['_token' => 'ok']));

        self::assertSame(['Imported or updated 1 profile(s) from YAML.'], $this->session->getFlashBag()->get('success'));
    }

    public function testNewRendersTheFormUntilSubmitted(): void
    {
        $controller = $this->createController(true, [], $this->formStub(false, false));

        self::assertSame('form-page', $controller->new(new Request())->getContent());
    }

    public function testEditPersistsAValidSubmission(): void
    {
        $profile = (new QrCodeProfileConfig())->setName('custom');
        $this->entityManager->expects(self::once())->method('persist')->with($profile);
        $this->entityManager->expects(self::once())->method('flush');
        $controller = $this->createController(true, [], $this->formStub(true, true));

        $response = $controller->edit(new Request(), $profile);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['Profile updated.'], $this->session->getFlashBag()->get('success'));
    }

    public function testNewPersistsAValidSubmission(): void
    {
        $this->entityManager->expects(self::once())->method('persist');
        $controller = $this->createController(true, [], $this->formStub(true, true));

        $controller->new(new Request());

        self::assertSame(['Profile created.'], $this->session->getFlashBag()->get('success'));
    }

    public function testFlashesFallBackToTheKeysWithoutTranslator(): void
    {
        $this->createController(false, translate: false)->importYaml(new Request(request: ['_token' => 'bad']));

        self::assertSame(['nowo_qr_code.admin.flash.invalid_csrf'], $this->session->getFlashBag()->get('error'));
    }

    public function testEditRendersTheFormPageWithTheDeleteZone(): void
    {
        $profile = (new QrCodeProfileConfig())->setName('custom');
        (new ReflectionProperty(QrCodeProfileConfig::class, 'id'))->setValue($profile, 7);
        $form = $this->formStub(true, false);

        $captured      = [];
        $repository    = $this->createStub(QrCodeProfileConfigRepository::class);
        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(true);
        $controller = new QrCodeProfileAdminController($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, [], 'default'), $accessChecker, true, 50, Forms::createFormFactoryBuilder()->addExtension(new CsrfExtension(new CsrfTokenManager()))->getFormFactory());
        $twig       = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $view, array $context) use (&$captured): string {
            $captured = $context;

            return 'form-page';
        });
        $container = new Container();
        $container->set('twig', $twig);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/qr-code-profiles/7/delete');
        $container->set('router', $router);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $container->set('form.factory', $formFactory);
        $controller->setContainer($container);

        $response = $controller->edit(new Request(), $profile);

        self::assertSame(422, $response->getStatusCode());
        $page = $captured['page'];
        self::assertInstanceOf(FormPage::class, $page);
        self::assertInstanceOf(Action::class, $page->deleteAction);
        self::assertSame('delete', $page->deleteAction->kind);
        self::assertFalse($captured['is_new']);
    }

    private function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/NowoQrCodeBundle.en.yaml', 'en', 'NowoQrCodeBundle');

        return $translator;
    }

    /**
     * @return FormInterface<mixed>
     */
    private function formStub(bool $submitted, bool $valid): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    /**
     * @param array<string, array{size: int, margin: int, error_correction: string, url_allowlist: list<string>}> $yamlProfiles
     * @param FormInterface<mixed>|null $form
     */
    private function createController(bool $csrfValid, array $yamlProfiles = [], ?FormInterface $form = null, bool $translate = true): QrCodeProfileAdminController
    {
        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(true);

        $repository = $this->createStub(QrCodeProfileConfigRepository::class);

        $controller = new class($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, $yamlProfiles, 'default'), $accessChecker, true, $csrfValid, $translate ? $this->translator() : null) extends QrCodeProfileAdminController {
            public function __construct(
                QrCodeProfileConfigRepository $repository,
                EntityManagerInterface $entityManager,
                QrCodeProfileAdminService $adminService,
                QrCodeAccessCheckerInterface $accessChecker,
                bool $useDatabaseConfig,
                private readonly bool $csrfValid,
                ?TranslatorInterface $translator,
            ) {
                parent::__construct($repository, $entityManager, $adminService, $accessChecker, $useDatabaseConfig, translator: $translator);
            }

            protected function isCsrfTokenValid(string $id, ?string $token): bool
            {
                return $this->csrfValid;
            }
        };

        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/qr-code-profiles');

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('router', $router);
        if ($form instanceof FormInterface) {
            $formFactory = $this->createStub(FormFactoryInterface::class);
            $formFactory->method('create')->willReturn($form);
            $container->set('form.factory', $formFactory);
            $twig = $this->createStub(Environment::class);
            $twig->method('render')->willReturn('form-page');
            $container->set('twig', $twig);
        }
        $controller->setContainer($container);

        return $controller;
    }
}
