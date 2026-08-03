<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Enum;

/**
 * Supported CSS stacks for the admin Web UI (REQ-UI-001).
 */
enum CssFramework: string
{
    case Bootstrap = 'bootstrap';

    case Bootstrap4 = 'bootstrap4';

    case Bootstrap5 = 'bootstrap5';

    case Tabler = 'tabler';

    case Tailwind = 'tailwind';

    case Foundation = 'foundation';

    case Custom = 'custom';

    case None = 'none';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Normalize config aliases ({@see Bootstrap} → {@see Bootstrap5}).
     */
    public function normalized(): self
    {
        return $this === self::Bootstrap ? self::Bootstrap5 : $this;
    }
}
