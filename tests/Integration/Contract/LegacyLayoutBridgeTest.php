<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\Contract;

use Nowo\QrCodeBundle\Tests\Support\PageFixtures;
use Nowo\QrCodeBundle\Ui\QrCodePages;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

use function substr_count;

/**
 * Deprecated 1.x admin layouts (no UiKit canonical blocks; the 1.x pages filled `body`) keep working through the
 * bridge: the page renders inside the host `body` block, once, with title, assets and flashes mapped; canonical
 * blocks the 1.x layout renders itself (a copy of the removed bundle layout) are not printed twice. A layout with the
 * canonical blocks is used as is.
 */
#[CoversClass(QrCodePages::class)]
final class LegacyLayoutBridgeTest extends TestCase
{
    public const LAYOUTS = [
        // A host layout written for 1.x: title, stylesheets, body, javascripts
        'legacy/plain.html.twig' => '<header data-host="plain"><title>{% block title %}{% endblock %}</title>{% block stylesheets %}<link data-host-css>{% endblock %}</header>'
            . '<main data-host-main>{% block body %}{% endblock %}</main>{% block javascripts %}<script data-host-js></script>{% endblock %}',
        // A copy of the removed 1.x bundle layout: renders the flashes itself
        'legacy/bundle-copy.html.twig' => '<header data-host="copy"><title>{% block title %}{% endblock %}</title>{% block stylesheets %}{% endblock %}</header>'
            . '<div data-host-flashes>{% block nowo_ui_flashes %}{% endblock %}</div>'
            . '<div data-host-body>{% block body %}{% endblock %}</div>{% block javascripts %}{% endblock %}',
        // A host layout that already renders the UiKit canonical blocks
        'canonical.html.twig' => '<div data-host-canonical><title>{% block nowo_ui_document_title %}{% endblock %}</title>{% block nowo_ui_page_header %}{% endblock %}'
            . '{% block nowo_ui_flashes %}{% endblock %}{% block nowo_ui_content %}{% endblock %}{% block nowo_ui_modals %}{% endblock %}</div>',
    ];

    #[Test]
    #[IgnoreDeprecations]
    public function aPlainLegacyLayoutRendersThePageInItsBodyBlock(): void
    {
        $twig    = $this->twig();
        $pages   = new QrCodePages('legacy/plain.html.twig', [], $twig);
        $factory = PageFixtures::factory($pages);

        $this->expectUserDeprecationMessageMatches('/legacy\/plain\.html\.twig/');
        self::assertSame(QrCodePages::LEGACY_ADMIN_BRIDGE, $pages->layout());
        $context = PageFixtures::index(false, true, $factory);
        self::assertSame('legacy/plain.html.twig', $context['nowo_qr_code_legacy_layout']);
        self::assertSame([], $context['nowo_qr_code_legacy_blocks']);

        $html = $twig->render('@NowoQrCodeBundle/admin/index.html.twig', $context);

        self::assertSame(1, substr_count($html, 'data-host="plain"'));
        self::assertMatchesRegularExpression('/<main data-host-main>.*data-testid="qr-code-profiles"/s', $html);
        self::assertSame(1, substr_count($html, 'data-testid="qr-code-profiles"'));
        self::assertStringContainsString('<title>QR Code profiles</title>', $html);
        self::assertStringContainsString('data-host-css', $html);
        self::assertStringContainsString('nowo-ui.css', $html);
        self::assertStringContainsString('data-host-js', $html);
        self::assertStringContainsString('data-nowo-ui-contract-partial="page_header"', $html);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function aCopyOfTheRemovedBundleLayoutDoesNotPrintBlocksTwice(): void
    {
        $twig    = $this->twig();
        $pages   = new QrCodePages('legacy/bundle-copy.html.twig', [], $twig);
        $factory = PageFixtures::factory($pages);

        $this->expectUserDeprecationMessageMatches('/legacy\/bundle-copy\.html\.twig/');
        $context = PageFixtures::form(true, true, $factory);
        self::assertSame(['nowo_ui_flashes'], $context['nowo_qr_code_legacy_blocks']);

        $html = $twig->render('@NowoQrCodeBundle/admin/form.html.twig', $context);

        self::assertSame(1, substr_count($html, 'data-testid="qr-code-profile-form"'));
        self::assertMatchesRegularExpression('/<div data-host-flashes>.*data-nowo-ui-contract-partial="page_header"/s', $html, 'page header before the content');
        self::assertSame(1, substr_count($html, 'data-nowo-ui-contract-partial="page_header"'));
    }

    #[Test]
    public function aCanonicalLayoutIsUsedAsIs(): void
    {
        $twig  = $this->twig();
        $pages = new QrCodePages('canonical.html.twig', ['index' => 'host/profiles.html.twig'], $twig);

        self::assertSame('canonical.html.twig', $pages->layout());
        self::assertSame('host/profiles.html.twig', $pages->template('index'));
        self::assertSame(QrCodePages::TEMPLATES['form'], $pages->template('form'));
        self::assertSame(['nowo_qr_code_legacy_layout' => null, 'nowo_qr_code_legacy_blocks' => []], $pages->context([]));

        $html = $twig->render('@NowoQrCodeBundle/admin/index.html.twig', PageFixtures::index(false, true, PageFixtures::factory($pages)));
        self::assertStringContainsString('<div data-host-canonical><title>QR Code profiles</title>', $html);
    }

    #[Test]
    public function unknownLayoutsAndMissingTwigFallBackToTheConfiguredValue(): void
    {
        $missing = new QrCodePages('missing/layout.html.twig', [], $this->twig());
        self::assertSame('missing/layout.html.twig', $missing->layout());

        $noTwig = new QrCodePages('legacy/plain.html.twig');
        self::assertSame('legacy/plain.html.twig', $noTwig->layout());
        self::assertSame(['nowo_qr_code_legacy_layout' => null, 'nowo_qr_code_legacy_blocks' => []], $noTwig->context([]));

        $default = new QrCodePages('');
        self::assertNull($default->layout());
        self::assertSame('unknown_page', $default->template('unknown_page'));
        self::assertSame(['key' => 'k', 'domain' => QrCodePages::DOMAIN, 'params' => ['%n%' => 1]], (array) QrCodePages::label('k', ['%n%' => 1]));
    }

    private function twig(): Environment
    {
        $case = new class('legacy') extends PageContractTestCase {
            use QrCodeContractEnvironment;

            public static function pageFixtures(): iterable
            {
                return [];
            }

            public function environment(): Environment
            {
                return $this->createTwig();
            }

            protected static function inlineTemplates(): array
            {
                return LegacyLayoutBridgeTest::LAYOUTS;
            }
        };

        return $case->environment();
    }
}
