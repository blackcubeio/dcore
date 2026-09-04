<?php

declare(strict_types=1);

/**
 * ContentCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\ActiveRecord\Elastic\ElasticSchema;

final class ContentCest
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

    private function createElasticSchema(string $schema = '{"type":"object"}'): ElasticSchema
    {
        $elasticSchema = new ElasticSchema();
        $elasticSchema->setName('content-schema-'.uniqid());
        $elasticSchema->setSchema($schema);
        $elasticSchema->save();
        return $elasticSchema;
    }

    public function testInsertAsRoot(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $content = new Content();
        $content->setName('Root Content');
        $content->setLanguageId($language->getId());
        $content->save();

        $I->assertNotNull($content->getId());
        $I->assertMatchesRegularExpression('/^\d+$/', $content->path, 'Root path should be a single number');
        $I->assertEquals(1, $content->level);
        $I->assertNotNull($content->getDateCreate());
    }

    public function testMultipleRoots(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root1 = new Content();
        $root1->setName('Root 1');
        $root1->setLanguageId($language->getId());
        $root1->save();

        $root2 = new Content();
        $root2->setName('Root 2');
        $root2->setLanguageId($language->getId());
        $root2->save();

        $I->assertEquals(1, $root1->level);
        $I->assertEquals(1, $root2->level);
        $I->assertNotEquals($root1->path, $root2->path);
        $I->assertEquals((int)$root1->path + 1, (int)$root2->path);
    }

    public function testSaveInto(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $parent = new Content();
        $parent->setName('Parent');
        $parent->setLanguageId($language->getId());
        $parent->save();

        $child = new Content();
        $child->setName('Child');
        $child->setLanguageId($language->getId());
        $child->saveInto($parent);

        $I->assertEquals($parent->path.'.1', $child->path);
        $I->assertEquals(2, $child->level);
    }

    public function testWithElasticProperties(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $jsonSchema = json_encode([
            'type' => 'object',
            'properties' => [
                'subtitle' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
        ]);
        $schema = $this->createElasticSchema($jsonSchema);

        $content = new Content();
        $content->setName('Elastic Content');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->subtitle = 'My Subtitle';
        $content->description = 'My Description';
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->assertEquals('My Subtitle', $found->subtitle);
        $I->assertEquals('My Description', $found->description);
    }

    public function testUpdate(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $content = new Content();
        $content->setName('Original');
        $content->setLanguageId($language->getId());
        $content->save();

        $content->setName('Updated');
        $content->save();

        $I->assertEquals('Updated', $content->getName());
        $I->assertNotNull($content->getDateUpdate());
    }

    public function testDateCreateAndDateUpdateEvents(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $content = new Content();
        $content->setName('Event Test');
        $content->setLanguageId($language->getId());
        $content->save();

        $I->assertNotNull($content->getDateCreate(), 'dateCreate should be set on insert');
        $I->assertNotNull($content->getDateUpdate(), 'dateUpdate should be set on insert');

        $dateCreateOnInsert = $content->getDateCreate();
        $dateUpdateOnInsert = $content->getDateUpdate();

        usleep(100000);

        $content->setName('Event Test Updated');
        $content->save();

        $I->assertEquals(
            $dateCreateOnInsert->getTimestamp(),
            $content->getDateCreate()->getTimestamp(),
            'dateCreate should NOT change on update'
        );
        $I->assertGreaterThanOrEqual(
            $dateUpdateOnInsert->getTimestamp(),
            $content->getDateUpdate()->getTimestamp(),
            'dateUpdate should be >= previous value on update'
        );

        $reloaded = Content::query()->andWhere(['id' => $content->getId()])->one();
        $I->assertNotNull($reloaded->getDateCreate(), 'dateCreate should be persisted in DB');
        $I->assertNotNull($reloaded->getDateUpdate(), 'dateUpdate should be persisted in DB');
        $I->assertEquals(
            $dateCreateOnInsert->getTimestamp(),
            $reloaded->getDateCreate()->getTimestamp(),
            'dateCreate in DB should match original'
        );
    }

    public function testDelete(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $parent = new Content();
        $parent->setName('Parent');
        $parent->setLanguageId($language->getId());
        $parent->save();

        $child = new Content();
        $child->setName('Child');
        $child->setLanguageId($language->getId());
        $child->saveInto($parent);

        $childId = $child->getId();
        $child->delete();

        $found = Content::query()->andWhere(['id' => $childId])->one();
        $I->assertNull($found);
    }

    public function testTreeNavigation(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $child1 = new Content();
        $child1->setName('Child 1');
        $child1->setLanguageId($language->getId());
        $child1->saveInto($root);

        $child2 = new Content();
        $child2->setName('Child 2');
        $child2->setLanguageId($language->getId());
        $child2->saveInto($root);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(2, $children);

        $firstChild = $root->relativeQuery()->children()->one();
        $I->assertEquals('Child 1', $firstChild->getName());
    }


    public function testMultipleChildrenOrdering(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $childA = new Content();
        $childA->setName('A');
        $childA->setLanguageId($language->getId());
        $childA->saveInto($root);

        $childB = new Content();
        $childB->setName('B');
        $childB->setLanguageId($language->getId());
        $childB->saveInto($root);

        $childC = new Content();
        $childC->setName('C');
        $childC->setLanguageId($language->getId());
        $childC->saveInto($root);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(3, $children);
        $I->assertEquals('A', $children[0]->getName());
        $I->assertEquals('B', $children[1]->getName());
        $I->assertEquals('C', $children[2]->getName());

        $I->assertEquals($root->path.'.1', $childA->path);
        $I->assertEquals($root->path.'.2', $childB->path);
        $I->assertEquals($root->path.'.3', $childC->path);
    }

    public function testNavigationParent(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $child = new Content();
        $child->setName('Child');
        $child->setLanguageId($language->getId());
        $child->saveInto($root);

        $parent = $child->relativeQuery()->parent()->one();
        $I->assertNotNull($parent);
        $I->assertEquals('Root', $parent->getName());
    }

    public function testNavigationAncestors(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $child = new Content();
        $child->setName('Child');
        $child->setLanguageId($language->getId());
        $child->saveInto($root);

        $grandchild = new Content();
        $grandchild->setName('Grandchild');
        $grandchild->setLanguageId($language->getId());
        $grandchild->saveInto($child);

        $ancestors = $grandchild->relativeQuery()->parent()->includeAncestors()->all();
        $I->assertCount(2, $ancestors);

        $I->assertEquals('Root', $ancestors[0]->getName());
        $I->assertEquals('Child', $ancestors[1]->getName());
    }

    public function testNavigationDescendants(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $child = new Content();
        $child->setName('Child');
        $child->setLanguageId($language->getId());
        $child->saveInto($root);

        $grandchild = new Content();
        $grandchild->setName('Grandchild');
        $grandchild->setLanguageId($language->getId());
        $grandchild->saveInto($child);

        $descendants = $root->relativeQuery()->children()->includeDescendants()->all();
        $I->assertCount(2, $descendants);

        $I->assertEquals('Child', $descendants[0]->getName());
        $I->assertEquals('Grandchild', $descendants[1]->getName());
    }

    public function testMoveNodeWithSaveInto(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root1 = new Content();
        $root1->setName('Root1');
        $root1->setLanguageId($language->getId());
        $root1->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root1);

        $root2 = new Content();
        $root2->setName('Root2');
        $root2->setLanguageId($language->getId());
        $root2->save();

        $nodeA->saveInto($root2);

        $nodeA->refresh();
        $I->assertEquals($root2->path.'.1', $nodeA->path);
        $I->assertEquals(2, $nodeA->level);

        $root1Children = $root1->relativeQuery()->children()->all();
        $I->assertCount(0, $root1Children);

        $root2Children = $root2->relativeQuery()->children()->all();
        $I->assertCount(1, $root2Children);
        $I->assertEquals('A', $root2Children[0]->getName());
    }

    public function testMoveNodeWithChildren(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root);

        $nodeA1 = new Content();
        $nodeA1->setName('A1');
        $nodeA1->setLanguageId($language->getId());
        $nodeA1->saveInto($nodeA);

        $nodeB = new Content();
        $nodeB->setName('B');
        $nodeB->setLanguageId($language->getId());
        $nodeB->saveInto($root);

        $nodeA->saveInto($nodeB);

        $nodeA->refresh();
        $nodeB->refresh();
        $nodeA1->refresh();

        $I->assertEquals($nodeB->path.'.1', $nodeA->path);
        $I->assertEquals($nodeA->path.'.1', $nodeA1->path);
    }

    public function testReorderWithSaveBefore(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root);

        $nodeB = new Content();
        $nodeB->setName('B');
        $nodeB->setLanguageId($language->getId());
        $nodeB->saveInto($root);

        $nodeC = new Content();
        $nodeC->setName('C');
        $nodeC->setLanguageId($language->getId());
        $nodeC->saveInto($root);


        $nodeB->saveBefore($nodeA);
        $nodeA->refresh();
        $nodeB->refresh();
        $nodeC->refresh();

        $I->assertEquals($root->path.'.1', $nodeB->path);
        $I->assertEquals($root->path.'.2', $nodeA->path);
        $I->assertEquals($root->path.'.3', $nodeC->path);

        $nodeC->saveBefore($nodeA);
        $nodeA->refresh();
        $nodeB->refresh();
        $nodeC->refresh();

        $I->assertEquals($root->path.'.1', $nodeB->path);
        $I->assertEquals($root->path.'.2', $nodeC->path);
        $I->assertEquals($root->path.'.3', $nodeA->path);

        $children = $root->relativeQuery()->children()->all();
        $I->assertEquals('B', $children[0]->getName());
        $I->assertEquals('C', $children[1]->getName());
        $I->assertEquals('A', $children[2]->getName());
    }

    public function testReorderWithSaveAfter(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root);

        $nodeB = new Content();
        $nodeB->setName('B');
        $nodeB->setLanguageId($language->getId());
        $nodeB->saveInto($root);

        $nodeC = new Content();
        $nodeC->setName('C');
        $nodeC->setLanguageId($language->getId());
        $nodeC->saveInto($root);

        $nodeA->saveAfter($nodeC);
        $nodeA->refresh();
        $nodeB->refresh();
        $nodeC->refresh();

        $I->assertEquals($root->path.'.1', $nodeB->path);
        $I->assertEquals($root->path.'.2', $nodeC->path);
        $I->assertEquals($root->path.'.3', $nodeA->path);
    }

    public function testProtectedFieldsThrowException(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $content = new Content();
        $content->setName('Test');
        $content->setLanguageId($language->getId());
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

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

        $content = new Content();
        $content->setName('Test');
        $content->setLanguageId($language->getId());
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

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

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root);

        $nodeB = new Content();
        $nodeB->setName('B');
        $nodeB->setLanguageId($language->getId());
        $nodeB->saveInto($root);

        $nodeC = new Content();
        $nodeC->setName('C');
        $nodeC->setLanguageId($language->getId());
        $nodeC->saveInto($root);

        $nodeB->delete();

        $nodeA->refresh();
        $nodeC->refresh();

        $I->assertEquals($root->path.'.1', $nodeA->path);
        $I->assertEquals($root->path.'.2', $nodeC->path);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(2, $children);
    }

    public function testDeleteWithChildrenClosesGap(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root);

        $nodeA1 = new Content();
        $nodeA1->setName('A1');
        $nodeA1->setLanguageId($language->getId());
        $nodeA1->saveInto($nodeA);

        $nodeB = new Content();
        $nodeB->setName('B');
        $nodeB->setLanguageId($language->getId());
        $nodeB->saveInto($root);

        $nodeA->delete();

        $nodeB->refresh();

        $I->assertEquals($root->path.'.1', $nodeB->path);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(1, $children);
        $I->assertEquals('B', $children[0]->getName());
    }

    public function testSiblingsNavigation(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $root = new Content();
        $root->setName('Root');
        $root->setLanguageId($language->getId());
        $root->save();

        $nodeA = new Content();
        $nodeA->setName('A');
        $nodeA->setLanguageId($language->getId());
        $nodeA->saveInto($root);

        $nodeB = new Content();
        $nodeB->setName('B');
        $nodeB->setLanguageId($language->getId());
        $nodeB->saveInto($root);

        $nodeC = new Content();
        $nodeC->setName('C');
        $nodeC->setLanguageId($language->getId());
        $nodeC->saveInto($root);

        $nextSibling = $nodeA->relativeQuery()->siblings()->next()->one();
        $I->assertNotNull($nextSibling);
        $I->assertEquals('B', $nextSibling->getName());

        $prevSibling = $nodeC->relativeQuery()->siblings()->previous()->one();
        $I->assertNotNull($prevSibling);
        $I->assertEquals('B', $prevSibling->getName());

        $allSiblings = $nodeB->relativeQuery()->siblings()->all();
        $I->assertCount(2, $allSiblings);
    }


    private function createContactSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-contact-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'title' => 'Email',
                ],
                'telephone' => [
                    'type' => 'string',
                    'pattern' => '^[0-9]{10}$',
                    'title' => 'Phone',
                ],
            ],
            'required' => ['email'],
        ]));
        $schema->save();
        return $schema;
    }

    private function createTextSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-text-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'title' => 'Titre',
                ],
                'text' => [
                    'type' => 'string',
                    'title' => 'Contenu',
                ],
            ],
            'required' => ['title'],
        ]));
        $schema->save();
        return $schema;
    }

    private function createAddressSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-address-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'title' => 'Nom',
                ],
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'street' => ['type' => 'string', 'title' => 'Rue'],
                        'city' => ['type' => 'string', 'title' => 'Ville'],
                        'zipcode' => [
                            'type' => 'string',
                            'pattern' => '^[0-9]{5}$',
                            'title' => 'Code postal',
                        ],
                    ],
                    'required' => ['city'],
                ],
            ],
            'required' => ['name'],
        ]));
        $schema->save();
        return $schema;
    }

    public function testElasticAssociateSchema(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Test Content');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->assertNotNull($found);
        $I->assertEquals($schema->getId(), $found->elasticSchemaId);
    }

    public function testElasticDynamicProperties(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Dynamic Props');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->email = 'test@example.com';
        $content->telephone = '0123456789';
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->assertEquals('test@example.com', $found->email);
        $I->assertEquals('0123456789', $found->telephone);
    }

    public function testElasticNestedObject(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createAddressSchema();

        $content = new Content();
        $content->setName('Nested Object');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->name = 'John Doe';
        $content->address = [
            'street' => '123 Main St',
            'city' => 'Paris',
            'zipcode' => '75001',
        ];
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->assertEquals('John Doe', $found->name);
        $I->assertIsArray($found->address);
        $I->assertEquals('Paris', $found->address['city']);
        $I->assertEquals('75001', $found->address['zipcode']);
    }

    public function testElasticGetValues(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Get Values');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->email = 'values@example.com';
        $content->telephone = '0987654321';
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();
        $values = $found->getElasticValues();

        $I->assertArrayHasKey('email', $values);
        $I->assertArrayHasKey('telephone', $values);
        $I->assertEquals('values@example.com', $values['email']);
        $I->assertEquals('0987654321', $values['telephone']);
    }

    public function testElasticSchemaChangeResetsProperties(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schemaContact = $this->createContactSchema();
        $schemaText = $this->createTextSchema();

        $content = new Content();
        $content->setName('Schema Change');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schemaContact->getId();
        $content->email = 'test@example.com';
        $content->save();

        $contentId = $content->getId();

        $found = Content::query()->andWhere(['id' => $contentId])->one();
        $found->elasticSchemaId = $schemaText->getId();
        $found->save();

        Content::clearSchemaCache();
        $reloaded = Content::query()->andWhere(['id' => $contentId])->one();

        $reloaded->title = 'New Title';
        $reloaded->text = 'New Text';
        $reloaded->save();

        $final = Content::query()->andWhere(['id' => $contentId])->one();

        $I->assertEquals('New Title', $final->title);
        $I->assertEquals('New Text', $final->text);
        $I->assertEquals($schemaText->getId(), $final->elasticSchemaId);
    }

    public function testElasticProtectedExtrasColumn(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Protected');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->email = 'protected@example.com';
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->expectThrowable(\Error::class, function () use ($found) {
            $found->_extras = '{"hacked": true}';
        });
    }

    public function testElasticPersistence(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Persistence Test');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->email = 'persist@example.com';
        $content->telephone = '0123456789';
        $content->save();

        $id = $content->getId();

        Content::clearSchemaCache();

        $reloaded = Content::query()->andWhere(['id' => $id])->one();

        $I->assertEquals('persist@example.com', $reloaded->email);
        $I->assertEquals('0123456789', $reloaded->telephone);
    }

    public function testElasticUnknownPropertyThrows(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Unknown Prop');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->expectThrowable(\Throwable::class, function () use ($found) {
            $found->unknownElasticProperty = 'value';
        });
    }

    public function testElasticNullSchemaAllowsNoExtras(ModelsTester $I): void
    {
        $language = $this->createLanguage();

        $content = new Content();
        $content->setName('No Schema');
        $content->setLanguageId($language->getId());
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();

        $I->assertNull($found->elasticSchemaId);

        $I->expectThrowable(\Throwable::class, function () use ($found) {
            $found->someProperty = 'value';
        });
    }

    public function testElasticUpdateProperties(ModelsTester $I): void
    {
        $language = $this->createLanguage();
        $schema = $this->createContactSchema();

        $content = new Content();
        $content->setName('Update Props');
        $content->setLanguageId($language->getId());
        $content->elasticSchemaId = $schema->getId();
        $content->email = 'original@example.com';
        $content->telephone = '1111111111';
        $content->save();

        $found = Content::query()->andWhere(['id' => $content->getId()])->one();
        $found->email = 'updated@example.com';
        $found->telephone = '2222222222';
        $found->save();

        $reloaded = Content::query()->andWhere(['id' => $content->getId()])->one();
        $I->assertEquals('updated@example.com', $reloaded->email);
        $I->assertEquals('2222222222', $reloaded->telephone);
    }
}
