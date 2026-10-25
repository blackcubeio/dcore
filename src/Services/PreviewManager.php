<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Interfaces\PreviewContextInterface;
use Blackcube\Dcore\Interfaces\PreviewManagerInterface;

/**
 * Preview manager — reads preview state from session.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class PreviewManager implements PreviewManagerInterface
{
    public const SESSION_KEY = '_preview';

    private ?bool $active = null;
    private ?string $simulateDate = null;

    public function __construct(
        private readonly PreviewContextInterface $context,
    ) {}

    public function isActive(): bool
    {
        $this->loadState();

        return $this->active;
    }

    public function getSimulateDate(): ?string
    {
        $this->loadState();

        return $this->simulateDate;
    }

    /**
     * Load preview state once, then keep it in memory.
     */
    private function loadState(): void
    {
        if ($this->active === null) {
            $this->active = false;
            $this->simulateDate = null;

            $data = $this->context->getData();

            if ($data !== null && ($data['active'] ?? false) !== false) {
                $this->active = true;
                $this->simulateDate = $data['simulateDate'] ?? null;
            }
        }
    }
}
