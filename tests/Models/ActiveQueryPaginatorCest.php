<?php

declare(strict_types=1);

/**
 * ActiveQueryPaginatorCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Data\ActiveQueryPaginator;
use Blackcube\Dcore\Models\Parameter;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Yiisoft\Data\Paginator\PageToken;

/**
 * Tests for ActiveQueryPaginator with real database.
 */
final class ActiveQueryPaginatorCest
{
    use DatabaseCestTrait;

    private string $testId = '';

    private function createTestParameters(int $count): void
    {
        $this->testId = uniqid('pag_', true);
        for ($i = 1; $i <= $count; $i++) {
            $param = new Parameter();
            $param->setDomain($this->testId);
            $param->setName('param_' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $param->setValue('value_' . $i);
            $param->save();
        }
    }

    private function getTestQuery(): \Yiisoft\ActiveRecord\ActiveQuery
    {
        return Parameter::query()->andWhere(['domain' => $this->testId]);
    }

    public function testPaginatorWithEmptyQuery(ModelsTester $I): void
    {
        $I->wantTo('test paginator with empty result set');
        $this->testId = uniqid('pag_', true);

        $query = $this->getTestQuery();
        $paginator = new ActiveQueryPaginator($query);

        $I->assertEquals(0, $paginator->getTotalCount());
        $I->assertEquals(1, $paginator->getTotalPages());
        $I->assertEquals(0, $paginator->getCurrentPageSize());
        $I->assertTrue($paginator->isOnFirstPage());
        $I->assertTrue($paginator->isOnLastPage());
        $I->assertFalse($paginator->isPaginationRequired());
        $I->assertEmpty(iterator_to_array($paginator->read()));
    }

    public function testPaginatorWithSinglePage(ModelsTester $I): void
    {
        $I->wantTo('test paginator when all items fit on one page');

        $this->createTestParameters(5);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        $I->assertEquals(5, $paginator->getTotalCount());
        $I->assertEquals(1, $paginator->getTotalPages());
        $I->assertEquals(5, $paginator->getCurrentPageSize());
        $I->assertTrue($paginator->isOnFirstPage());
        $I->assertTrue($paginator->isOnLastPage());
        $I->assertFalse($paginator->isPaginationRequired());
        $I->assertNull($paginator->getNextToken());
        $I->assertNull($paginator->getPreviousToken());

        $items = iterator_to_array($paginator->read());
        $I->assertCount(5, $items);
    }

    public function testPaginatorWithMultiplePages(ModelsTester $I): void
    {
        $I->wantTo('test paginator with multiple pages');

        $this->createTestParameters(25);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        $I->assertEquals(25, $paginator->getTotalCount());
        $I->assertEquals(3, $paginator->getTotalPages());
        $I->assertTrue($paginator->isPaginationRequired());

        // Page 1
        $I->assertTrue($paginator->isOnFirstPage());
        $I->assertFalse($paginator->isOnLastPage());
        $I->assertEquals(10, $paginator->getCurrentPageSize());
        $I->assertNull($paginator->getPreviousToken());
        $I->assertNotNull($paginator->getNextToken());

        $items = iterator_to_array($paginator->read());
        $I->assertCount(10, $items);
    }

    public function testPaginatorNavigation(ModelsTester $I): void
    {
        $I->wantTo('test paginator navigation through pages');

        $this->createTestParameters(25);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        // Page 1
        $I->assertEquals(1, $paginator->getCurrentPage());
        $I->assertTrue($paginator->isOnFirstPage());

        // Go to page 2
        $page2 = $paginator->nextPage();
        $I->assertNotNull($page2);
        $I->assertEquals(2, $page2->getCurrentPage());
        $I->assertFalse($page2->isOnFirstPage());
        $I->assertFalse($page2->isOnLastPage());
        $I->assertEquals(10, $page2->getCurrentPageSize());

        $items = iterator_to_array($page2->read());
        $I->assertCount(10, $items);

        // Go to page 3
        $page3 = $page2->nextPage();
        $I->assertNotNull($page3);
        $I->assertEquals(3, $page3->getCurrentPage());
        $I->assertFalse($page3->isOnFirstPage());
        $I->assertTrue($page3->isOnLastPage());
        $I->assertEquals(5, $page3->getCurrentPageSize()); // 25 - 20 = 5

        $items = iterator_to_array($page3->read());
        $I->assertCount(5, $items);

        // No next page from last page
        $I->assertNull($page3->nextPage());

        // Go back to page 2
        $backToPage2 = $page3->previousPage();
        $I->assertNotNull($backToPage2);
        $I->assertEquals(2, $backToPage2->getCurrentPage());

        // Go back to page 1
        $backToPage1 = $backToPage2->previousPage();
        $I->assertNotNull($backToPage1);
        $I->assertEquals(1, $backToPage1->getCurrentPage());
        $I->assertTrue($backToPage1->isOnFirstPage());

        // No previous page from first page
        $I->assertNull($backToPage1->previousPage());
    }

    public function testWithCurrentPage(ModelsTester $I): void
    {
        $I->wantTo('test withCurrentPage method');

        $this->createTestParameters(30);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        // Jump directly to page 2
        $page2 = $paginator->withCurrentPage(2);
        $I->assertEquals(2, $page2->getCurrentPage());
        $I->assertEquals(10, $page2->getOffset());
        $I->assertFalse($page2->isOnFirstPage());
        $I->assertFalse($page2->isOnLastPage());

        // Jump to last page
        $page3 = $paginator->withCurrentPage(3);
        $I->assertEquals(3, $page3->getCurrentPage());
        $I->assertTrue($page3->isOnLastPage());
    }

    public function testWithToken(ModelsTester $I): void
    {
        $I->wantTo('test withToken method');

        $this->createTestParameters(20);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        // Get token for next page
        $nextToken = $paginator->getNextToken();
        $I->assertNotNull($nextToken);

        // Use token to navigate
        $page2 = $paginator->withToken($nextToken);
        $I->assertEquals(2, $page2->getCurrentPage());
        $I->assertNotNull($page2->getToken());

        // Null token should not change page
        $sameAsCurrent = $paginator->withToken(null);
        $I->assertEquals(1, $sameAsCurrent->getCurrentPage());
    }

    public function testWithPageSize(ModelsTester $I): void
    {
        $I->wantTo('test withPageSize method');

        $this->createTestParameters(15);

        $query = $this->getTestQuery();
        $paginator = new ActiveQueryPaginator($query);

        // Default page size
        $I->assertEquals(10, $paginator->getPageSize());

        // Change page size
        $paginator5 = $paginator->withPageSize(5);
        $I->assertEquals(5, $paginator5->getPageSize());
        $I->assertEquals(3, $paginator5->getTotalPages()); // 15 / 5 = 3

        $paginator20 = $paginator->withPageSize(20);
        $I->assertEquals(20, $paginator20->getPageSize());
        $I->assertEquals(1, $paginator20->getTotalPages()); // 15 fits in 20
    }

    public function testWithPageSizeInvalidThrowsException(ModelsTester $I): void
    {
        $I->wantTo('test withPageSize throws exception for invalid size');

        $query = Parameter::query();
        $paginator = new ActiveQueryPaginator($query);

        $I->expectThrowable(\InvalidArgumentException::class, function () use ($paginator) {
            $paginator->withPageSize(0);
        });

        $I->expectThrowable(\InvalidArgumentException::class, function () use ($paginator) {
            $paginator->withPageSize(-1);
        });
    }

    public function testWithCurrentPageInvalidThrowsException(ModelsTester $I): void
    {
        $I->wantTo('test withCurrentPage throws exception for invalid page');

        $query = Parameter::query();
        $paginator = new ActiveQueryPaginator($query);

        $I->expectThrowable(\InvalidArgumentException::class, function () use ($paginator) {
            $paginator->withCurrentPage(0);
        });

        $I->expectThrowable(\InvalidArgumentException::class, function () use ($paginator) {
            $paginator->withCurrentPage(-1);
        });
    }

    public function testGetOffset(ModelsTester $I): void
    {
        $I->wantTo('test getOffset calculation');

        $this->createTestParameters(50);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        $I->assertEquals(0, $paginator->getOffset()); // Page 1

        $page2 = $paginator->withCurrentPage(2);
        $I->assertEquals(10, $page2->getOffset());

        $page3 = $paginator->withCurrentPage(3);
        $I->assertEquals(20, $page3->getOffset());

        $page5 = $paginator->withCurrentPage(5);
        $I->assertEquals(40, $page5->getOffset());
    }

    public function testImmutability(ModelsTester $I): void
    {
        $I->wantTo('test that paginator methods return new instances');

        $this->createTestParameters(20);

        $query = $this->getTestQuery();
        $original = (new ActiveQueryPaginator($query))->withPageSize(10);

        $withNewPageSize = $original->withPageSize(5);
        $I->assertNotSame($original, $withNewPageSize);
        $I->assertEquals(10, $original->getPageSize());
        $I->assertEquals(5, $withNewPageSize->getPageSize());

        $withNewPage = $original->withCurrentPage(2);
        $I->assertNotSame($original, $withNewPage);
        $I->assertEquals(1, $original->getCurrentPage());
        $I->assertEquals(2, $withNewPage->getCurrentPage());
    }

    public function testSortableAndFilterable(ModelsTester $I): void
    {
        $I->wantTo('test isSortable and isFilterable return false');

        $query = Parameter::query();
        $paginator = new ActiveQueryPaginator($query);

        $I->assertFalse($paginator->isSortable());
        $I->assertFalse($paginator->isFilterable());
        $I->assertNull($paginator->getSort());
    }

    public function testWithSortThrowsException(ModelsTester $I): void
    {
        $I->wantTo('test withSort throws LogicException');

        $query = Parameter::query();
        $paginator = new ActiveQueryPaginator($query);

        $I->expectThrowable(\LogicException::class, function () use ($paginator) {
            $paginator->withSort(null);
        });
    }

    public function testGetFilterThrowsException(ModelsTester $I): void
    {
        $I->wantTo('test getFilter throws LogicException');

        $query = Parameter::query();
        $paginator = new ActiveQueryPaginator($query);

        $I->expectThrowable(\LogicException::class, function () use ($paginator) {
            $paginator->getFilter();
        });
    }

    public function testWithFilterThrowsException(ModelsTester $I): void
    {
        $I->wantTo('test withFilter throws LogicException');

        $query = Parameter::query();
        $paginator = new ActiveQueryPaginator($query);

        $I->expectThrowable(\LogicException::class, function () use ($paginator) {
            $paginator->withFilter(new class implements \Yiisoft\Data\Reader\FilterInterface {
                public static function getOperator(): string { return 'test'; }
                public function toArray(): array { return []; }
            });
        });
    }

    public function testReadBeyondLastPageReturnsEmpty(ModelsTester $I): void
    {
        $I->wantTo('test read beyond last page returns empty');

        $this->createTestParameters(5);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))
            ->withPageSize(10)
            ->withCurrentPage(10); // Way beyond actual pages

