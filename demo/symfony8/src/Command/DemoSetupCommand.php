<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function is_dir;
use function mkdir;

/**
 * Demo only: creates the SQLite schema of the QR profile table (when missing) and seeds realistic profiles once, so
 * the admin (/admin/qr-code-profiles) and the database overrides have data. Idempotent; run by the entrypoint.
 */
#[AsCommand(name: 'app:demo:setup', description: 'Create the demo QR profile table and seed it (idempotent).')]
final class DemoSetupCommand extends Command
{
    /** name => [size, margin, error correction, URL allowlist]; default / compact override the YAML profiles. */
    private const PROFILES = [
        'default'     => [300, 10, 'high', []],
        'compact'     => [128, 2, 'medium', ['example.com']],
        'wallet-pass' => [256, 4, 'quartile', ['wallet.example.com', 'example.com/passes/']],
        'event-badge' => [200, 6, 'high', ['tickets.example.org']],
        'print-large' => [600, 16, 'high', []],
        'menu-table'  => [180, 4, 'low', ['menu.example.net']],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->entityManager->getConnection();
        $params     = $connection->getParams();
        if (isset($params['path']) && !is_dir(dirname($params['path']))) {
            mkdir(dirname($params['path']), 0o777, true);
        }

        $metadata = [$this->entityManager->getClassMetadata(QrCodeProfileConfig::class)];
        (new SchemaTool($this->entityManager))->updateSchema($metadata);

        $repository = $this->entityManager->getRepository(QrCodeProfileConfig::class);
        if ($repository->count([]) > 0) {
            $output->writeln('QR demo profiles already seeded.');

            return Command::SUCCESS;
        }

        foreach (self::PROFILES as $name => [$size, $margin, $errorCorrection, $allowlist]) {
            $this->entityManager->persist((new QrCodeProfileConfig())
                ->setName($name)
                ->setSize($size)
                ->setMargin($margin)
                ->setErrorCorrection($errorCorrection)
                ->setUrlAllowlist($allowlist));
        }
        $this->entityManager->flush();
        $output->writeln('Seeded ' . \count(self::PROFILES) . ' QR demo profiles.');

        return Command::SUCCESS;
    }
}
