<?php

declare(strict_types=1);

/**
 * EmptyDataReader.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Data;

use Closure;
use Yiisoft\Db\Query\DataReaderInterface;

/**
 * Empty data reader returned by ScopableQuery::each() when the query must emulate
 * its execution (no translation group, empty pivot on a via relation, ...).
 *
 * It honors the same contract as PdoDataReader but never touches the database:
 * iterating over it yields nothing and counting it returns zero.
 */
class EmptyDataReader implements DataReaderInterface
{
    public function rewind(): void
    {
    }

    public function next(): void
    {
    }

    public function valid(): bool
    {
        return false;
    }

    public function current(): array|object|false
    {
        return false;
    }

    public function key(): int|string|null
    {
        return null;
    }

    public function count(): int
    {
        return 0;
    }

    public function indexBy(Closure|string|null $indexBy): static
    {
        return $this;
    }

    public function resultCallback(?Closure $resultCallback): static
    {
        return $this;
    }

    public function typecastColumns(array $typecastColumns): static
    {
        return $this;
    }
}
