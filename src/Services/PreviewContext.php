<?php

declare(strict_types=1);

/**
 * PreviewContext.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Interfaces\PreviewContextInterface;
use Yiisoft\Session\SessionInterface;

/**
 * HTTP pass-through for preview data.
 * Reads preview state from session.
 */
final class PreviewContext implements PreviewContextInterface
{
    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function getJwt(): ?string
    {
        return null;
    }

    public function getData(): ?array
    {
        $data = $this->session->get(PreviewManager::SESSION_KEY);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }
}
