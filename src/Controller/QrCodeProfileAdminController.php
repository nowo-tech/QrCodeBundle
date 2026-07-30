<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Form\QrCodeProfileConfigType;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Security\QrCodeAccessCheckerInterface;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function sprintf;

/**
 * CRUD admin for database-backed QR profiles.
 */
#[Route(path: '/admin/qr-code-profiles', name: 'nowo_qr_code_admin_')]
class QrCodeProfileAdminController extends AbstractController
{
    public function __construct(
        private readonly QrCodeProfileConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly QrCodeProfileAdminService $adminService,
        private readonly QrCodeAccessCheckerInterface $accessChecker,
        private readonly bool $useDatabaseConfig,
    ) {
    }

    #[Route(path: '', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessGranted();

        return $this->render('@NowoQrCodeBundle/admin/index.html.twig', [
            'profiles'            => $this->repository->findAllOrderedByName(),
            'yaml_profiles'       => $this->adminService->yamlProfileNames(),
            'use_database_config' => $this->useDatabaseConfig,
        ]);
    }

    #[Route(path: '/import-yaml', name: 'import_yaml', methods: ['POST'])]
    public function importYaml(Request $request): Response
    {
        $this->denyUnlessGranted();

        if (!$this->isCsrfTokenValid('import_qr_yaml', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('nowo_qr_code_admin_index');
        }

        $touched = $this->adminService->importFromYaml();
        $this->addFlash(
            'success',
            $touched > 0
                ? sprintf('Imported/updated %d profile(s) from YAML.', $touched)
                : 'No YAML profiles found to import.',
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
    public function delete(Request $request, QrCodeProfileConfig $profile): Response
    {
        $this->denyUnlessGranted();

        if ($this->isCsrfTokenValid('delete_qr_profile_' . $profile->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($profile);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Profile "%s" deleted.', $profile->getName()));
        }

        return $this->redirectToRoute('nowo_qr_code_admin_index');
    }

    private function handleForm(Request $request, QrCodeProfileConfig $profile, bool $isNew): Response
    {
        $form = $this->createForm(QrCodeProfileConfigType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
            $this->addFlash('success', $isNew ? 'Profile created.' : 'Profile updated.');

            return $this->redirectToRoute('nowo_qr_code_admin_index');
        }

        return $this->render('@NowoQrCodeBundle/admin/form.html.twig', [
            'form'                => $form,
            'profile'             => $profile,
            'is_new'              => $isNew,
            'use_database_config' => $this->useDatabaseConfig,
        ]);
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Access denied to QR Code profile admin.');
        }
    }
}
