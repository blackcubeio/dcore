<?php

declare(strict_types=1);

/**
 * ElasticSchema.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Traits\NamespaceResolverTrait;
use Blackcube\Elastic\ElasticSchema as BaseElasticSchema;
use Closure;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * ElasticSchema model for BLAMS.
 * Extends the base ElasticSchema from yii3-elastic with relations and new fields.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ElasticSchema extends BaseElasticSchema
{
    use MagicRelationsTrait;
    use NamespaceResolverTrait;

    protected int $id;
    protected string $name = '';
    protected ?string $schema = '{"type": "object", "properties": {}, "required": []}';
    protected ?string $view = null;
    protected ?string $mdMapping = null;
    protected ElasticSchemaKind $kind = ElasticSchemaKind::Common;
    protected bool $builtin = false;
    protected bool $hidden = false;
    protected int $order = 0;
    protected bool $active = true;

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

    protected function populateProperty(string $name, mixed $value): void
    {
        if ($name === 'kind' && is_string($value)) {
            $this->kind = ElasticSchemaKind::from($value);
            return;
        }
        parent::populateProperty($name, $value);
    }

    public function getMdMapping(): ?string
    {
        return $this->mdMapping;
    }

    public function setMdMapping(?string $mdMapping): void
    {
        $this->mdMapping = $mdMapping;
    }

    public function getKind(): ElasticSchemaKind
    {
        return $this->kind;
    }

    public function setKind(ElasticSchemaKind|string $kind): void
    {
        $this->kind = $kind instanceof ElasticSchemaKind ? $kind : ElasticSchemaKind::from($kind);
    }

    public function isBuiltin(): bool
    {
        return $this->builtin;
    }

    public function setBuiltin(bool $builtin): void
    {
        $this->builtin = $builtin;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    // ========================================
    // Relations
    // ========================================

    /**
     * Relation to pivot TypeElasticSchema.
     * @relation typeElasticSchemas
     */
    public function getTypeElasticSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(TypeElasticSchema::class), ['elasticSchemaId' => 'id']);
    }

    /**
     * Relation to Type via pivot (types that can use this bloc type).
     * @relation types
     */
    public function getTypesQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Type::class), ['id' => 'typeId'])
            ->via('typeElasticSchemas');
    }

    /**
     * Relation to pivot SchemaSchema (this schema as regular).
     * @relation schemasSchemas
     */
    public function getSchemasSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(SchemaSchema::class), ['regularElasticSchemaId' => 'id']);
    }

    /**
     * Relation to pivot SchemaSchema (this schema as xeo).
     * @relation xeoSchemasSchemas
     */
    public function getXeoSchemasSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(SchemaSchema::class), ['xeoElasticSchemaId' => 'id']);
    }
}
