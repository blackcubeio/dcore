<?php

declare(strict_types=1);

/**
 * MenuCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Models\Menu;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class MenuCest
{
    use DatabaseCestTrait;

    private function createLanguage(): Language
    {
        $language = Language::query()->andWhere(['id' => 'fr'])->one();
        if ($language === null) {
            $language = new Language();
            $language->setId('fr');
            $language->setName('Français');
            $language->setActive(true);
            $language->setMain(true);
            $language->save();
        }
        return $language;
    }

    public function testInsertAsRoot(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $menu = new Menu();
        $menu->setName('Main Menu');
        $menu->setLanguageId($language->getId());
        $menu->save();

        $I->assertNotNull($menu->getId());
        $I->assertMatchesRegularExpression('/^\d+$/', $menu->path, 'Root path should be a single number');
        $I->assertEquals(1, $menu->level);
        $I->assertNotNull($menu->getDateCreate());
    }

    public function testMultipleRoots(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $menu1 = new Menu();
        $menu1->setName('Main Menu');
        $menu1->setLanguageId($language->getId());
        $menu1->save();

        $menu2 = new Menu();
        $menu2->setName('Footer Menu');
        $menu2->setLanguageId($language->getId());
        $menu2->save();

        $I->assertEquals(1, $menu1->level);
        $I->assertEquals(1, $menu2->level);
        $I->assertNotEquals($menu1->path, $menu2->path);
        $I->assertEquals((int)$menu1->path + 1, (int)$menu2->path);
    }

    public function testSaveInto(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main Menu');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $subItem = new Menu();
        $subItem->setName('Products');
        $subItem->setLanguageId($language->getId());
        $subItem->saveInto($mainMenu);

        $I->assertEquals($mainMenu->path.'.1', $subItem->path);
        $I->assertEquals(2, $subItem->level);
    }

    public function testMenuWithRoute(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $menu = new Menu();
        $menu->setName('Products');
        $menu->setLanguageId($language->getId());
        $menu->setRoute('/products');
        $menu->setQueryString('category=all');
        $menu->save();

        $found = Menu::query()->andWhere(['id' => $menu->getId()])->one();

        $I->assertEquals('/products', $found->getRoute());
        $I->assertEquals('category=all', $found->getQueryString());
    }

    public function testUpdate(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $menu = new Menu();
        $menu->setName('Original');
        $menu->setLanguageId($language->getId());
        $menu->save();

        $menu->setName('Updated');
        $menu->save();

        $I->assertEquals('Updated', $menu->getName());
        $I->assertNotNull($menu->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main Menu');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $subItem = new Menu();
        $subItem->setName('Products');
        $subItem->setLanguageId($language->getId());
        $subItem->saveInto($mainMenu);

        $subItemId = $subItem->getId();
        $subItem->delete();

        $found = Menu::query()->andWhere(['id' => $subItemId])->one();
        $I->assertNull($found);
    }

    public function testTreeNavigation(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main Menu');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $item1 = new Menu();
        $item1->setName('Home');
        $item1->setLanguageId($language->getId());
        $item1->saveInto($mainMenu);

        $item2 = new Menu();
        $item2->setName('Products');
        $item2->setLanguageId($language->getId());
        $item2->saveInto($mainMenu);

        $children = $mainMenu->relativeQuery()->children()->all();
        $I->assertCount(2, $children);

        $firstItem = $mainMenu->relativeQuery()->children()->one();
        $I->assertEquals('Home', $firstItem->getName());
    }

    public function testMenuHierarchy(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $products = new Menu();
        $products->setName('Products');
        $products->setLanguageId($language->getId());
        $products->saveInto($mainMenu);

        $phones = new Menu();
        $phones->setName('Phones');
        $phones->setLanguageId($language->getId());
        $phones->saveInto($products);

        $iphone = new Menu();
        $iphone->setName('iPhone');
        $iphone->setLanguageId($language->getId());
        $iphone->saveInto($phones);

        $I->assertEquals(1, $mainMenu->level);
        $I->assertEquals($mainMenu->path.'.1', $products->path);
        $I->assertEquals($products->path.'.1', $phones->path);
        $I->assertEquals($phones->path.'.1', $iphone->path);
        $I->assertEquals(4, $iphone->level);
    }


    public function testMultipleChildrenOrdering(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $itemB = new Menu();
        $itemB->setName('B');
        $itemB->setLanguageId($language->getId());
        $itemB->saveInto($mainMenu);

        $itemC = new Menu();
        $itemC->setName('C');
        $itemC->setLanguageId($language->getId());
        $itemC->saveInto($mainMenu);

        $children = $mainMenu->relativeQuery()->children()->all();
        $I->assertCount(3, $children);
        $I->assertEquals('A', $children[0]->getName());
        $I->assertEquals('B', $children[1]->getName());
        $I->assertEquals('C', $children[2]->getName());

        $I->assertEquals($mainMenu->path.'.1', $itemA->path);
        $I->assertEquals($mainMenu->path.'.2', $itemB->path);
        $I->assertEquals($mainMenu->path.'.3', $itemC->path);
    }

    public function testNavigationParent(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $item = new Menu();
        $item->setName('Item');
        $item->setLanguageId($language->getId());
        $item->saveInto($mainMenu);

        $parent = $item->relativeQuery()->parent()->one();
        $I->assertNotNull($parent);
        $I->assertEquals('Main', $parent->getName());
    }

    public function testNavigationAncestors(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $subMenu = new Menu();
        $subMenu->setName('SubMenu');
        $subMenu->setLanguageId($language->getId());
        $subMenu->saveInto($mainMenu);

        $item = new Menu();
        $item->setName('Item');
        $item->setLanguageId($language->getId());
        $item->saveInto($subMenu);

        $ancestors = $item->relativeQuery()->parent()->includeAncestors()->all();
        $I->assertCount(2, $ancestors);

        $I->assertEquals('Main', $ancestors[0]->getName());
        $I->assertEquals('SubMenu', $ancestors[1]->getName());
    }

    public function testNavigationDescendants(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $subMenu = new Menu();
        $subMenu->setName('SubMenu');
        $subMenu->setLanguageId($language->getId());
        $subMenu->saveInto($mainMenu);

        $item = new Menu();
        $item->setName('Item');
        $item->setLanguageId($language->getId());
        $item->saveInto($subMenu);

        $descendants = $mainMenu->relativeQuery()->children()->includeDescendants()->all();
        $I->assertCount(2, $descendants);

        $I->assertEquals('SubMenu', $descendants[0]->getName());
        $I->assertEquals('Item', $descendants[1]->getName());
    }

    public function testMoveNodeWithSaveInto(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $footerMenu = new Menu();
        $footerMenu->setName('Footer');
        $footerMenu->setLanguageId($language->getId());
        $footerMenu->save();

        $itemA->saveInto($footerMenu);

        $itemA->refresh();
        $I->assertEquals($footerMenu->path.'.1', $itemA->path);
        $I->assertEquals(2, $itemA->level);

        $mainChildren = $mainMenu->relativeQuery()->children()->all();
        $I->assertCount(0, $mainChildren);

        $footerChildren = $footerMenu->relativeQuery()->children()->all();
        $I->assertCount(1, $footerChildren);
        $I->assertEquals('A', $footerChildren[0]->getName());
    }

    public function testMoveNodeWithChildren(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $itemA1 = new Menu();
        $itemA1->setName('A1');
        $itemA1->setLanguageId($language->getId());
        $itemA1->saveInto($itemA);

        $itemB = new Menu();
        $itemB->setName('B');
        $itemB->setLanguageId($language->getId());
        $itemB->saveInto($mainMenu);

        $itemA->saveInto($itemB);

        $itemA->refresh();
        $itemB->refresh();
        $itemA1->refresh();

        $I->assertEquals($itemB->path.'.1', $itemA->path);
        $I->assertEquals($itemA->path.'.1', $itemA1->path);
    }

    public function testReorderWithSaveBefore(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $itemB = new Menu();
        $itemB->setName('B');
        $itemB->setLanguageId($language->getId());
        $itemB->saveInto($mainMenu);

        $itemC = new Menu();
        $itemC->setName('C');
        $itemC->setLanguageId($language->getId());
        $itemC->saveInto($mainMenu);


        $itemB->saveBefore($itemA);
        $itemA->refresh();
        $itemB->refresh();
        $itemC->refresh();

        $I->assertEquals($mainMenu->path.'.1', $itemB->path);
        $I->assertEquals($mainMenu->path.'.2', $itemA->path);
        $I->assertEquals($mainMenu->path.'.3', $itemC->path);

        $itemC->saveBefore($itemA);
        $itemA->refresh();
        $itemB->refresh();
        $itemC->refresh();

        $I->assertEquals($mainMenu->path.'.1', $itemB->path);
        $I->assertEquals($mainMenu->path.'.2', $itemC->path);
        $I->assertEquals($mainMenu->path.'.3', $itemA->path);

        $children = $mainMenu->relativeQuery()->children()->all();
        $I->assertEquals('B', $children[0]->getName());
        $I->assertEquals('C', $children[1]->getName());
        $I->assertEquals('A', $children[2]->getName());
    }

    public function testReorderWithSaveAfter(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $itemB = new Menu();
        $itemB->setName('B');
        $itemB->setLanguageId($language->getId());
        $itemB->saveInto($mainMenu);

        $itemC = new Menu();
        $itemC->setName('C');
        $itemC->setLanguageId($language->getId());
        $itemC->saveInto($mainMenu);

        $itemA->saveAfter($itemC);
        $itemA->refresh();
        $itemB->refresh();
        $itemC->refresh();

        $I->assertEquals($mainMenu->path.'.1', $itemB->path);
        $I->assertEquals($mainMenu->path.'.2', $itemC->path);
        $I->assertEquals($mainMenu->path.'.3', $itemA->path);
    }

    public function testProtectedFieldsThrowException(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $menu = new Menu();
        $menu->setName('Test');
        $menu->setLanguageId($language->getId());
        $menu->save();

        $found = Menu::query()->andWhere(['id' => $menu->getId()])->one();

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->path = '999';
        });

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->left = 999.0;
        });

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->right = 999.0;
        });

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->level = 999;
        });
    }

    public function testProtectedFieldsViaSetterThrowException(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $menu = new Menu();
        $menu->setName('Test');
        $menu->setLanguageId($language->getId());
        $menu->save();

        $found = Menu::query()->andWhere(['id' => $menu->getId()])->one();

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->setPath('999');
        });

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->setLeft(999.0);
        });

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->setRight(999.0);
        });

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->setLevel(999);
        });
    }

    public function testDeleteClosesGap(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $itemB = new Menu();
        $itemB->setName('B');
        $itemB->setLanguageId($language->getId());
        $itemB->saveInto($mainMenu);

        $itemC = new Menu();
        $itemC->setName('C');
        $itemC->setLanguageId($language->getId());
        $itemC->saveInto($mainMenu);

        $itemB->delete();

        $itemA->refresh();
        $itemC->refresh();

        $I->assertEquals($mainMenu->path.'.1', $itemA->path);
        $I->assertEquals($mainMenu->path.'.2', $itemC->path);

        $children = $mainMenu->relativeQuery()->children()->all();
        $I->assertCount(2, $children);
    }

    public function testSiblingsNavigation(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $mainMenu = new Menu();
        $mainMenu->setName('Main');
        $mainMenu->setLanguageId($language->getId());
        $mainMenu->save();

        $itemA = new Menu();
        $itemA->setName('A');
        $itemA->setLanguageId($language->getId());
        $itemA->saveInto($mainMenu);

        $itemB = new Menu();
        $itemB->setName('B');
        $itemB->setLanguageId($language->getId());
        $itemB->saveInto($mainMenu);

        $itemC = new Menu();
        $itemC->setName('C');
        $itemC->setLanguageId($language->getId());
        $itemC->saveInto($mainMenu);

        $nextSibling = $itemA->relativeQuery()->siblings()->next()->one();
        $I->assertNotNull($nextSibling);
        $I->assertEquals('B', $nextSibling->getName());

        $prevSibling = $itemC->relativeQuery()->siblings()->previous()->one();
        $I->assertNotNull($prevSibling);
        $I->assertEquals('B', $prevSibling->getName());

        $allSiblings = $itemB->relativeQuery()->siblings()->all();
        $I->assertCount(2, $allSiblings);
    }
}
