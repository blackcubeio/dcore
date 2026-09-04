<?php

declare(strict_types=1);

/**
 * BaseType.php
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
use Blackcube\Dcore\Traits\ModelKindTrait;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * BaseType - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseType extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected string $name = '';
    protected ?string $handler = null;
    protected bool $contentAllowed = true;
    protected bool $tagAllowed = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%types}}';
    }

    #[Exportable(description: 'Database id.')]
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    #[Exportable(description: 'Type name.')]
    public function getName(): string
    {
        return $this->name;
    }

    #[Importable(name: 'name')]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    #[Exportable(description: 'The render handler/route this type maps to.')]
    public function getHandler(): ?string
    {
        return $this->handler;
    }

    #[Importable(name: 'handler')]
    public function setHandler(?string $handler): void
    {
        $this->handler = $handler;
    }

    #[Exportable(description: 'Whether Contents can use this type.')]
    public function isContentAllowed(): bool
    {
        return $this->contentAllowed;
    }

    #[Importable(name: 'contentAllowed')]
    public function setContentAllowed(bool $contentAllowed): void
    {
        $this->contentAllowed = $contentAllowed;
    }

    #[Exportable(description: 'Whether Tags can use this type.')]
    public function isTagAllowed(): bool
    {
        return $this->tagAllowed;
    }

    #[Importable(name: 'tagAllowed')]
    public function setTagAllowed(bool $tagAllowed): void
    {
        $this->tagAllowed = $tagAllowed;
    }

    public function getDateCreate(): ?DateTimeImmutable
    {
        return $this->dateCreate;
    }

    public function getDateUpdate(): ?DateTimeImmutable
    {
        return $this->dateUpdate;
    }

    /**
     * Relation to pivot TypeElasticSchema.
     * @relation typeElasticSchemas
     */
    public function getTypeElasticSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TypeElasticSchema::class), ['typeId' => 'id']);
    }

    /**
     * Relation to ElasticSchema via pivot (bloc types available for this type).
     * @relation elasticSchemas
     */
    #[Exportable(name: 'elasticSchemas', fields: ['id', 'name'], description: 'The ElasticSchemas this type allows.')]
    public function getElasticSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ElasticSchema::class), ['id' => 'elasticSchemaId'])
            ->via('typeElasticSchemas');
    }
}
