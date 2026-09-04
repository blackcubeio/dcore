<?php

declare(strict_types=1);

/**
 * BaseMenu.php
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
 * BaseMenu - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseMenu extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected string $name = '';
    protected ?int $hostId = null;
    protected string $languageId = '';
    protected ?string $route = null;
    protected ?string $queryString = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%menus}}';
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

    #[Exportable(description: 'Host scope of the entry.')]
    public function getHostId(): ?int
    {
        return $this->hostId;
    }

    #[Importable(name: 'hostId')]
    public function setHostId(?int $hostId): void
    {
        $this->hostId = $hostId;
    }

    #[Exportable(description: 'Language scope of the entry.')]
    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    #[Importable(name: 'languageId')]
    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    #[Exportable(description: 'Target route (dcore-XXX) the entry points to.')]
    public function getRoute(): ?string
    {
        return $this->route;
    }

    #[Importable(name: 'route')]
    public function setRoute(?string $route): void
    {
        $this->route = $route;
    }

    #[Exportable(description: 'Optional query string appended to the route.')]
    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    #[Importable(name: 'queryString')]
    public function setQueryString(?string $queryString): void
    {
        $this->queryString = $queryString;
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

    public function getDateCreate(): ?DateTimeImmutable
    {
        return $this->dateCreate;
    }

    public function getDateUpdate(): ?DateTimeImmutable
    {
        return $this->dateUpdate;
    }

    /**
     * Relation to Host.
     * @relation host
     */
    public function getHostQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Host::class), ['id' => 'hostId']);
    }

    /**
     * Relation to Language.
     * @relation language
     */
    public function getLanguageQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Language::class), ['id' => 'languageId']);
    }
}
