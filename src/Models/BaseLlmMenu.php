<?php

declare(strict_types=1);

/**
 * BaseLlmMenu.php
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
 * BaseLlmMenu - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseLlmMenu extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected string $name = '';
    protected ?string $description = null;
    protected ?int $contentId = null;
    protected ?int $tagId = null;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%llmMenus}}';
    }

    #[Exportable(description: 'Database id.')]
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function setId(int $id): void
    {
        if (isset($this->id) === true && $this->id !== $id) {
            throw new \LogicException('Cannot change ID on existing record');
        }
        $this->id = $id;
    }

    #[Exportable(description: 'Entry label.')]
    public function getName(): string
    {
        return $this->name;
    }

    #[Importable(name: 'name')]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    #[Exportable(description: 'Entry description shown in llms.txt.')]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    #[Importable(name: 'description')]
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    #[Exportable(description: 'Target Content id (set when the entry points to a Content).')]
    public function getContentId(): ?int
    {
        return $this->contentId;
    }

    #[Importable(name: 'contentId')]
    public function setContentId(?int $contentId): void
    {
        $this->contentId = $contentId;
    }

    #[Exportable(description: 'Target Tag id (set when the entry points to a Tag).')]
    public function getTagId(): ?int
    {
        return $this->tagId;
    }

    #[Importable(name: 'tagId')]
    public function setTagId(?int $tagId): void
    {
        $this->tagId = $tagId;
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
     * Relation to Content.
     * @relation content
     */
    public function getContentQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Content::class), ['id' => 'contentId']);
    }

    /**
     * Relation to Tag.
     * @relation tag
     */
    public function getTagQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Tag::class), ['id' => 'tagId']);
    }
}
