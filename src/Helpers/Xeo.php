<?php

declare(strict_types=1);

/**
 * Xeo.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Models\BaseContent;
use Blackcube\Dcore\Models\Link;

final class Xeo
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

    public static function fromSlugId(int $slugId): ?self
    {
        $slug = Slug::query()->andWhere(['id' => $slugId])->one();
        if ($slug === null) {
            return null;
        }

        $xeo = new self();

        // SEO metadata (optional — xeo model may not exist or be inactive)
        $xeoModel = $slug->getXeoQuery()->one();
        if ($xeoModel !== null && $xeoModel->isActive()) {
            $xeo->title = $xeoModel->getTitle();
            $xeo->description = $xeoModel->getDescription();
            $xeo->image = $xeoModel->getImage();
            $xeo->noIndex = $xeoModel->isNoindex();
            $xeo->noFollow = $xeoModel->isNofollow();
            $xeo->keywords = $xeoModel->getKeywords();

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
        }

        // Language & hreflang alternates (independent of xeo model)
        $element = $slug->getElement();
        if ($element instanceof BaseContent && $element->getLanguageId() !== null) {
            $xeo->language = $element->getLanguageId();
            // Self hreflang
            $xeo->alternates[] = [
                'language' => $element->getLanguageId(),
                'link' => $slug->getLink(),
            ];
            // Translations hreflang
            $translations = $element->getTranslationsQuery()->all();
            foreach ($translations as $translation) {
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

        // JSON-LDs built by JsonLdBuilderInterface in SSR layer
        $xeo->jsonLds = null;

        return $xeo;
    }
}
