<?php

declare(strict_types=1);

/**
 * BaseElasticSchema.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Attributes\Importable;
use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Traits\ModelKindTrait;
use Blackcube\ActiveRecord\Elastic\ElasticSchema as ElasticElasticSchema;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * BaseElasticSchema - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here (EventsTrait already in parent).
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseElasticSchema extends ElasticElasticSchema
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

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

    #[Exportable(description: 'Drives the markdown import/export of the fields.')]
    public function getMdMapping(): ?string
    {
        return $this->mdMapping;
    }

    #[Importable(name: 'mdMapping')]
    public function setMdMapping(?string $mdMapping): void
    {
        $this->mdMapping = $mdMapping;
    }

    #[Exportable(description: 'common | page | bloc | xeo - where the schema applies.')]
    public function getKind(): ElasticSchemaKind
    {
        return $this->kind;
    }

    #[Importable(name: 'kind')]
    public function setKind(ElasticSchemaKind|string $kind): void
    {
        $this->kind = $kind instanceof ElasticSchemaKind ? $kind : ElasticSchemaKind::from($kind);
    }

    #[Exportable(description: 'Shipped with the system (not user-created).')]
    public function isBuiltin(): bool
    {
        return $this->builtin;
    }

    #[Importable(name: 'builtin')]
    public function setBuiltin(bool $builtin): void
    {
        $this->builtin = $builtin;
    }

    #[Exportable(description: 'Hidden from the schema picker.')]
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    #[Importable(name: 'hidden')]
    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    #[Exportable(description: 'Display order.')]
    public function getOrder(): int
    {
        return $this->order;
    }

    #[Importable(name: 'order')]
    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    #[Exportable(description: 'On/off switch.')]
    public function isActive(): bool
    {
        return $this->active;
    }

    #[Importable(name: 'active')]
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * Relation to pivot TypeElasticSchema.
     * @relation typeElasticSchemas
     */
    public function getTypeElasticSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TypeElasticSchema::class), ['elasticSchemaId' => 'id']);
    }

    /**
     * Relation to Type via pivot (types that can use this bloc type).
     * @relation types
     */
    #[Exportable(name: 'types', fields: ['id', 'name'], description: 'The Types that allow this schema.')]
    public function getTypesQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Type::class), ['id' => 'typeId'])
            ->via('typeElasticSchemas');
    }

    /**
     * Relation to pivot SchemaSchema (this schema as regular).
     * @relation schemasSchemas
     */
    public function getSchemasSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(SchemaSchema::class), ['regularElasticSchemaId' => 'id']);
    }

    /**
     * Relation to pivot SchemaSchema (this schema as xeo).
     * @relation xeoSchemasSchemas
     */
    public function getXeoSchemasSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(SchemaSchema::class), ['xeoElasticSchemaId' => 'id']);
    }
}
