<?php

declare(strict_types=1);

/**
 * HazeltreeSlugGenerator.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Interfaces\SlugGeneratorInterface;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Transliterator;

class HazeltreeSlugGenerator implements SlugGeneratorInterface
{
    public function getElementSlug(Content|Tag $element): string
    {
        $pathParts = [$this->urlize($element->getName())];

        $ancestorsQuery = $element->relativeQuery()
            ->parent()
            ->includeAncestors()
            ->reverse();

        foreach ($ancestorsQuery->each() as $ancestor) {
            $slug = $ancestor->getSlugQuery()->one();
            if ($slug?->getPath() !== null) {
                $finalPath = $slug->getPath() . '/' . implode('/', $pathParts);
                return $this->ensureUnique($finalPath);
            }
            array_unshift($pathParts, $this->urlize($ancestor->getName()));
        }

        $finalPath = implode('/', $pathParts);
        return $this->ensureUnique($finalPath);
    }

    private function ensureUnique(string $path): string
    {
        $existingSlugs = Slug::query()
            ->andWhere(['like', 'path', $path . '%', 'escape' => false])
            ->select(['path'])
            ->column();

        if (empty($existingSlugs)) {
            return $path;
        }

        $pattern = '/^' . preg_quote($path, '/') . '(-(\d{3}))?$/';
        $maxSuffix = -1;

        foreach ($existingSlugs as $existing) {
            if (preg_match($pattern, $existing, $matches)) {
                $suffix = isset($matches[2]) ? (int) $matches[2] : 0;
                $maxSuffix = max($maxSuffix, $suffix);
            }
        }

        if ($maxSuffix === -1) {
            return $path;
        }

        return $path . '-' . str_pad((string) ($maxSuffix + 1), 3, '0', STR_PAD_LEFT);
    }

    private function urlize(string $str): string
    {
        $transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator !== null) {
            $transliterated = $transliterator->transliterate($str);
            if ($transliterated !== false) {
                $str = $transliterated;
            }
        }

        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9]+/', '-', $str);

        return trim($str, '-');
    }
}
