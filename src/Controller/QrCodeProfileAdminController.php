<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Form\QrCodeProfileConfigType;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Security\QrCodeAccessCheckerInterface;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use Nowo\QrCodeBundle\Ui\QrCodePageFactory;
use Nowo\QrCodeBundle\Ui\QrCodePages;
use Nowo\UiKitBundle\View\Pagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function ceil;
use function max;
use function min;
use function strtr;

/**
 * CRUD admin for database-backed QR profiles.
 */
#[Route(path: '/admin/qr-code-profiles', name: 'nowo_qr_code_admin_')]
final class QrCodeProfileAdminController extends AbstractController
{
    public function __construct(
        private readonly QrCodeProfileConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly QrCodeProfileAdminService $adminService,
        private readonly QrCodeAccessCheckerInterface $accessChecker,
        private readonly bool $useDatabaseConfig,
        private readonly int $listPageSize = 50,
        private readonly ?FormFactoryInterface $formFactory = null,
        private readonly ?QrCodePageFactory $pageFactory = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[Route(path: '', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyUnlessGranted();

        // REQ-PERF-001: paginated list (web_ui.list_page_size rows per page).
        $total      = $this->repository->count([]);
        $totalPages = max(1, (int) ceil($total / $this->listPageSize));
        $page       = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $profiles   = $this->repository->findPageOrderedByName($this->listPageSize, ($page - 1) * $this->listPageSize);

        $deleteForms = [];
        foreach ($profiles as $profile) {
            $deleteForms[(int) $profile->getId()] = $this->deleteForm($profile);
        }

        $pages = $this->pages();

        return $this->render($pages->template('index'), $pages->context([
            'page' => $pages->index(
                $profiles,
                Pagination::fromTotal($total, $page, $this->listPageSize, [$this->listPageSize], $this->listPageSize, 'nowo_qr_code_admin_index'),
                $deleteForms,
                $this->actionForm($this->generateUrl('nowo_qr_code_admin_import_yaml'), 'import_qr_yaml'),
            ),
            'profiles'            => $profiles,
            'yaml_profiles'       => $this->adminService->yamlProfileNames(),
            'use_database_config' => $this->useDatabaseConfig,
        ]));
    }

    #[Route(path: '/import-yaml', name: 'import_yaml', methods: ['POST'])]
    public function importYaml(Request $request): RedirectResponse
    {
        $this->denyUnlessGranted();

        if (!$this->isCsrfTokenValid('import_qr_yaml', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('nowo_qr_code.admin.flash.invalid_csrf'));

            return $this->redirectToRoute('nowo_qr_code_admin_index');
        }

        $touched = $this->adminService->importFromYaml();
        $this->addFlash(
            'success',
            $touched > 0
                ? $this->trans('nowo_qr_code.admin.flash.imported', ['%count%' => $touched])
                : $this->trans('nowo_qr_code.admin.flash.import_none'),
        );

        return $this->redirectToRoute('nowo_qr_code_admin_index');
    }

    #[Route(path: '/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyUnlessGranted();

        $profile = (new QrCodeProfileConfig())
            ->setName('custom')
            ->setSize(300)
            ->setMargin(10)
            ->setErrorCorrection(QrErrorCorrection::High->value)
            ->setUrlAllowlist([]);

        return $this->handleForm($request, $profile, true);
    }

    #[Route(path: '/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, QrCodeProfileConfig $profile): Response
    {
        $this->denyUnlessGranted();

        return $this->handleForm($request, $profile, false);
    }

    #[Route(path: '/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, QrCodeProfileConfig $profile): RedirectResponse
    {
        $this->denyUnlessGranted();

        if (!$this->isCsrfTokenValid('delete_qr_profile_' . $profile->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('nowo_qr_code.admin.flash.invalid_csrf'));

            return $this->redirectToRoute('nowo_qr_code_admin_index');
        }

        $this->entityManager->remove($profile);
        $this->entityManager->flush();
        $this->addFlash('success', $this->trans('nowo_qr_code.admin.flash.deleted', ['%name%' => $profile->getName()]));

        return $this->redirectToRoute('nowo_qr_code_admin_index');
    }

    private function handleForm(Request $request, QrCodeProfileConfig $profile, bool $isNew): Response
    {
        $form = $this->createForm(QrCodeProfileConfigType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
            $this->addFlash('success', $this->trans($isNew ? 'nowo_qr_code.admin.flash.created' : 'nowo_qr_code.admin.flash.updated'));

            return $this->redirectToRoute('nowo_qr_code_admin_index');
        }

        $pages = $this->pages();

        return $this->render($pages->template('form'), $pages->context([
            'page'                => $pages->form($form->createView(), $profile, $isNew, $isNew ? null : $this->deleteForm($profile)),
            'profile'             => $profile,
            'is_new'              => $isNew,
            'use_database_config' => $this->useDatabaseConfig,
        ]), new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function deleteForm(QrCodeProfileConfig $profile): FormView
    {
        return $this->actionForm(
            $this->generateUrl('nowo_qr_code_admin_delete', ['id' => $profile->getId()]),
            'delete_qr_profile_' . $profile->getId(),
        );
    }

    private function pages(): QrCodePageFactory
    {
        if ($this->pageFactory instanceof QrCodePageFactory) {
            return $this->pageFactory;
        }

        /** @var UrlGeneratorInterface $router */
        $router = $this->container->get('router');

        return new QrCodePageFactory($router);
    }

    /**
     * Admin copy of the bundle catalogue (`NowoQrCodeBundle`); the key itself without a translator.
     *
     * @param array<string, int|string> $params
     */
    private function trans(string $key, array $params = []): string
    {
        return $this->translator instanceof TranslatorInterface
            ? $this->translator->trans($key, $params, QrCodePages::DOMAIN)
            : strtr($key, $params);
    }

    /**
     * Empty POST form carrying only the `_token` CSRF field (REQ-TWIG-005: no raw form markup in the views).
     */
    private function actionForm(string $action, string $tokenId): FormView
    {
        if (!$this->formFactory instanceof FormFactoryInterface) {
            throw new LogicException('QrCodeProfileAdminController needs the form factory (symfony/form).');
        }

        return $this->formFactory->createNamed('', FormType::class, null, [
            'action'          => $action,
            'method'          => 'POST',
            'csrf_field_name' => '_token',
            'csrf_token_id'   => $tokenId,
        ])->createView();
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Access denied to QR Code profile admin.');
        }
    }
}
