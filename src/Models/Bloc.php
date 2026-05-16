<?php

declare(strict_types=1);

/**
 * Bloc.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\ActiveRecord\Elastic\ElasticInterface;
use Blackcube\ActiveRecord\Elastic\ElasticTrait;
use Blackcube\FormModel\Attributes\ParentProperty;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * Bloc model - Instances elastic.
 * Uses ElasticTrait from yii3-elastic for dynamic properties.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
#[ParentProperty(name: 'elasticSchemaId', type: 'int', getter: 'getElasticSchemaId', setter: 'setElasticSchemaId')]
class Bloc extends BaseBloc implements ElasticInterface
{
    use ElasticTrait;
    use ScopedQueryTrait;

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return ElasticQuery::create($modelClass ?? static::class);
    }

    /**
     * Get Elastic Schema ID.
     * Used for form <-> model mapping.
     */
    #[Exportable]
    public function getElasticSchemaId(): mixed
    {
        return $this->elasticSchemaId;
    }

    /**
     * Set Elastic Schema ID.
     * Used for form <-> model mapping.
     */
    public function setElasticSchemaId(mixed $elasticSchemaId): void
    {
        $this->elasticSchemaId = $elasticSchemaId;
    }

    /**
     * Get elastic data for export.
     *
     * @return array<string, mixed>
     */
    #[Exportable(name: 'data')]
    public function getData(): array
    {
        return $this->getElasticValues();
    }
}
