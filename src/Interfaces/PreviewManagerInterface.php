<?php

declare(strict_types=1);

/**
 * PreviewManagerInterface.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

/**
 * Read-only contract for preview state.
 * Consumed by blams scopes/queries to adapt publishable filtering.
 */
interface PreviewManagerInterface
{
    /**
     * Whether preview mode is active and authenticated.
     */
    public function isActive(): bool;

    /**
     * Simulated date for preview (Y-m-d or Y-m-d H:i:s), or null if no simulation.
     */
    public function getSimulateDate(): ?string;
}
