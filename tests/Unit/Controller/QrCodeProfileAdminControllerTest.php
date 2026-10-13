<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\QrCodeBundle\Controller\QrCodeProfileAdminController;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Security\QrCodeAccessCheckerInterface;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    private function createController(bool $csrfValid): QrCodeProfileAdminController
    {
        $accessChecker = $this->createStub(QrCodeAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturn(true);

        $repository = $this->createStub(QrCodeProfileConfigRepository::class);

        $controller = new class($repository, $this->entityManager, new QrCodeProfileAdminService($repository, $this->entityManager, [], 'default'), $accessChecker, true, $csrfValid) extends QrCodeProfileAdminController {
            public function __construct(
                QrCodeProfileConfigRepository $repository,
                EntityManagerInterface $entityManager,
                QrCodeProfileAdminService $adminService,
                QrCodeAccessCheckerInterface $accessChecker,
                bool $useDatabaseConfig,
                private readonly bool $csrfValid,
            ) {
                parent::__construct($repository, $entityManager, $adminService, $accessChecker, $useDatabaseConfig);
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
        $router->method('generate')->willReturnMap([
            ['nowo_qr_code_admin_index', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/qr-code-profiles'],
        ]);

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('router', $router);
        $controller->setContainer($container);

        return $controller;
    }
}
