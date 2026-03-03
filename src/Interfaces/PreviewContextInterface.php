<?php

declare(strict_types=1);

/**
 * PreviewContextInterface.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

/**
 * Contract for providing raw preview data from the HTTP context.
 * blams consumes this interface — yii-ssr implements it.
 */
interface PreviewContextInterface
{
    /**
     * Get the raw JWT string from the HTTP context (cookie, header, etc.).
     *
     * @return string|null The raw JWT or null if not available.
     */
    public function getJwt(): ?string;

    /**
     * Get the raw preview session data.
     *
     * @return array{active: bool, simulateDate: string|null, signature: string}|null
     */
    public function getData(): ?array;
}
