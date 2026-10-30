<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\Contract;

use Nowo\QrCodeBundle\Tests\Support\PageFixtures;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

/**
 * REQ-UI-003 page contract: the admin pages extend UiKit scaffolds (list, form); no <html>, CDN, inline handler or
 * confirm(); framework classes only through theme slots; the stub theme is reflected; no raw or missing translation
 * keys (strict rule); the CSRF forms of the actions keep their hidden fields.
 */
final class PageContractTest extends PageContractTestCase
{
    use QrCodeContractEnvironment;

    public static function pageFixtures(): iterable
    {
        yield 'profiles' => new PageFixture('@NowoQrCodeBundle/admin/index.html.twig', PageFixtures::index());
        yield 'profiles (empty, database config off)' => new PageFixture('@NowoQrCodeBundle/admin/index.html.twig', PageFixtures::index(true, false));
        yield 'profile new' => new PageFixture('@NowoQrCodeBundle/admin/form.html.twig', PageFixtures::form());
        yield 'profile edit (database config off)' => new PageFixture('@NowoQrCodeBundle/admin/form.html.twig', PageFixtures::form(true, false));
    }
}
