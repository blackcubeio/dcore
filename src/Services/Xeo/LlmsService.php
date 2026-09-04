<?php

declare(strict_types=1);

/**
 * LlmsService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services\Xeo;

use Blackcube\Dcore\Entities\LlmMenu;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Helpers\ParameterHelper;
use Blackcube\Dcore\Services\ElasticMdService;

/**
 * Generates llms.txt and llms-full.txt content from LlmMenu tree.
 *
 * Rendering rules per node:
 * - Level 1 or 2 (Root / SubLevel2):
 *     #(level) <displayName>
 *     > <description>                                  (when description is set)
 *     - [<content.title ?? content.name>](<slug.md>)   (when a linked content/tag has an active slug)
 *   <displayName> = node.name, with fallback to content.title ?? content.name when trim(name) is empty.
 *
 * - Level 3+ (Final, content/tag is the point of the node):
 *     - [<node.name>](<slug.md>)                       (link only)
 *     > <description>                                  (when description is set)
 *
 * Full mode appends the inlined markdown of the linked content/tag after the node's lines.
 *
 * Returns null when no root LlmMenu exists.
 */
class LlmsService
{
    public function __construct(
        private readonly ElasticMdService $elasticMdService,
    ) {}

    /**
     * Generate llms.txt — navigation with links to .md pages.
     */
    public function generate(string $scheme, string $hostname, ?string $languageId = null): ?string
    {
        return $this->generateAll($scheme, $hostname, false, $languageId);
    }

    /**
     * Generate llms-full.txt — navigation with full markdown content inlined per node.
     */
    public function generateFull(string $scheme, string $hostname, ?string $languageId = null): ?string
    {
        return $this->generateAll($scheme, $hostname, true, $languageId);
    }

    private function generateAll(string $scheme, string $hostname, bool $full, ?string $languageId): ?string
    {
        $projectName = ParameterHelper::get('PROJECT', 'NAME');
        if ($projectName === null || trim($projectName) === '') {
            return null;
        }

        $root = $this->findRoot($languageId);
        if ($root === null) {
            return null;
        }

        $lines = ['# '.trim($projectName)];

        $summary = ParameterHelper::get('PROJECT', 'SUMMARY');
        if ($summary !== null && trim($summary) !== '') {
            $lines[] = '';
            $lines[] = '> '.trim($summary);
        }

        $lines[] = '';
        $lines[] = $this->renderTree($root, $scheme, $hostname, $full);

        return implode("\n", $lines);
    }

    /**
     * Select the single level-1 root for the current language.
     *
     * A root carries no languageId of its own — its language comes from the linked
     * content/tag. Falls back to the first root (natural order) when no linked entity
     * matches the locale, mirroring App\Handlers\RedirectLangHandler.
     */
    private function findRoot(?string $languageId): ?LlmMenu
    {
        $rootsQuery = LlmMenu::query()
            ->andWhere(['level' => 1])
            ->natural();

        $fallback = null;
        /** @var LlmMenu $root */
        foreach ($rootsQuery->each() as $root) {
            if ($fallback === null) {
                $fallback = $root;
            }
            if ($languageId === null) {
                continue;
            }
            $entity = $this->linkedEntity($root);
            if ($entity !== null && $entity->getLanguageId() === $languageId) {
                return $root;
            }
        }

        return $fallback;
    }

