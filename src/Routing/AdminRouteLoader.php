<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Routing;

use Nowo\QrCodeBundle\Controller\QrCodeProfileAdminController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\RouteCollection;

/**
 * Imports the profile admin routes only when `use_database_config` is true.
 *
 * The admin controller service exists only in database mode; importing its attribute routes otherwise would turn
 * every `/admin/qr-code-profiles` request into a 500 (controller not a service). Disabled = no routes = 404.
 */
final class AdminRouteLoader extends Loader
{
    public const TYPE = 'nowo_qr_code_admin';

    public function __construct(
        private readonly bool $useDatabaseConfig,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        if ($this->useDatabaseConfig) {
            $imported = $this->import(QrCodeProfileAdminController::class, 'attribute');
            if ($imported instanceof RouteCollection) {
                $routes->addCollection($imported);
            }
        }

        // Rebuild the router cache when the flag changes (debug mode resource check).
        $routes->addResource(new ContainerParametersResource(['nowo_qr_code.use_database_config' => $this->useDatabaseConfig]));

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}
