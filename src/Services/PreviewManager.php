<?php

declare(strict_types=1);

/**
 * PreviewManager.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Interfaces\PreviewContextInterface;
use Blackcube\Dcore\Interfaces\PreviewManagerInterface;
use Blackcube\Dcore\Models\Parameter;

/**
 * Preview manager — verifies HMAC-signed preview state from session.
 * 100% in blams, no HTTP/session/framework dependency.
 */
final class PreviewManager implements PreviewManagerInterface
{
    public const SESSION_KEY = '_preview';
    public const PARAMETER_DOMAIN = 'PREVIEW';
    public const PARAMETER_NAME = 'KEY';

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

        $jwt = $this->context->getJwt();
        $data = $this->context->getData();
        $userId = $jwt !== null ? $this->extractUserId($jwt) : null;

        $canVerify = $userId !== null
            && $data !== null
            && ($data['active'] ?? false)
            && isset($data['signature']);

        if (!$canVerify) {
            return;
        }

        $secret = $this->getSecret();
        $simulateDate = $data['simulateDate'] ?? null;
        $expected = $secret !== null
            ? hash_hmac('sha256', $userId . '1' . ($simulateDate ?? ''), $secret)
            : null;

        if ($expected !== null && hash_equals($expected, $data['signature'])) {
            $this->active = true;
            $this->simulateDate = $simulateDate;
        }
    }

    /**
     * Extract userId (sub claim) from JWT payload without crypto verification.
     * The JWT was already validated by the auth middleware.
     */
    private function extractUserId(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload) || !isset($payload['sub'])) {
            return null;
        }

        return (string) $payload['sub'];
    }

    /**
     * Read HMAC secret from parameters table.
     */
    private function getSecret(): ?string
    {
        $parameter = Parameter::query()
            ->andWhere(['domain' => self::PARAMETER_DOMAIN, 'name' => self::PARAMETER_NAME])
            ->one();

        return $parameter?->getValue();
    }
}
