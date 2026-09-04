<?php

declare(strict_types=1);

/**
 * TagCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Tag;
use Blackcube\ActiveRecord\Elastic\ElasticSchema;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class TagCest
{
    use DatabaseCestTrait;

    private function createElasticSchema(string $schema = '{"type":"object"}'): ElasticSchema
    {
        $elasticSchema = new ElasticSchema();
        $elasticSchema->setName('tag-schema-'.uniqid());
        $elasticSchema->setSchema($schema);
        $elasticSchema->save();
        return $elasticSchema;
    }

    public function testInsertAsRoot(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Category');
        $tag->save();

        $I->assertNotNull($tag->getId());
        $I->assertMatchesRegularExpression('/^\d+$/', $tag->path, 'Root path should be a single number');
        $I->assertEquals(1, $tag->level);
        $I->assertNotNull($tag->getDateCreate());
    }

    public function testMultipleCategories(ModelsTester $I): void
    {
        $cat1 = new Tag();
        $cat1->setName('Category 1');
        $cat1->save();

        $cat2 = new Tag();
        $cat2->setName('Category 2');
        $cat2->save();

        $I->assertEquals(1, $cat1->level);
        $I->assertEquals(1, $cat2->level);
        $I->assertNotEquals($cat1->path, $cat2->path);
        $I->assertEquals((int)$cat1->path + 1, (int)$cat2->path);
    }

    public function testSaveInto(ModelsTester $I): void
    {
        $category = new Tag();
        $category->setName('Category');
        $category->save();

        $subTag = new Tag();
        $subTag->setName('Sub-Tag');
        $subTag->saveInto($category);

        $I->assertEquals($category->path.'.1', $subTag->path);
        $I->assertEquals(2, $subTag->level);
    }

    public function testWithElasticProperties(ModelsTester $I): void
    {
        $jsonSchema = json_encode([
            'type' => 'object',
            'properties' => [
                'color' => ['type' => 'string'],
                'icon' => ['type' => 'string'],
            ],
        ]);
        $schema = $this->createElasticSchema($jsonSchema);

        $tag = new Tag();
        $tag->setName('Colored Tag');
        $tag->elasticSchemaId = $schema->getId();
        $tag->color = '#FF0000';
        $tag->icon = 'star';
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

        $I->assertEquals('#FF0000', $found->color);
        $I->assertEquals('star', $found->icon);
    }

    public function testUpdate(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Original');
        $tag->save();

        $tag->setName('Updated');
        $tag->save();

        $I->assertEquals('Updated', $tag->getName());
        $I->assertNotNull($tag->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $category = new Tag();
        $category->setName('Category');
        $category->save();

        $subTag = new Tag();
        $subTag->setName('Sub-Tag');
        $subTag->saveInto($category);

        $subTagId = $subTag->getId();
        $subTag->delete();

        $found = Tag::query()->andWhere(['id' => $subTagId])->one();
        $I->assertNull($found);
    }

    public function testTreeNavigation(ModelsTester $I): void
    {
        $category = new Tag();
        $category->setName('Category');
        $category->save();

        $tag1 = new Tag();
        $tag1->setName('Tag 1');
        $tag1->saveInto($category);

        $tag2 = new Tag();
        $tag2->setName('Tag 2');
        $tag2->saveInto($category);

        $children = $category->relativeQuery()->children()->all();
        $I->assertCount(2, $children);

        $firstTag = $category->relativeQuery()->children()->one();
        $I->assertEquals('Tag 1', $firstTag->getName());
    }


    public function testMultipleChildrenOrdering(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($root);

        $tagB = new Tag();
        $tagB->setName('B');
        $tagB->saveInto($root);

        $tagC = new Tag();
        $tagC->setName('C');
        $tagC->saveInto($root);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(3, $children);
        $I->assertEquals('A', $children[0]->getName());
        $I->assertEquals('B', $children[1]->getName());
        $I->assertEquals('C', $children[2]->getName());

        $I->assertEquals($root->path.'.1', $tagA->path);
        $I->assertEquals($root->path.'.2', $tagB->path);
        $I->assertEquals($root->path.'.3', $tagC->path);
    }

    public function testNavigationParent(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $child = new Tag();
        $child->setName('Child');
        $child->saveInto($root);

        $parent = $child->relativeQuery()->parent()->one();
        $I->assertNotNull($parent);
        $I->assertEquals('Root', $parent->getName());
    }

    public function testNavigationAncestors(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $child = new Tag();
        $child->setName('Child');
        $child->saveInto($root);

        $grandchild = new Tag();
        $grandchild->setName('Grandchild');
        $grandchild->saveInto($child);

        $ancestors = $grandchild->relativeQuery()->parent()->includeAncestors()->all();
        $I->assertCount(2, $ancestors);

        $I->assertEquals('Root', $ancestors[0]->getName());
        $I->assertEquals('Child', $ancestors[1]->getName());
    }

    public function testNavigationDescendants(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $child = new Tag();
        $child->setName('Child');
        $child->saveInto($root);

        $grandchild = new Tag();
        $grandchild->setName('Grandchild');
        $grandchild->saveInto($child);

        $descendants = $root->relativeQuery()->children()->includeDescendants()->all();
        $I->assertCount(2, $descendants);

        $I->assertEquals('Child', $descendants[0]->getName());
        $I->assertEquals('Grandchild', $descendants[1]->getName());
    }

    public function testMoveNodeWithSaveInto(ModelsTester $I): void
    {
        $cat1 = new Tag();
        $cat1->setName('Cat1');
        $cat1->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($cat1);

        $cat2 = new Tag();
        $cat2->setName('Cat2');
        $cat2->save();

        $tagA->saveInto($cat2);

        $tagA->refresh();
        $I->assertEquals($cat2->path.'.1', $tagA->path);
        $I->assertEquals(2, $tagA->level);

        $cat1Children = $cat1->relativeQuery()->children()->all();
        $I->assertCount(0, $cat1Children);

        $cat2Children = $cat2->relativeQuery()->children()->all();
        $I->assertCount(1, $cat2Children);
        $I->assertEquals('A', $cat2Children[0]->getName());
    }

    public function testMoveNodeWithChildren(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($root);

        $tagA1 = new Tag();
        $tagA1->setName('A1');
        $tagA1->saveInto($tagA);

        $tagB = new Tag();
        $tagB->setName('B');
        $tagB->saveInto($root);

        $tagA->saveInto($tagB);

        $tagA->refresh();
        $tagB->refresh();
        $tagA1->refresh();

        $I->assertEquals($tagB->path.'.1', $tagA->path);
        $I->assertEquals($tagA->path.'.1', $tagA1->path);
    }

    public function testReorderWithSaveBefore(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($root);

        $tagB = new Tag();
        $tagB->setName('B');
        $tagB->saveInto($root);

        $tagC = new Tag();
        $tagC->setName('C');
        $tagC->saveInto($root);


        $tagB->saveBefore($tagA);
        $tagA->refresh();
        $tagB->refresh();
        $tagC->refresh();

        $I->assertEquals($root->path.'.1', $tagB->path);
        $I->assertEquals($root->path.'.2', $tagA->path);
        $I->assertEquals($root->path.'.3', $tagC->path);

        $tagC->saveBefore($tagA);
        $tagA->refresh();
        $tagB->refresh();
        $tagC->refresh();

        $I->assertEquals($root->path.'.1', $tagB->path);
        $I->assertEquals($root->path.'.2', $tagC->path);
        $I->assertEquals($root->path.'.3', $tagA->path);

        $children = $root->relativeQuery()->children()->all();
        $I->assertEquals('B', $children[0]->getName());
        $I->assertEquals('C', $children[1]->getName());
        $I->assertEquals('A', $children[2]->getName());
    }

    public function testReorderWithSaveAfter(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($root);

        $tagB = new Tag();
        $tagB->setName('B');
        $tagB->saveInto($root);

        $tagC = new Tag();
        $tagC->setName('C');
        $tagC->saveInto($root);

        $tagA->saveAfter($tagC);
        $tagA->refresh();
        $tagB->refresh();
        $tagC->refresh();

        $I->assertEquals($root->path.'.1', $tagB->path);
        $I->assertEquals($root->path.'.2', $tagC->path);
        $I->assertEquals($root->path.'.3', $tagA->path);
    }

    public function testProtectedFieldsThrowException(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

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
        $tag = new Tag();
        $tag->setName('Test');
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

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
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($root);

        $tagB = new Tag();
        $tagB->setName('B');
        $tagB->saveInto($root);

        $tagC = new Tag();
        $tagC->setName('C');
        $tagC->saveInto($root);

        $tagB->delete();

        $tagA->refresh();
        $tagC->refresh();

        $I->assertEquals($root->path.'.1', $tagA->path);
        $I->assertEquals($root->path.'.2', $tagC->path);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(2, $children);
    }

    public function testSiblingsNavigation(ModelsTester $I): void
    {
        $root = new Tag();
        $root->setName('Root');
        $root->save();

        $tagA = new Tag();
        $tagA->setName('A');
        $tagA->saveInto($root);

        $tagB = new Tag();
        $tagB->setName('B');
        $tagB->saveInto($root);

        $tagC = new Tag();
        $tagC->setName('C');
        $tagC->saveInto($root);

        $nextSibling = $tagA->relativeQuery()->siblings()->next()->one();
        $I->assertNotNull($nextSibling);
        $I->assertEquals('B', $nextSibling->getName());

        $prevSibling = $tagC->relativeQuery()->siblings()->previous()->one();
        $I->assertNotNull($prevSibling);
        $I->assertEquals('B', $prevSibling->getName());

        $allSiblings = $tagB->relativeQuery()->siblings()->all();
        $I->assertCount(2, $allSiblings);
    }


    private function createMetaSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-meta-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'color' => [
                    'type' => 'string',
                    'title' => 'Color',
                ],
                'icon' => [
                    'type' => 'string',
                    'title' => 'Icon',
                ],
                'priority' => [
                    'type' => 'integer',
                    'title' => 'Priority',
                ],
            ],
        ]));
        $schema->save();
        return $schema;
    }

    private function createSeoSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-seo-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'metaTitle' => [
                    'type' => 'string',
                    'title' => 'Meta Title',
                ],
                'metaDescription' => [
                    'type' => 'string',
                    'title' => 'Meta Description',
                ],
            ],
            'required' => ['metaTitle'],
        ]));
        $schema->save();
        return $schema;
    }

    public function testElasticAssociateSchema(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Test Tag');
        $tag->elasticSchemaId = $schema->getId();
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

        $I->assertNotNull($found);
        $I->assertEquals($schema->getId(), $found->elasticSchemaId);
    }

    public function testElasticDynamicProperties(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Dynamic Props');
        $tag->elasticSchemaId = $schema->getId();
        $tag->color = '#FF0000';
        $tag->icon = 'star';
        $tag->priority = 10;
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

        $I->assertEquals('#FF0000', $found->color);
        $I->assertEquals('star', $found->icon);
        $I->assertEquals(10, $found->priority);
    }

    public function testElasticGetValues(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Get Values');
        $tag->elasticSchemaId = $schema->getId();
        $tag->color = '#00FF00';
        $tag->icon = 'heart';
        $tag->priority = 5;
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $values = $found->getElasticValues();

        $I->assertArrayHasKey('color', $values);
        $I->assertArrayHasKey('icon', $values);
        $I->assertArrayHasKey('priority', $values);
        $I->assertEquals('#00FF00', $values['color']);
        $I->assertEquals('heart', $values['icon']);
        $I->assertEquals(5, $values['priority']);
    }

    public function testElasticSchemaChangeResetsProperties(ModelsTester $I): void
    {
        $schemaMeta = $this->createMetaSchema();
        $schemaSeo = $this->createSeoSchema();

        $tag = new Tag();
        $tag->setName('Schema Change');
        $tag->elasticSchemaId = $schemaMeta->getId();
        $tag->color = '#0000FF';
        $tag->save();

        $tagId = $tag->getId();

        $found = Tag::query()->andWhere(['id' => $tagId])->one();
        $found->elasticSchemaId = $schemaSeo->getId();
        $found->save();

        Tag::clearSchemaCache();
        $reloaded = Tag::query()->andWhere(['id' => $tagId])->one();

        $reloaded->metaTitle = 'SEO Title';
        $reloaded->metaDescription = 'SEO Description';
        $reloaded->save();

        $final = Tag::query()->andWhere(['id' => $tagId])->one();

        $I->assertEquals('SEO Title', $final->metaTitle);
        $I->assertEquals('SEO Description', $final->metaDescription);
        $I->assertEquals($schemaSeo->getId(), $final->elasticSchemaId);
    }

    public function testElasticProtectedExtrasColumn(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Protected');
        $tag->elasticSchemaId = $schema->getId();
        $tag->color = '#FFFFFF';
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->_extras = '{"hacked": true}';
        });
    }

    public function testElasticPersistence(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Persistence Test');
        $tag->elasticSchemaId = $schema->getId();
        $tag->color = '#123456';
        $tag->icon = 'check';
        $tag->priority = 99;
        $tag->save();

        $id = $tag->getId();

        Tag::clearSchemaCache();

        $reloaded = Tag::query()->andWhere(['id' => $id])->one();

        $I->assertEquals('#123456', $reloaded->color);
        $I->assertEquals('check', $reloaded->icon);
        $I->assertEquals(99, $reloaded->priority);
    }

    public function testElasticUnknownPropertyThrows(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Unknown Prop');
        $tag->elasticSchemaId = $schema->getId();
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

        $I->expectThrowable(\Throwable::class, function () use ($found) {
            $found->unknownElasticProperty = 'value';
        });
    }

    public function testElasticNullSchemaAllowsNoExtras(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('No Schema');
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();

        $I->assertNull($found->elasticSchemaId);

        $I->expectThrowable(\Throwable::class, function () use ($found) {
            $found->someProperty = 'value';
        });
    }

    public function testElasticUpdateProperties(ModelsTester $I): void
    {
        $schema = $this->createMetaSchema();

        $tag = new Tag();
        $tag->setName('Update Props');
        $tag->elasticSchemaId = $schema->getId();
        $tag->color = '#111111';
        $tag->icon = 'old';
        $tag->save();

        $found = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $found->color = '#222222';
        $found->icon = 'new';
        $found->save();

        $reloaded = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $I->assertEquals('#222222', $reloaded->color);
        $I->assertEquals('new', $reloaded->icon);
    }
}
