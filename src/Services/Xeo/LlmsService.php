<?php

declare(strict_types=1);

/**
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
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
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
        $content = null;
        $projectName = ParameterHelper::get('PROJECT', 'NAME');
        if ($projectName !== null && trim($projectName) !== '') {
            $root = $this->findRoot($languageId);
            if ($root !== null) {
                $lines = ['# ' . trim($projectName)];

                $summary = ParameterHelper::get('PROJECT', 'SUMMARY');
                if ($summary !== null && trim($summary) !== '') {
                    $lines[] = '';
                    $lines[] = '> ' . trim($summary);
                }

                $lines[] = '';
                $lines[] = $this->renderTree($root, $scheme, $hostname, $full);

                $content = implode("\n", $lines);
            }
        }

        return $content;
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
        $matchedRoot = null;
        /** @var LlmMenu $root */
        foreach ($rootsQuery->each() as $root) {
            if ($fallback === null) {
                $fallback = $root;
            }
            if ($matchedRoot === null && $languageId !== null) {
                $entity = $this->linkedEntity($root);
                if ($entity !== null && $entity->getLanguageId() === $languageId) {
                    $matchedRoot = $root;
                }
            }
        }

        return $matchedRoot ?? $fallback;
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
                $lines[] = '## ' . $displayName;
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

        $line = '- [' . ($name ?? '') . '](' . $linkUrl . ')';

        $description = $node->getDescription();
        if (($description === null || trim($description) === '') && $entity !== null) {
            $description = $this->linkText($entity);
        }
        if ($description !== null && trim($description) !== '' && trim($description) !== trim($name ?? '')) {
            $line .= ': ' . trim($description);
        }

        return $line;
    }

    private function linkedEntity(LlmMenu $node): ?object
    {
        $entity = null;
        if ($node->getContentId() !== null) {
            $entity = $node->getContentQuery()->one();
        } elseif ($node->getTagId() !== null) {
            $entity = $node->getTagQuery()->one();
        }
        return $entity;
    }

    private function displayName(LlmMenu $node, ?object $entity): ?string
    {
        $displayName = null;
        $name = $node->getName();
        if ($name !== null && trim($name) !== '') {
            $displayName = $name;
        } elseif ($entity !== null) {
            $displayName = $this->linkText($entity);
        }
        return $displayName;
    }

    private function linkText(object $entity): string
    {
        $text = $entity->getName();
        $slug = $entity->getSlugQuery()->one();
        if ($slug !== null) {
            $xeo = $slug->getXeoQuery()->one();
            if ($xeo !== null) {
                $title = $xeo->getTitle();
                if ($title !== null && trim($title) !== '') {
                    $text = $title;
                }
            }
        }
        return $text;
    }

    private function buildSlugUrl(object $entity, string $scheme, string $hostname): ?string
    {
        $slugUrl = null;
        $slug = $entity->getSlugQuery()->one();
        if ($slug !== null && $slug->isActive() === true) {
            /** @var Slug $slug */
            $href = $slug->getLink()->withTemplate('host', $hostname)->getHref();
            $slugUrl = $scheme . ':' . $href . '.md';
        }
        return $slugUrl;
    }

    private function renderEntityContent(object $entity, string $scheme, string $hostname): ?string
    {
        $content = null;
        $slug = $entity->getSlugQuery()->one();
        if ($slug !== null && $slug->isActive() === true) {
            $md = $this->elasticMdService->renderMarkdown($entity, $scheme, $hostname);
            $content = $this->shiftHeadings($md);
        }
        return $content;
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
