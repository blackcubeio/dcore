<?php

declare(strict_types=1);

/**
 * BaseLanguage.php
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
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;

/**
 * BaseLanguage - Pure Yii3 ActiveRecord base class.
 * Contains: properties only, no relations.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseLanguage extends ActiveRecord
{
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected string $id;
    protected string $name = '';
    protected bool $main = false;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%languages}}';
    }

    #[Exportable(description: 'Language code (e.g. "fr").')]
    public function getId(): ?string
    {
        return $this->id ?? null;
    }

    #[Importable(name: 'id')]
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    #[Exportable(description: 'Display name of the language.')]
    public function getName(): string
    {
        return $this->name;
    }

    #[Importable(name: 'name')]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    #[Exportable(description: 'Default-language flag.')]
    public function isMain(): bool
    {
        return $this->main;
    }

    #[Importable(name: 'main')]
    public function setMain(bool $main): void
    {
        $this->main = $main;
    }

    #[Exportable(description: 'Availability toggle.')]
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
}
