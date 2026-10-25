<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Closure;

/**
 * Describes how to handle a given path: handler class + mode + lazy data.
 * Built by HandlerDescriptorFactory.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class HandlerDescriptor
{
    private mixed $data = null;
    private bool $dataLoaded = false;

    public function __construct(
        private readonly string $class,
        private readonly string $mode,
        private readonly ?string $method = null,
        private readonly array $expects = [],
        private readonly ?Closure $dataLoader = null,
    ) {}

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getExpects(): array
    {
        return $this->expects;
    }

    /**
     * Lazy-loaded data for the handler: typed params ready for the Injector.
     */
    public function getData(): array
    {
        if ($this->dataLoaded === false) {
            $this->data = $this->dataLoader !== null ? ($this->dataLoader)() : [];
            $this->dataLoaded = true;
        }
        return $this->data;
    }
}
