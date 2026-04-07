<?php

declare(strict_types=1);

/**
 * HostCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class HostCest
{
    use DatabaseCestTrait;

    public function testInsert(ModelsTester $I): void
    {
        $host = new Host();
        $host->setName('Test Host');
        $host->setActive(true);
        // dateCreate should be set automatically by DefaultDateTimeOnInsert

        $host->save();

        $I->assertNotNull($host->getId(), 'Host id should not be null after insert');
        $I->assertGreaterThan(1, $host->getId(), 'Host id should be > 1 (1 is reserved for wildcard)');
        $I->assertNotNull($host->getDateCreate(), 'dateCreate should be set automatically on insert');
        $I->assertNotNull($host->getDateUpdate(), 'dateUpdate should be set automatically on insert');
    }

    public function testUpdate(ModelsTester $I): void
    {
        $host = new Host();
        $host->setName('Original Name');
        $host->setActive(true);
        // dateCreate set automatically
        $host->save();

        $id = $host->getId();
        $dateCreateBefore = $host->getDateCreate();
        $dateUpdateBefore = $host->getDateUpdate();
        $I->assertNotNull($dateUpdateBefore, 'dateUpdate should be set on insert');

        $host->setName('Updated Name');
        // dateUpdate should be updated automatically by SetDateTimeOnUpdate
        $host->save();

        $I->assertEquals('Updated Name', $host->getName(), 'Name should be updated');
        $I->assertNotNull($host->getDateUpdate(), 'dateUpdate should be set after update');
        $I->assertGreaterThanOrEqual($dateUpdateBefore->getTimestamp(), $host->getDateUpdate()->getTimestamp(), 'dateUpdate should be >= initial value');

        // Reload from DB
        $reloaded = Host::query()->andWhere(['id' => $id])->one();
        $I->assertNotNull($reloaded, 'Host should be found after update');
        $I->assertEquals('Updated Name', $reloaded->getName(), 'Name should be updated in DB');
        // Compare timestamps (DB may use different timezone representation)
        $I->assertEquals(
            $dateCreateBefore->getTimestamp(),
            $reloaded->getDateCreate()->getTimestamp(),
            'dateCreate should not change on update'
        );
        $I->assertNotNull($reloaded->getDateUpdate(), 'dateUpdate should be set in DB');
    }

    public function testSearch(ModelsTester $I): void
    {
        $host = new Host();
        $host->setName('Searchable Host');
        $host->setActive(true);
        $host->save();

        $id = $host->getId();

        $found = Host::query()->andWhere(['id' => $id])->one();

        $I->assertNotNull($found, 'Host should be found');
        $I->assertEquals('Searchable Host', $found->getName(), 'Name should match');
        $I->assertTrue($found->isActive(), 'Active should be true');
        $I->assertNotNull($found->getDateCreate(), 'dateCreate should be set');
    }

    public function testDelete(ModelsTester $I): void
    {
        $host = new Host();
        $host->setName('To Delete');
        $host->setActive(true);
        $host->save();

        $id = $host->getId();

        $host->delete();

        $found = Host::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found, 'Host should not be found after delete');
    }

    public function testWildcardHostExists(ModelsTester $I): void
    {
        // Wildcard host (id=1) is inserted by migration
        $wildcard = Host::query()->andWhere(['id' => 1])->one();

        $I->assertNotNull($wildcard, 'Wildcard host should exist');
        $I->assertEquals('*', $wildcard->getName(), 'Wildcard name should be *');
        $I->assertTrue($wildcard->isActive(), 'Wildcard should be active');
    }

    public function testQuery(ModelsTester $I): void
    {
        // Count existing hosts before test
        $countBefore = Host::query()
            ->andWhere(['!=', 'id', 1]) // Exclude wildcard
            ->count();

        // Create test hosts with unique names
        $uniqueId = uniqid();
        $host1 = new Host();
        $host1->setName('Active Host ' . $uniqueId);
        $host1->setActive(true);
        $host1->save();

        $host2 = new Host();
        $host2->setName('Inactive Host ' . $uniqueId);
        $host2->setActive(false);
        $host2->save();

        // Test query() with conditions - find our active host by name
        $activeHost = Host::query()
            ->andWhere(['name' => 'Active Host ' . $uniqueId])
            ->one();

        $I->assertNotNull($activeHost, 'Should find our active host');
        $I->assertTrue($activeHost->isActive());

        // Test query() - count should have increased by 2
        $countAfter = Host::query()
            ->andWhere(['!=', 'id', 1])
            ->count();

        $I->assertEquals($countBefore + 2, $countAfter, 'Should have 2 more hosts than before');
    }
}
