<?php

declare(strict_types=1);

/**
 * ExportImportPropsCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Services\FileService;
use Blackcube\Dcore\Tests\Support\ExportImport\CreateExportImportProbes;
use Blackcube\Dcore\Tests\Support\ExportImport\ExportImportProbe;
use Blackcube\Dcore\Tests\Support\ExportImport\PublicFieldProbe;
use Blackcube\Dcore\Tests\Support\ExportImport\TestMapperService;
use Blackcube\Dcore\Tests\Support\ExportImport\TestFileProvider;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\Dcore\Tests\Support\MysqlHelper;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Proves MapperService discovers annotations on the three targets:
 * method, class (via `method`/`property`), and public property — and apply `format`
 * callables. Method + class-targeting go through a real DB round-trip; public property
 * and format callable are checked in memory (a public prop has no persisted column).
 *
 * The {{%exportImportProbes}} table is dropped + recreated before each test via a
 * test-only migration (not registered in the dcore Migrations namespace).
 */
final class ExportImportPropsCest
{
    private ConnectionInterface $db;

    public function _before(ModelsTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);

        $builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $this->db->createCommand('DROP TABLE IF EXISTS {{%exportImportProbes}}')->execute();
        (new CreateExportImportProbes())->up($builder);
        $this->db->getSchema()->refresh();
    }

    private function exporter(): TestMapperService
    {
        return new TestMapperService(new FileService(new TestFileProvider()));
    }

    public function methodAndClassTargetRoundTripThroughDb(ModelsTester $I): void
    {
        $I->wantTo('export/re-import method-level and class-targeted attributes through a real DB round-trip');

        $probe = new ExportImportProbe();
        $probe->setLabel('hello');
        $probe->setScore(42);
        $probe->save();

        $id = $probe->getId();
        $I->assertNotNull($id, 'probe should have an id after save');

        $reloaded = (new ActiveQuery(ExportImportProbe::class))->andWhere(['id' => $id])->one();
        $I->assertNotNull($reloaded, 'probe should be found in DB');

        $exported = $this->exporter()->exportProbe($reloaded);
        $I->assertEquals('hello', $exported['label'], 'method-level attribute exported (getLabel → label)');
        $I->assertEquals(42, $exported['score'], 'class-level attribute targeting getScore exported (→ score)');

        $clone = new ExportImportProbe();
        $this->exporter()->import($clone, $exported);
        $clone->save();

        $cloneId = $clone->getId();
        $I->assertNotEquals($id, $cloneId, 'clone is a distinct row');

        $reclone = (new ActiveQuery(ExportImportProbe::class))->andWhere(['id' => $cloneId])->one();
        $I->assertEquals('hello', $reclone->getLabel(), 'method-level setter fed, persisted');
        $I->assertEquals(42, $reclone->getScore(), 'class-level attribute targeting setScore fed, persisted');
    }

    public function publicPropertiesAndFormatInMemory(ModelsTester $I): void
    {
        $I->wantTo('export/import a public property directly and one through a format callable');

        $probe = new PublicFieldProbe();
        $probe->title = 'direct';
        $probe->when = new DateTimeImmutable('2026-03-15 08:00:00');

        $exported = $this->exporter()->exportProbe($probe);
        $I->assertEquals('direct', $exported['title'], 'public property read directly on export');
        $I->assertEquals('2026-03-15 08:00:00', $exported['when'], 'public property transformed by dateToString callable');

        $clone = new PublicFieldProbe();
        $this->exporter()->import($clone, [
            'title' => 'written',
            'when' => '2026-03-15 08:00:00',
        ]);

        $I->assertEquals('written', $clone->title, 'public property written directly on import');
        $I->assertInstanceOf(DateTimeImmutable::class, $clone->when, 'stringToDate callable produced a DateTimeImmutable');
        $I->assertEquals('2026-03-15 08:00:00', $clone->when->format('Y-m-d H:i:s'), 'round-trip date value preserved');
    }
}
