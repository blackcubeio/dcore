<?php

declare(strict_types=1);

/**
 * ParameterCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Parameter;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class ParameterCest
{
    use DatabaseCestTrait;

    public function testInsert(ModelsTester $I): void
    {
        $param = new Parameter();
        $param->setDomain('app');
        $param->setName('site_name');
        $param->setValue('My Website');

        $param->save();

        $I->assertEquals('app', $param->getDomain());
        $I->assertEquals('site_name', $param->getName());
        $I->assertNotNull($param->getDateCreate());
    }

    public function testCompositePrimaryKey(ModelsTester $I): void
    {
        $param1 = new Parameter();
        $param1->setDomain('mail');
        $param1->setName('host');
        $param1->setValue('smtp.example.com');
        $param1->save();

        $param2 = new Parameter();
        $param2->setDomain('mail');
        $param2->setName('port');
        $param2->setValue('587');
        $param2->save();

        // Same name, different domain
        $param3 = new Parameter();
        $param3->setDomain('api');
        $param3->setName('host');
        $param3->setValue('api.example.com');
        $param3->save();

        $found = Parameter::query()
            ->andWhere(['domain' => 'mail', 'name' => 'host'])
            ->one();

        $I->assertEquals('smtp.example.com', $found->getValue());
    }

    public function testUpdate(ModelsTester $I): void
    {
        $param = new Parameter();
        $param->setDomain('cache');
        $param->setName('ttl');
        $param->setValue('3600');
        $param->save();

        $param->setValue('7200');
        $param->save();

        $I->assertEquals('7200', $param->getValue());
        $I->assertNotNull($param->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $param = new Parameter();
        $param->setDomain('temp');
        $param->setName('deletable');
        $param->setValue('test');
        $param->save();

        $param->delete();

        $found = Parameter::query()
            ->andWhere(['domain' => 'temp', 'name' => 'deletable'])
            ->one();
        $I->assertNull($found);
    }

    public function testQueryByDomain(ModelsTester $I): void
    {
        $param1 = new Parameter();
        $param1->setDomain('settings');
        $param1->setName('opt1');
        $param1->setValue('val1');
        $param1->save();

        $param2 = new Parameter();
        $param2->setDomain('settings');
        $param2->setName('opt2');
        $param2->setValue('val2');
        $param2->save();

        $allSettings = Parameter::query()
            ->andWhere(['domain' => 'settings'])
            ->all();

        $I->assertCount(2, $allSettings);
    }
}
