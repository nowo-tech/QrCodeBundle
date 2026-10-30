<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Ui;

use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the UiKit view models of the QR profile admin pages (REQ-UI-003): page headers (title, intro, actions, back
 * link), columns, row actions (POST + CSRF-only FormViews behind a Confirm for deletes), the YAML import action,
 * pagination and the empty state. The controller and the page contract test share it, so the test renders exactly the
 * view models the application gets. Stateless: safe in worker mode.
 */
final readonly class QrCodePageFactory
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private QrCodePages $pages = new QrCodePages(),
    ) {
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        return $this->pages->template($page);
    }

    /**
     * Page variables plus the shared ones (legacy layout bridge).
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $this->pages->context($vars);
    }

    /**
     * Profiles list: New profile + Import from YAML (POST + CSRF-only FormView) in the header, Edit / Delete per row.
     *
     * @param list<QrCodeProfileConfig> $profiles
     * @param array<int, FormView> $deleteForms profile id => CSRF-only delete form
     */
    public function index(array $profiles, Pagination $pagination, array $deleteForms, FormView $importForm): ListPage
    {
        $newAction    = Action::create($this->url('nowo_qr_code_admin_new'), QrCodePages::label('nowo_qr_code.admin.index.new'));
        $importAction = new Action(
            'import',
            QrCodePages::label('nowo_qr_code.admin.index.import'),
            $this->url('nowo_qr_code_admin_import_yaml'),
            method: 'POST',
            icon: 'upload',
            variant: 'secondary',
            attributes: ['data-testid' => 'qr-code-import-yaml'],
            form: $importForm,
        );

        $actions = [];
        foreach ($profiles as $profile) {
            $id           = (int) $profile->getId();
            $actions[$id] = [Action::edit($this->url('nowo_qr_code_admin_edit', ['id' => $id]), QrCodePages::label('nowo_qr_code.admin.actions.edit'))];
            if (isset($deleteForms[$id])) {
                $actions[$id][] = $this->deleteAction($deleteForms[$id], $profile);
            }
        }

        return new ListPage(
            header: new PageHeader(
                QrCodePages::label('nowo_qr_code.admin.index.title'),
                QrCodePages::label('nowo_qr_code.admin.index.intro'),
                [$newAction, $importAction],
            ),
            columns: [
                new Column('name', QrCodePages::label('nowo_qr_code.admin.columns.name'), required: true),
                new Column('size', QrCodePages::label('nowo_qr_code.admin.columns.size'), align: 'end'),
                new Column('margin', QrCodePages::label('nowo_qr_code.admin.columns.margin'), align: 'end', priority: 2),
                new Column('errorCorrection', QrCodePages::label('nowo_qr_code.admin.columns.error_correction'), priority: 2),
                new Column('urlAllowlist', QrCodePages::label('nowo_qr_code.admin.columns.url_allowlist'), priority: 3),
            ],
            rows: $profiles,
            rowActions: static fn (QrCodeProfileConfig $row): array => $actions[(int) $row->getId()] ?? [],
            pagination: $pagination,
            empty: new EmptyState(null, QrCodePages::label('nowo_qr_code.admin.index.empty'), $newAction),
            bundle: QrCodePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'qr-code-profiles'],
            actionsLabel: QrCodePages::label('nowo_qr_code.admin.columns.actions'),
        );
    }

    /**
     * Profile create / edit (the delete zone on edit).
     */
    public function form(FormView $form, QrCodeProfileConfig $profile, bool $isNew, ?FormView $deleteForm = null): FormPage
    {
        $indexUrl = $this->url('nowo_qr_code_admin_index');

        return new FormPage(
            header: new PageHeader(
                QrCodePages::label($isNew ? 'nowo_qr_code.admin.form.new_title' : 'nowo_qr_code.admin.form.edit_title'),
                $isNew ? QrCodePages::label('nowo_qr_code.admin.form.new_intro') : $profile->getName(),
                back: new BackLink($indexUrl, QrCodePages::label('nowo_qr_code.admin.back'), 'qr-code-back'),
            ),
            form: $form,
            submitLabel: QrCodePages::label('nowo_qr_code.admin.form.save'),
            cancelHref: $indexUrl,
            cancelLabel: QrCodePages::label('nowo_qr_code.admin.form.cancel'),
            deleteAction: $deleteForm instanceof FormView ? $this->deleteAction($deleteForm, $profile) : null,
            bundle: QrCodePages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'qr-code-profile-form'],
        );
    }

    /**
     * POST delete behind the UiKit confirm dialog; the CSRF-only FormView carries the `_token` field.
     */
    private function deleteAction(FormView $form, QrCodeProfileConfig $profile): Action
    {
        return Action::delete(
            $this->url('nowo_qr_code_admin_delete', ['id' => (int) $profile->getId()]),
            confirm: new Confirm(
                QrCodePages::label('nowo_qr_code.admin.confirm.delete_title'),
                QrCodePages::label('nowo_qr_code.admin.confirm.delete_message', ['%name%' => $profile->getName()]),
                QrCodePages::label('nowo_qr_code.admin.confirm.delete_confirm'),
                cancelLabel: QrCodePages::label('nowo_qr_code.admin.confirm.cancel'),
            ),
            label: QrCodePages::label('nowo_qr_code.admin.actions.delete'),
            form: $form,
        );
    }

    /**
     * @param array<string, int|string> $params
     */
    private function url(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($route, $params);
    }
}
