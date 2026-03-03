<?php

declare(strict_types=1);

/**
 * Xeo.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Models\Link;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Xeo as XeoModel;

final class Xeo
{
    public ?string $language = null;
    public ?Link $canonicalLink = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $image = null;
    public ?bool $noIndex = null;
    public ?bool $noFollow = null;
    public ?array $jsonLds = null;
    public ?XeoTwitter $twitter = null;
    public ?XeoOg $og = null;

    private function __construct() {}

    public static function fromSlugId(int $slugId): ?self
    {
        $slug = Slug::query()->andWhere(['id' => $slugId])->one();
        if ($slug === null) {
            return null;
        }

        $xeoModel = $slug->getXeoQuery()->one();
        if ($xeoModel === null || !$xeoModel->isActive()) {
            return null;
        }

        $xeo = new self();
        $xeo->title = $xeoModel->getTitle();
        $xeo->description = $xeoModel->getDescription();
        $xeo->image = $xeoModel->getImage();
        $xeo->noIndex = $xeoModel->isNoindex();
        $xeo->noFollow = $xeoModel->isNofollow();

        // Canonical
        if ($xeoModel->getCanonicalSlugId() !== null) {
            $canonicalSlug = Slug::query()->andWhere(['id' => $xeoModel->getCanonicalSlugId()])->one();
            if ($canonicalSlug !== null) {
                $xeo->canonicalLink = $canonicalSlug->getLink();
            }
        }

        // Open Graph
        if ($xeoModel->isOg()) {
            $xeo->og = new XeoOg();
            $xeo->og->type = $xeoModel->getOgType() ?? 'website';
        }

        // Twitter
        if ($xeoModel->isTwitter()) {
            $xeo->twitter = new XeoTwitter();
            $xeo->twitter->type = $xeoModel->getTwitterCard() ?? 'summary';
        }

        // JSON-LDs : propriétés elastic brutes de chaque XeoBloc actif
        $jsonLds = [];
        foreach ($xeoModel->getXeoBlocsQuery()->orderBy(['order' => SORT_ASC])->each() as $xeoBlocPivot) {
            $bloc = $xeoBlocPivot->getBlocQuery()->one();
            if ($bloc !== null && $bloc->isActive()) {
                $jsonLds[] = $bloc->getExtras();
            }
        }
        $xeo->jsonLds = !empty($jsonLds) ? $jsonLds : null;

        return $xeo;
    }
}
