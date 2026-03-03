<?php

declare(strict_types=1);

/**
 * ElementCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Unit;

use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Tests\Support\UnitTester;

/**
 * Unit tests for Element (route parsing only, no DB).
 */
final class ElementCest
{
    public function createFromRouteParsesContent(UnitTester $I): void
    {
        $I->wantTo('verify createFromRoute() parses content route');

        $result = Element::createFromRoute('dcore-c-12');

        $I->assertNotNull($result);
        $I->assertInstanceOf(Element::class, $result);
        $I->assertEquals('content', $result->getType());
        $I->assertEquals(12, $result->getId());
        $I->assertEquals('dcore-c-12', $result->toRoute());
    }

    public function createFromRouteParsesTag(UnitTester $I): void
    {
        $I->wantTo('verify createFromRoute() parses tag route');

        $result = Element::createFromRoute('dcore-t-5');

        $I->assertNotNull($result);
        $I->assertInstanceOf(Element::class, $result);
        $I->assertEquals('tag', $result->getType());
        $I->assertEquals(5, $result->getId());
        $I->assertEquals('dcore-t-5', $result->toRoute());
    }

    public function createFromRouteReturnsNullForInvalid(UnitTester $I): void
    {
        $I->wantTo('verify createFromRoute() returns null for invalid routes');

        $I->assertNull(Element::createFromRoute('invalid'));
        $I->assertNull(Element::createFromRoute('dcore-x-12'));
        $I->assertNull(Element::createFromRoute('dcore-c-'));
        $I->assertNull(Element::createFromRoute('dcore-c-abc'));
        $I->assertNull(Element::createFromRoute('c-12'));
        $I->assertNull(Element::createFromRoute(''));
    }

    public function createFromRouteHandlesLargeIds(UnitTester $I): void
    {
        $I->wantTo('verify createFromRoute() handles large IDs');

        $result = Element::createFromRoute('dcore-c-999999999');

        $I->assertNotNull($result);
        $I->assertEquals('content', $result->getType());
        $I->assertEquals(999999999, $result->getId());
    }

    public function createFromRouteHandlesIdOne(UnitTester $I): void
    {
        $I->wantTo('verify createFromRoute() handles ID 1');

        $result = Element::createFromRoute('dcore-t-1');

        $I->assertNotNull($result);
        $I->assertEquals('tag', $result->getType());
        $I->assertEquals(1, $result->getId());
    }

    public function toRouteRoundTrip(UnitTester $I): void
    {
        $I->wantTo('verify toRoute() round-trips with createFromRoute()');

        $element = Element::createFromRoute('dcore-c-42');

        $I->assertNotNull($element);
        $I->assertEquals('dcore-c-42', $element->toRoute());

        $reloaded = Element::createFromRoute($element->toRoute());
        $I->assertEquals($element->getType(), $reloaded->getType());
        $I->assertEquals($element->getId(), $reloaded->getId());
    }
}
