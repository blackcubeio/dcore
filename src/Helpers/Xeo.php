<?php

declare(strict_types=1);

/**
 * Xeo.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Models\BaseContent;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Link;
use Blackcube\Dcore\Models\Tag;

class Xeo
{
    public ?string $language = null;
    /** @var array<array{language: string, link: Link}> */
    public array $alternates = [];
    public ?Link $canonicalLink = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $image = null;
    public ?bool $noIndex = null;
    public ?bool $noFollow = null;
    public ?string $keywords = null;
    public ?array $jsonLds = null;
    public ?XeoTwitter $twitter = null;
    public ?XeoOg $og = null;

    private function __construct() {}

    public static function from(int|Content|Tag $slugIdOrElement): ?self
    {
        return is_int($slugIdOrElement) === true
            ? self::buildFromSlugId($slugIdOrElement)
            : self::buildFromElement($slugIdOrElement);
    }

    private static function buildFromElement(Content|Tag $element): self
    {
        $xeo = new self();
        $xeo->language = $element->getLanguageId();
        return $xeo;
    }

    private static function buildFromSlugId(int $slugId): ?self
    {
        $slug = Slug::query()->andWhere(['id' => $slugId])->one();
        if ($slug === null) {
            return null;
        }

        $xeo = new self();

        $xeoModel = $slug->getXeoQuery()->one();
        if ($xeoModel !== null && $xeoModel->isActive() === true) {
            $xeo->title = $xeoModel->getTitle();
            $xeo->description = $xeoModel->getDescription();
            $xeo->image = $xeoModel->getImage();
            $xeo->noIndex = $xeoModel->isNoindex();
            $xeo->noFollow = $xeoModel->isNofollow();
            $xeo->keywords = $xeoModel->getKeywords();

            if ($xeoModel->getCanonicalSlugId() !== null) {
                $canonicalSlug = Slug::query()->andWhere(['id' => $xeoModel->getCanonicalSlugId()])->one();
                if ($canonicalSlug !== null) {
                    $xeo->canonicalLink = $canonicalSlug->getLink();
                }
            }

            if ($xeoModel->isOg() === true) {
                $xeo->og = new XeoOg();
                $xeo->og->type = $xeoModel->getOgType() ?? 'website';
            }

            if ($xeoModel->isTwitter() === true) {
                $xeo->twitter = new XeoTwitter();
                $xeo->twitter->type = $xeoModel->getTwitterCard() ?? 'summary';
            }
        }

        $element = $slug->getElement();
        if ($element instanceof BaseContent && $element->getLanguageId() !== null) {
            $xeo->language = $element->getLanguageId();
            $xeo->alternates[] = [
                'language' => $element->getLanguageId(),
                'link' => $slug->getLink(),
            ];
            $translationsQuery = $element->getTranslationsQuery();
            /** @var Content $translation */
            foreach ($translationsQuery->each() as $translation) {
                if ($translation->getLanguageId() !== null && $translation->getSlugId() !== null) {
                    $translationSlug = Slug::query()->andWhere(['id' => $translation->getSlugId()])->one();
                    if ($translationSlug !== null) {
                        $xeo->alternates[] = [
                            'language' => $translation->getLanguageId(),
                            'link' => $translationSlug->getLink(),
                        ];
                    }
                }
            }
        }

        $xeo->jsonLds = null;

        return $xeo;
    }
}
