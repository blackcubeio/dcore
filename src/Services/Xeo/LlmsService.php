<?php

declare(strict_types=1);

/**
 * LlmsService.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services\Xeo;

use Blackcube\Dcore\Entities\LlmMenu;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Services\ElasticMdService;

/**
 * Generates llms.txt and llms-full.txt content from LlmMenu tree.
 * Returns null if no LlmMenu root exists.
 */
final class LlmsService
{
    public function __construct(
        private readonly ElasticMdService $elasticMdService,
    ) {}

    /**
     * Generate llms.txt — navigation with links to .md pages.
     */
    public function generate(string $scheme, string $hostname): ?string
    {
        $root = LlmMenu::query()
            ->andWhere(['level' => 1])
            ->one();

        if ($root === null) {
            return null;
        }

        return $this->generateText($root, $scheme, $hostname, false);
    }

    /**
     * Generate llms-full.txt — navigation with full markdown content inline.
     */
    public function generateFull(string $scheme, string $hostname): ?string
    {
        $root = LlmMenu::query()
            ->andWhere(['level' => 1])
            ->one();

        if ($root === null) {
            return null;
        }

        return $this->generateText($root, $scheme, $hostname, true);
    }

    private function generateText(LlmMenu $root, string $scheme, string $hostname, bool $full): string
    {
        $lines = [];

        // Level 1 — root
        $lines[] = '# ' . $root->getName();
        if ($root->getDescription() !== null && $root->getDescription() !== '') {
            $lines[] = '';
            $lines[] = '> ' . $root->getDescription();
        }

        // Level 2 — categories
        $categories = $root->relativeQuery()->children()->natural()->all();

        foreach ($categories as $category) {
            $lines[] = '';
            $lines[] = '## ' . $category->getName();
            if ($category->getDescription() !== null && $category->getDescription() !== '') {
                $lines[] = '';
                $lines[] = '> ' . $category->getDescription();
            }

            // Level 3 — entries
            $entries = $category->relativeQuery()->children()->natural()->all();
            if (!empty($entries)) {
                $lines[] = '';
            }

            foreach ($entries as $entry) {
                if ($full) {
                    $content = $this->buildEntryContent($entry, $scheme, $hostname);
                    if ($content !== null) {
                        $lines[] = $content;
                    }
                } else {
                    $line = $this->buildEntryLine($entry, $scheme, $hostname);
                    if ($line !== null) {
                        $lines[] = $line;
                    }
                }
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildEntryLine(LlmMenu $entry, string $scheme, string $hostname): ?string
    {
        $entity = null;
        if ($entry->getContentId() !== null) {
            $entity = $entry->getContentQuery()->one();
        } elseif ($entry->getTagId() !== null) {
            $entity = $entry->getTagQuery()->one();
        }

        if ($entity === null) {
            return null;
        }

        $slug = $entity->getSlugQuery()->one();
        if ($slug === null || !$slug->isActive()) {
            return null;
        }

        $xeo = $slug->getXeoQuery()->one();
        $title = $xeo !== null ? $xeo->getTitle() : null;
        $description = $xeo !== null ? $xeo->getDescription() : null;

        if ($title === null || $title === '') {
            $title = $entity->getName();
        }

        /** @var Slug $slug */
        $href = $slug->getLink()->withTemplate('host', $hostname)->getHref();
        $url = $scheme . ':' . $href . '.md';

        $line = '- [' . $title . '](' . $url . ')';
        if ($description !== null && $description !== '') {
            $line .= ': ' . $description;
        }

        return $line;
    }

    private function buildEntryContent(LlmMenu $entry, string $scheme, string $hostname): ?string
    {
        $entity = null;
        if ($entry->getContentId() !== null) {
            $entity = $entry->getContentQuery()->one();
        } elseif ($entry->getTagId() !== null) {
            $entity = $entry->getTagQuery()->one();
        }

        if ($entity === null) {
            return null;
        }

        $slug = $entity->getSlugQuery()->one();
        if ($slug === null || !$slug->isActive()) {
            return null;
        }

        $md = $this->elasticMdService->renderMarkdown($entity, $scheme, $hostname);

        return $this->shiftHeadings($md);
    }

    private function shiftHeadings(string $md): string
    {
        return preg_replace('/^(#{1,5})\s/m', '##$1 ', $md);
    }
}
