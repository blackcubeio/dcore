<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Data;

use LogicException;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Data\Paginator\PageToken;
use Yiisoft\Data\Paginator\PaginatorInterface;
use Yiisoft\Data\Reader\FilterInterface;
use Yiisoft\Data\Reader\Sort;

/**
 * yiisoft/data paginator over an ActiveQuery, by page number.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ActiveQueryPaginator implements PaginatorInterface
{
    private int $currentPage = 1;
    private int $pageSize = self::DEFAULT_PAGE_SIZE;
    private ?int $totalCount = null;
    private ?PageToken $token = null;

    public function __construct(
        private ActiveQuery $query
    ) {}

    public function withToken(?PageToken $token): static
    {
        $new = clone $this;
        $new->token = $token;
        if ($token !== null) {
            $new->currentPage = (int) $token->value;
        }
        return $new;
    }

    public function withPageSize(int $pageSize): static
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('Page size should be at least 1.');
        }

        $new = clone $this;
        $new->pageSize = $pageSize;
        return $new;
    }

    public function withCurrentPage(int $page): static
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Current page should be at least 1.');
        }

        $new = clone $this;
        $new->currentPage = $page;
        $new->token = PageToken::next((string) $page);
        return $new;
    }

    public function getToken(): ?PageToken
    {
        return $this->token;
    }

    public function getNextToken(): ?PageToken
    {
        $token = null;
        if ($this->isOnLastPage() === false) {
            $token = PageToken::next((string) ($this->currentPage + 1));
        }
        return $token;
    }

    public function getPreviousToken(): ?PageToken
    {
        $token = null;
        if ($this->isOnFirstPage() === false) {
            $token = PageToken::previous((string) ($this->currentPage - 1));
        }
        return $token;
    }

    public function nextPage(): ?static
    {
        $page = null;
        $token = $this->getNextToken();
        if ($token !== null) {
            $page = $this->withToken($token);
        }
        return $page;
    }

    public function previousPage(): ?static
    {
        $page = null;
        $token = $this->getPreviousToken();
        if ($token !== null) {
            $page = $this->withToken($token);
        }
        return $page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getCurrentPageSize(): int
    {
        $totalPages = $this->getTotalPages();

        $currentPageSize = 0;
        if ($this->currentPage <= $totalPages) {
            if ($totalPages === 1) {
                $currentPageSize = $this->getTotalItems();
            } elseif ($this->currentPage < $totalPages) {
                $currentPageSize = $this->pageSize;
            } else {
                $currentPageSize = $this->getTotalItems() - $this->getOffset();
            }
        }

        return $currentPageSize;
    }

    public function getOffset(): int
    {
        return $this->pageSize * ($this->currentPage - 1);
    }

    public function getTotalItems(): int
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->query->count();
        }
        return $this->totalCount;
    }

    public function getTotalPages(): int
    {
        return (int) max(1, ceil($this->getTotalItems() / $this->pageSize));
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function withSort(?Sort $sort): static
    {
        throw new LogicException('Sorting is not supported.');
    }

    public function getSort(): ?Sort
    {
        return null;
    }

    public function isFilterable(): bool
    {
        return false;
    }

    public function getFilter(): FilterInterface
    {
        throw new LogicException('Filtering is not supported.');
    }

    public function withFilter(FilterInterface $filter): static
    {
        throw new LogicException('Filtering is not supported.');
    }

    public function read(): iterable
    {
        if ($this->currentPage <= $this->getTotalPages()) {
            yield from $this->query
                ->limit($this->pageSize)
                ->offset($this->getOffset())
                ->each();
        }
    }

    public function readOne(): array|object|null
    {
        $item = null;
        if ($this->currentPage <= $this->getTotalPages()) {
            $item = $this->query
                ->limit(1)
                ->offset($this->getOffset())
                ->one();
        }
        return $item;
    }

    public function isOnFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    public function isOnLastPage(): bool
    {
        return $this->currentPage >= $this->getTotalPages();
    }

    public function isPaginationRequired(): bool
    {
        return $this->getTotalPages() > 1;
    }
}
