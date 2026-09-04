<?php

declare(strict_types=1);

/**
 * BaseParameter.php
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
 * BaseParameter - Pure Yii3 ActiveRecord base class.
 * Contains: properties only, no relations.
 * Composite primary key (domain, name).
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseParameter extends ActiveRecord
{
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected string $domain = '';
    protected string $name = '';
    protected ?string $value = null;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%parameters}}';
    }

    #[Exportable(description: 'Setting domain (e.g. PROJECT).')]
    public function getDomain(): string
    {
        return $this->domain;
    }

    #[Importable(name: 'domain')]
    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    #[Exportable(description: 'Setting name within the domain (e.g. NAME).')]
    public function getName(): string
    {
        return $this->name;
    }

    #[Importable(name: 'name')]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    #[Exportable(description: 'Setting value.')]
    public function getValue(): ?string
    {
        return $this->value;
    }

    #[Importable(name: 'value')]
    public function setValue(?string $value): void
    {
        $this->value = $value;
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
