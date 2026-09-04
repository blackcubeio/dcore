<?php

declare(strict_types=1);

/**
 * XeoRefreshService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ContentBloc;
use Blackcube\Dcore\Models\SchemaSchema;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\TagBloc;
use Blackcube\Dcore\Models\Xeo;
use Throwable;

/**
 * Rebuilds the xeo blocs of an element from its content, driven by the
 * SchemaSchema mappings: the existing xeo blocs (and their files) are
 * dropped, then one xeo bloc is created per mapping matching the page schema
 * and each content bloc schema, prefilled through the field map and with the
 * source files duplicated. Single home of the logic, consumed by the dboard
 * refresh actions and the mcp xeo.refresh tool.
 */
class XeoRefreshService
{
    public function __construct(
        private FileService $fileService,
    ) {}

    /**
     * Rebuilds the xeo blocs of the element carried by $xeo.
     *
     * @return int The number of xeo blocs created
     */
    public function refresh(Content|Tag $model, Xeo $xeo): int
    {
        $created = 0;
        $transaction = $xeo->db()->beginTransaction();
        try {
            $existingBlocs = [];
            foreach ($xeo->getBlocsQuery()->each() as $bloc) {
                $existingBlocs[] = $bloc;
            }
            foreach ($existingBlocs as $bloc) {
                $this->fileService->deleteBlocFiles($bloc, $model);
                $xeo->detachBloc($bloc);
            }

            $articleSchemaId = $model->getElasticSchemaId();
            if ($articleSchemaId !== null) {
                $schemaSchemasQuery = SchemaSchema::query()
                    ->andWhere(['regularElasticSchemaId' => $articleSchemaId])
                    ->orderBy(['xeoElasticSchemaId' => SORT_ASC]);
                /** @var SchemaSchema $schemaSchema */
                foreach ($schemaSchemasQuery->each() as $schemaSchema) {
                    $this->createXeoBloc($model, $xeo, $schemaSchema, $model);
                    $created++;
                }
            }

            $pivotClass = $model instanceof Content ? ContentBloc::class : TagBloc::class;
            $fkColumn = $model instanceof Content ? 'contentId' : 'tagId';
            $articleBlocPivotsQuery = $pivotClass::query()
                ->andWhere([$fkColumn => $model->getId()])
                ->orderBy(['order' => SORT_ASC]);
            foreach ($articleBlocPivotsQuery->each() as $pivot) {
                $bloc = $pivot->getBlocQuery()->one();
                if ($bloc === null) {
                    continue;
                }
                $blocSchemaId = $bloc->getElasticSchemaId();
                if ($blocSchemaId === null) {
                    continue;
                }
                $schemaSchemasQuery = SchemaSchema::query()
                    ->andWhere(['regularElasticSchemaId' => $blocSchemaId])
                    ->orderBy(['xeoElasticSchemaId' => SORT_ASC]);
                /** @var SchemaSchema $schemaSchema */
                foreach ($schemaSchemasQuery->each() as $schemaSchema) {
                    $this->createXeoBloc($model, $xeo, $schemaSchema, $bloc);
                    $created++;
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $created;
    }

    /**
     * Creates one xeo bloc from a SchemaSchema mapping, prefilled from the
     * source (the element for the page schema, a content bloc otherwise).
     */
    private function createXeoBloc(Content|Tag $model, Xeo $xeo, SchemaSchema $schemaSchema, object $source): void
    {
        $xeoBloc = new Bloc();
        $xeoBloc->setElasticSchemaId($schemaSchema->getXeoElasticSchemaId());
        $xeoBloc->setActive(false);

        $mappingJson = $schemaSchema->getMapping();
        if ($mappingJson !== null) {
            $decoded = json_decode($mappingJson, true);
            if (isset($decoded['mapping']) === true && is_array($decoded['mapping']) === true) {
                foreach ($decoded['mapping'] as $sourceField => $targetField) {
                    $sourceValue = $source->$sourceField ?? null;
                    if ($sourceValue !== null && $sourceValue !== '') {
                        $xeoBloc->$targetField = $sourceValue;
                    }
                }
            }
        }

        $xeoBloc->save();

        $basePath = FileService::buildEntityPath($model).'/'.FileService::buildEntityPath($xeo).'/'.FileService::buildEntityPath($xeoBloc);
        $this->fileService->duplicateBlocFiles($xeoBloc, $basePath);

        $xeo->attachBloc($xeoBloc, 0);
    }
}