    private function renderTree(LlmMenu $root, string $scheme, string $hostname, bool $full): string
    {
        $lines = [];

        $treeQuery = $root->relativeQuery()
            ->children()
            ->includeDescendants()
            ->includeSelf()
            ->natural();

        /** @var LlmMenu $node */
        foreach ($treeQuery->each() as $node) {
            $nodeLines = $this->renderNode($node, $scheme, $hostname, $full);
            if (empty($nodeLines) === false) {
                if (empty($lines) === false && ($full === true || str_starts_with($nodeLines[0], '#') === true)) {
                    $lines[] = '';
                }
                foreach ($nodeLines as $line) {
                    $lines[] = $line;
                }
            }
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * @return string[]
     */
    private function renderNode(LlmMenu $node, string $scheme, string $hostname, bool $full): array
    {
        $lines = [];

        $entity = $this->linkedEntity($node);
        $linkUrl = $entity !== null ? $this->buildSlugUrl($entity, $scheme, $hostname) : null;
        $level = $node->getLevel();

        if ($level === 2) {
            $displayName = $this->displayName($node, $entity);
            if ($displayName !== null && $displayName !== '') {
                $lines[] = '## '.$displayName;
            }
        }

        if ($linkUrl !== null) {
            $lines[] = $this->renderLink($node, $entity, $linkUrl);
        }

        if ($full === true && $entity !== null) {
            $inlined = $this->renderEntityContent($entity, $scheme, $hostname);
            if ($inlined !== null) {
                if (empty($lines) === false) {
                    $lines[] = '';
                }
                $lines[] = $inlined;
            }
        }

        return $lines;
    }

    /**
     * Render a node as `- [name](url): description`.
     *
     * Name is the menu node's name (falling back to the linked entity's title).
     * Description is the node description, falling back to the linked entity's title/slogan;
     * omitted when empty or identical to the name.
     */
    private function renderLink(LlmMenu $node, ?object $entity, string $linkUrl): string
    {
        $name = $node->getName();
        if (($name === null || trim($name) === '') && $entity !== null) {
            $name = $this->linkText($entity);
        }

        $line = '- ['.($name ?? '').']('.$linkUrl.')';

        $description = $node->getDescription();
        if (($description === null || trim($description) === '') && $entity !== null) {
            $description = $this->linkText($entity);
        }
        if ($description !== null && trim($description) !== '' && trim($description) !== trim($name ?? '')) {
            $line .= ': '.trim($description);
        }

        return $line;
    }

    private function linkedEntity(LlmMenu $node): ?object
    {
        if ($node->getContentId() !== null) {
            return $node->getContentQuery()->one();
        }
        if ($node->getTagId() !== null) {
            return $node->getTagQuery()->one();
        }
        return null;
    }

    private function displayName(LlmMenu $node, ?object $entity): ?string
    {
        $name = $node->getName();
        if ($name !== null && trim($name) !== '') {
            return $name;
        }
        if ($entity !== null) {
            return $this->linkText($entity);
        }
        return null;
    }

    private function linkText(object $entity): string
    {
        $slug = $entity->getSlugQuery()->one();
        if ($slug !== null) {
            $xeo = $slug->getXeoQuery()->one();
            if ($xeo !== null) {
                $title = $xeo->getTitle();
                if ($title !== null && trim($title) !== '') {
                    return $title;
                }
            }
        }
        return $entity->getName();
    }

    private function buildSlugUrl(object $entity, string $scheme, string $hostname): ?string
    {
        $slug = $entity->getSlugQuery()->one();
        if ($slug === null || $slug->isActive() === false) {
            return null;
        }

        /** @var Slug $slug */
        $href = $slug->getLink()->withTemplate('host', $hostname)->getHref();
        return $scheme.':'.$href.'.md';
    }

    private function renderEntityContent(object $entity, string $scheme, string $hostname): ?string
    {
        $slug = $entity->getSlugQuery()->one();
        if ($slug === null || $slug->isActive() === false) {
            return null;
        }

        $md = $this->elasticMdService->renderMarkdown($entity, $scheme, $hostname);
        return $this->shiftHeadings($md);
    }

    private function shiftHeadings(string $md): string
    {
        $shifted = preg_replace('/^(#{1,5})\s/m', '##$1 ', $md);

        $lines = [];
        $skipping = false;
        foreach (explode("\n", $shifted) as $line) {
            if (preg_match('/^(#+)\s/', $line, $matches) === 1) {
                $skipping = strlen($matches[1]) > 6;
                if ($skipping === true) {
                    continue;
                }
                $lines[] = $line;
                continue;
            }
            if ($skipping === true) {
                continue;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
