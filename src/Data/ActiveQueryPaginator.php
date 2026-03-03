<?php

declare(strict_types=1);

namespace Blackcube\Dcore\Data;

use LogicException;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Data\Paginator\PageToken;
use Yiisoft\Data\Paginator\PaginatorInterface;
use Yiisoft\Data\Reader\FilterInterface;
use Yiisoft\Data\Reader\Sort;

final class ActiveQueryPaginator implements PaginatorInterface
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
        if ($this->isOnLastPage()) {
            return null;
        }
        return PageToken::next((string) ($this->currentPage + 1));
    }

    public function getPreviousToken(): ?PageToken
    {
        if ($this->isOnFirstPage()) {
            return null;
        }
        return PageToken::previous((string) ($this->currentPage - 1));
    }

    public function nextPage(): ?static
    {
        $token = $this->getNextToken();
        if ($token === null) {
            return null;
        }
        return $this->withToken($token);
    }

    public function previousPage(): ?static
    {
        $token = $this->getPreviousToken();
        if ($token === null) {
            return null;
        }
        return $this->withToken($token);
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

        if ($this->currentPage > $totalPages) {
            return 0;
        }

        if ($totalPages === 1) {
            return $this->getTotalCount();
        }

        if ($this->currentPage < $totalPages) {
            return $this->pageSize;
        }

        return $this->getTotalCount() - $this->getOffset();
    }

    public function getOffset(): int
    {
        return $this->pageSize * ($this->currentPage - 1);
    }

    public function getTotalCount(): int
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->query->count();
        }
        return $this->totalCount;
    }

    public function getTotalPages(): int
    {
        return (int) max(1, ceil($this->getTotalCount() / $this->pageSize));
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
        if ($this->currentPage > $this->getTotalPages()) {
            return [];
        }

        yield from $this->query
            ->limit($this->pageSize)
            ->offset($this->getOffset())
            ->each();
    }

    public function readOne(): array|object|null
    {
        if ($this->currentPage > $this->getTotalPages()) {
            return null;
        }

        return $this->query
            ->limit(1)
            ->offset($this->getOffset())
            ->one();
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
