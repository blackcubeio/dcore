<?php

declare(strict_types=1);

/**
 * ExportImportProbe.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\ExportImport;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Attributes\Importable;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;

/**
 * Probe ActiveRecord persisted in {{%exportImportProbes}}. AR columns are protected
 * (Yii3 mapping), so annotations go on the accessors (method-level) or on the class
 * (targeting an accessor by `method`). Proves both paths through a real DB round-trip.
 *
 * - getLabel/setLabel: method-level Exportable/Importable (key 'label').
 * - class-level Exportable/Importable targeting getScore/setScore by `method` (key 'score').
 */
#[Exportable(method: 'getScore')]
#[Importable(method: 'setScore')]
class ExportImportProbe extends ActiveRecord
{
    use MagicPropertiesTrait;

    protected int $id;
    protected string $label = '';
    protected int $score = 0;

    public function tableName(): string
    {
        return '{{%exportImportProbes}}';
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    #[Exportable]
    public function getLabel(): string
    {
        return $this->label;
    }

    #[Importable]
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }
}
