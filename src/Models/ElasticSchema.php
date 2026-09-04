<?php

declare(strict_types=1);

/**
 * ElasticSchema.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\PopulatePropertyTrait;
use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Attributes\Importable;
use Blackcube\Dcore\Attributes\ResetQueryCacheOnWrite;
use Blackcube\Dcore\Traits\ScopedQueryTrait;

/**
 * ElasticSchema model for BLAMS.
 * Extends the base ElasticSchema from yii3-elastic with relations and new fields.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[Exportable(name: 'id', method: 'getId', description: 'Database id.')]
#[Exportable(name: 'name', method: 'getName', description: 'Schema name.')]
#[Exportable(name: 'schema', method: 'getSchema', description: 'The JSON Schema itself (field names, types, validation).')]
#[Exportable(name: 'view', method: 'getView', description: 'The rendering view/template bound to the schema.')]
#[Importable(name: 'name', method: 'setName')]
#[Importable(name: 'schema', method: 'setSchema')]
#[Importable(name: 'view', method: 'setView')]
#[ResetQueryCacheOnWrite]
class ElasticSchema extends BaseElasticSchema
{
    use ScopedQueryTrait;
    use PopulatePropertyTrait;
}
