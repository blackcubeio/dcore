<?php

declare(strict_types=1);

/**
 * Bloc.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Elastic\ElasticInterface;
use Blackcube\Elastic\ElasticTrait;
use Blackcube\FormModel\Attributes\ParentProperty;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
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
    use MagicComposeActiveRecordTrait;
    use ElasticTrait;

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
     * Override ElasticTrait::query() to use ScopedQuery.
     */
    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
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
