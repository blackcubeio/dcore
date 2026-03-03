<?php

declare(strict_types=1);

/**
 * ConfigHandler.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\Handlers;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handler configured via config (no attributes) for testing override.
 */
final class ConfigHandler
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly StreamFactory $streamFactory,
    ) {}

    public function configOnly(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->streamFactory->createStream('config-only handler');

        return $this->responseFactory
            ->createResponse(200)
            ->withBody($body);
    }

    public function overrideConfig(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->streamFactory->createStream('CONFIG VERSION');

        return $this->responseFactory
            ->createResponse(200)
            ->withBody($body);
    }
}
