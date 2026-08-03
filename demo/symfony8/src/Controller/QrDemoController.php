<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\QrCodeBundle\Service\QrCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QrDemoController extends AbstractController
{
    #[Route(path: '/', name: 'app_root', methods: ['GET'])]
    public function root(): RedirectResponse
    {
        return $this->redirectToRoute('app_demo_index');
    }

    #[Route(path: '/demo', name: 'app_demo_index', methods: ['GET'])]
    public function index(QrCodeService $qrCodeService): Response
    {
        $phpDataUri = $qrCodeService->createDataUri('Hello from QrCodeService');
        $phpUrlUri  = $qrCodeService->createDataUriForUrl('https://example.com/pass', 'compact');

        return $this->render('qr_demo/index.html.twig', [
            'php_data_uri' => $phpDataUri,
            'php_url_uri'  => $phpUrlUri,
            'sample_url'   => 'https://example.com/pass',
        ]);
    }
}
