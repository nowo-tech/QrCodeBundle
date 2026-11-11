<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\Ddd;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Service\QrCodeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;

use function gc_collect_cycles;
use function json_decode;
use function memory_get_usage;

use const JSON_THROW_ON_ERROR;

/**
 * 1.13 (architecture bar rule 8): a DDD host without DoctrineBundle reads its QR profiles from its own
 * `profile_provider`, per tenant and per request, in a kept-alive kernel (FrankenPHP worker mode, kernel reset off:
 * `http_kernel` handle / terminate, no services resetter) without one tenant's profile reaching another's request.
 */
final class HostProfileProviderKernelTest extends TestCase
{
    private ?HostProviderTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(HostProviderTestKernel::baseDir());
    }

    public function testBootsWithoutDoctrineOnTheHostProfiles(): void
    {
        $container = $this->boot();

        self::assertFalse($container->has('doctrine'));
        $resolver = $container->get(ProfileResolver::class);
        self::assertInstanceOf(ProfileResolver::class, $resolver);
        self::assertSame(['default'], $resolver->getProfileNames());
        self::assertFalse($resolver->usesDatabaseConfig());

        // Outside a request the provider knows no tenant: the YAML profile applies.
        $service = $container->get(QrCodeService::class);
        self::assertInstanceOf(QrCodeService::class, $service);
        self::assertStringStartsWith('data:image/png;base64,', $service->createDataUriForUrl('https://yaml.example/a'));
    }

    public function testKeptAliveKernelResolvesTheProfileOfEachRequest(): void
    {
        $this->boot();
        $widths = [];
        $before = 0;

        for ($i = 0; $i < 600; ++$i) {
            $tenant = $i % 2 === 0 ? 'acme' : 'globex';
            $other  = $tenant === 'acme' ? 'globex' : 'acme';

            [$status, $data] = $this->get($tenant, 'https://' . $tenant . '.example/page');
            self::assertSame(200, $status, $tenant . ' #' . $i);
            self::assertArrayHasKey('width', $data);
            $widths[$tenant] ??= $data['width'];
            self::assertSame($widths[$tenant], $data['width'], $tenant . ' #' . $i);

            // The other tenant's allowed host is refused: its profile never leaks into this request.
            [$status] = $this->get($tenant, 'https://' . $other . '.example/page');
            self::assertSame(422, $status, $tenant . ' #' . $i);

            if ($i === 99) {
                gc_collect_cycles();
                $before = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before, 'Memory must stay flat over 500 requests.');
        self::assertLessThan($widths['globex'], $widths['acme']);
    }

    private function boot(): ContainerInterface
    {
        $this->kernel = new HostProviderTestKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    /**
     * One request through `http_kernel` (no services resetter in between).
     *
     * @return array{int, array{width?: int, refused?: bool}}
     */
    private function get(string $tenant, string $url): array
    {
        self::assertNotNull($this->kernel);
        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);
        $request  = Request::create('/qr/' . $tenant, 'GET', ['url' => $url]);
        $response = $http->handle($request);
        $http->terminate($request, $response);
        /** @var array{width?: int, refused?: bool} $data */
        $data = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return [$response->getStatusCode(), $data];
    }
}
