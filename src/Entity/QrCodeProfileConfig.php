<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;

/**
 * Database-backed QR profile (overrides YAML when use_database_config is true and names match).
 */
#[ORM\Entity(repositoryClass: QrCodeProfileConfigRepository::class)]
#[ORM\Table(name: 'qr_code_profile')]
#[ORM\UniqueConstraint(name: 'uniq_qr_code_profile_name', columns: ['name'])]
class QrCodeProfileConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /* @phpstan-ignore property.unusedType (Doctrine assigns id via reflection) */
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $name = 'default';

    #[ORM\Column(type: Types::INTEGER)]
    private int $size = 300;

    #[ORM\Column(type: Types::INTEGER)]
    private int $margin = 10;

    #[ORM\Column(length: 16)]
    private string $errorCorrection = QrErrorCorrection::High->value;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $urlAllowlist = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function setMargin(int $margin): self
    {
        $this->margin = $margin;

        return $this;
    }

    public function getErrorCorrection(): string
    {
        return $this->errorCorrection;
    }

    public function setErrorCorrection(string $errorCorrection): self
    {
        $this->errorCorrection = $errorCorrection;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getUrlAllowlist(): array
    {
        return $this->urlAllowlist;
    }

    /**
     * @param list<string> $urlAllowlist
     */
    public function setUrlAllowlist(array $urlAllowlist): self
    {
        $this->urlAllowlist = array_values($urlAllowlist);

        return $this;
    }

    /**
     * @return array{
     *     size: int,
     *     margin: int,
     *     error_correction: string,
     *     url_allowlist: list<string>
     * }
     */
    public function toProfileArray(): array
    {
        return [
            'size'             => $this->size,
            'margin'           => $this->margin,
            'error_correction' => $this->errorCorrection,
            'url_allowlist'    => $this->urlAllowlist,
        ];
    }
}
