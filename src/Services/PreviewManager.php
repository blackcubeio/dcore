<?php

declare(strict_types=1);

/**
 * PreviewManager.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Interfaces\PreviewContextInterface;
use Blackcube\Dcore\Interfaces\PreviewManagerInterface;

/**
 * Preview manager — reads preview state from session.
 */
final class PreviewManager implements PreviewManagerInterface
{
    public const SESSION_KEY = '_preview';

    private ?bool $active = null;
    private ?string $simulateDate = null;

    public function __construct(
        private readonly PreviewContextInterface $context,
    ) {}

    public function isActive(): bool
    {
        $this->resolve();

        return $this->active;
    }

    public function getSimulateDate(): ?string
    {
        $this->resolve();

        return $this->simulateDate;
    }

    /**
     * Resolve preview state once, then cache in memory.
     */
    private function resolve(): void
    {
        if ($this->active !== null) {
            return;
        }

        $this->active = false;
        $this->simulateDate = null;

        $data = $this->context->getData();

        if ($data === null || !($data['active'] ?? false)) {
            return;
        }

        $this->active = true;
        $this->simulateDate = $data['simulateDate'] ?? null;
    }
}