        $items = iterator_to_array($paginator->read());
        $I->assertEmpty($items);
    }

    public function testExactPageBoundary(ModelsTester $I): void
    {
        $I->wantTo('test pagination when total equals page size exactly');

        $this->createTestParameters(10);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        $I->assertEquals(10, $paginator->getTotalCount());
        $I->assertEquals(1, $paginator->getTotalPages());
        $I->assertEquals(10, $paginator->getCurrentPageSize());
        $I->assertTrue($paginator->isOnFirstPage());
        $I->assertTrue($paginator->isOnLastPage());
        $I->assertFalse($paginator->isPaginationRequired());
    }

    public function testExactMultipleOfPageSize(ModelsTester $I): void
    {
        $I->wantTo('test pagination when total is exact multiple of page size');

        $this->createTestParameters(20);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        $I->assertEquals(20, $paginator->getTotalCount());
        $I->assertEquals(2, $paginator->getTotalPages());

        // Last page should have full page size
        $page2 = $paginator->withCurrentPage(2);
        $I->assertEquals(10, $page2->getCurrentPageSize());
        $I->assertTrue($page2->isOnLastPage());
    }

    public function testReadOne(ModelsTester $I): void
    {
        $I->wantTo('test readOne returns first item of current page');

        $this->createTestParameters(15);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))->withPageSize(10);

        // Page 1 - first item
        $item = $paginator->readOne();
        $I->assertNotNull($item);
        $I->assertInstanceOf(Parameter::class, $item);

        // Page 2 - first item of page 2
        $page2 = $paginator->withCurrentPage(2);
        $item2 = $page2->readOne();
        $I->assertNotNull($item2);
        $I->assertInstanceOf(Parameter::class, $item2);
        $I->assertNotEquals($item->getName(), $item2->getName());
    }

    public function testReadOneEmptyReturnsNull(ModelsTester $I): void
    {
        $I->wantTo('test readOne returns null for empty result');
        $this->testId = uniqid('pag_', true);

        $query = $this->getTestQuery();
        $paginator = new ActiveQueryPaginator($query);

        $item = $paginator->readOne();
        $I->assertNull($item);
    }

    public function testReadOneBeyondLastPage(ModelsTester $I): void
    {
        $I->wantTo('test readOne beyond last page returns null');

        $this->createTestParameters(5);

        $query = $this->getTestQuery();
        $paginator = (new ActiveQueryPaginator($query))
            ->withPageSize(10)
            ->withCurrentPage(10);

        $item = $paginator->readOne();
        $I->assertNull($item);
    }
}
