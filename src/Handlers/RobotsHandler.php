<?php

declare(strict_types=1);

/**
 * RobotsHandler.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Handlers;

use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\Host;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler for /robots.txt — autonomous, queries GlobalXeo directly.
 */
final class RobotsHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $host = $this->resolveHost($request->getUri()->getHost());

        if ($host === null) {
            return $this->responseFactory->createResponse(404);
        }

        $globalXeo = GlobalXeo::query()
            ->andWhere(['hostId' => $host->getId(), 'kind' => 'Robots'])
            ->active()
            ->one();

        if ($globalXeo === null) {
            return $this->responseFactory->createResponse(404);
        }

        $body = $this->streamFactory->createStream($globalXeo->rawData ?? '');

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($body);
    }

    private function resolveHost(string $hostname): ?Host
    {
        $host = Host::query()
            ->andWhere(['name' => $hostname])
            ->active()
            ->one();

        if ($host !== null) {
            return $host;
        }

        return Host::query()
            ->andWhere(['id' => 1])
            ->active()
            ->one();
    }
}
