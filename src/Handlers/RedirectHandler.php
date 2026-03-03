<?php

declare(strict_types=1);

/**
 * RedirectHandler.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Handlers;

use Blackcube\Dcore\Models\Slug;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler for slug redirects — receives Slug via constructor injection.
 */
final class RedirectHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Slug $slug,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $link = $this->slug->getLink();
        if ($link->isTemplated()) {
            $link = $link->withTemplate('host', $request->getUri()->getHost());
        }
        return $this->responseFactory
            ->createResponse($this->slug->getHttpCode())
            ->withHeader('Location', $link->getHref());
    }
}
