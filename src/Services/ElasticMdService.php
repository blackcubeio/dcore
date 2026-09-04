<?php

declare(strict_types=1);

/**
 * ElasticMdService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Helpers\ContentHelper;
use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\FileProvider\CacheFile;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Models\TypeElasticSchema;
use Blackcube\Injector\Injector;

/**
 * Service for exporting/importing Content and Tag entities as structured markdown.
 * The markdown format uses typed bloc delimiters (:::start:type / :::end)
 * and is designed for bidirectional LLM <-> CMS exchange.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ElasticMdService
{
    private string $linkScheme = '';
    private string $linkHostname = '';

    /**
     * Export a Content or Tag as structured markdown.
     *
     * The output contains:
     * 1. YAML front matter (entity type, language, type name, user prompt)
     * 2. Bloc dictionary (mdMapping templates from the Type's allowed schemas)
     * 3. Existing blocs converted to markdown
     *
     * @param Content|Tag $model The entity to export
     * @param string $prompt User prompt for the LLM
     * @return string Complete markdown document
     * @throws \RuntimeException If model has no Type assigned
     */
    public function export(Content|Tag $model, string $prompt = ''): string
    {
        $type = $model->getTypeQuery()->one();
        if ($type === null) {
            throw new \RuntimeException('The model must have a Type assigned for the markdown export.');
        }

        $entityType = $model instanceof Content ? 'content' : 'tag';

        $langId = null;
        if ($model instanceof Content) {
            $langId = $model->getLanguageId();
        }

        $articleSchema = $this->getArticleSchema($model);
        $articleSlug = $articleSchema !== null ? strtolower($articleSchema->getName()) : null;

        $blocSchemas = $this->getAllowedBlocSchemas($type, $articleSchema);

        $blocs = [];
        /** @var Bloc $bloc */
        foreach ($model->getBlocsQuery()->each() as $bloc) {
            $blocs[] = $bloc;
        }

        $md = $this->buildFrontMatter($entityType, $langId, $type->getName(), $prompt, $articleSlug);
        $md .= $this->buildMetaPrompt($langId, $articleSlug);
        $md .= $this->buildArticleDictionary($articleSchema);
        $md .= $this->buildBlocDictionary($blocSchemas, $type->getName());
        $md .= $this->buildExistingContent($model, $articleSchema, $blocs);

        return $md;
    }

    /**
     * Render a Content or Tag as clean, public-facing markdown.
     *
     * No front matter, no LLM instructions, no :::start:/:::end delimiters.
     * File references (@blfs/) are resolved to public URLs via CacheFile.
     * Internal links are resolved to absolute URLs.
     *
     * @param Content|Tag $model The entity to render
     * @param string $scheme URL scheme (http/https)
     * @param string $hostname Host name
     * @return string Clean markdown document
     */
    public function renderMarkdown(Content|Tag $model, string $scheme, string $hostname): string
    {
        $articleSchema = $this->getArticleSchema($model);

        $md = '';

        if ($articleSchema !== null && empty($model->getElasticValues()) === false) {
            $articleMd = $this->dataToMarkdown($model->getElasticValues(), $articleSchema);
            $articleMd = $this->stripDelimiters($articleMd);
            if ($articleMd !== '') {
                $md .= $articleMd."\n\n";
            }
        }

        /** @var Bloc $bloc */
        foreach ($model->getBlocsQuery()->each() as $bloc) {
            $schema = ElasticSchema::query()
                ->andWhere(['id' => $bloc->getElasticSchemaId()])
                ->one();
            if ($schema === null || $schema->getMdMapping() === null) {
                continue;
            }
            $blocMd = $this->dataToMarkdown($bloc->getData(), $schema);
            $blocMd = $this->stripDelimiters($blocMd);
            if ($blocMd !== '') {
                $md .= $blocMd."\n\n";
            }
        }

        $md = $this->rewriteFileUrls($md, $scheme, $hostname);
        $md = $this->removeEmptyImages($md);
        $md = $this->rewriteInternalLinks($md, $scheme, $hostname);

        return trim($md)."\n";
    }

    /**
     * Strip :::start:xxx and :::end delimiter lines from rendered markdown.
     */
    private function stripDelimiters(string $md): string
    {
        $lines = explode("\n", $md);
        $filtered = array_filter($lines, fn(string $line) => str_starts_with(trim($line), ':::') === false);
        return trim(implode("\n", $filtered));
    }

    /**
     * Resolve @blfs/ file references to public CacheFile URLs.
     */
    private function rewriteFileUrls(string $md, string $scheme, string $hostname): string
    {
        return preg_replace_callback(
            '/!\[([^\]]*)\]\((@blfs\/[^)]+)\)/',
            function (array $matches) use ($scheme, $hostname): string {
                $alt = $matches[1];
                $path = $matches[2];
                $url = (string) CacheFile::from($path);
                if ($url === '') {
                    return '';
                }
                if (str_starts_with($url, 'http') === false) {
                    $url = $scheme.'://'.$hostname.$url;
                }
                return '!['.$alt.']('.$url.')';
            },
            $md
        );
    }

    /**
     * Remove image tags with empty src: ![...]()
     */
    private function removeEmptyImages(string $md): string
    {
        return preg_replace('/!\[[^\]]*\]\(\)/', '', $md);
    }

    /**
     * Resolve internal links (non-external hrefs) to absolute URLs.
     * Skips external URLs (http://, https://, //, #, mailto:, tel:).
     */
    private function rewriteInternalLinks(string $md, string $scheme, string $hostname): string
    {
        $this->linkScheme = $scheme;
        $this->linkHostname = $hostname;
        return preg_replace_callback(
            '#(?<!!)\[(?<text>[^\]]+)\]\((?<scheme>https?://|//|mailto:|tel:|\#)?(?<path>[^)]+)\)#',
            [$this, 'processInternalLink'],
            $md
        );
    }

    private function processInternalLink(array $matches): string
    {
        $href = $matches['scheme'].$matches['path'];
        if ($matches['scheme'] === '') {
            $link = Element::getLink($matches['path']);
            if ($link !== null) {
                $href = $link;
            }
            if (preg_match('#^(?:https?:)?//#', $href) !== 1) {
                $href = $this->linkScheme.'://'.$this->linkHostname.'/'.ltrim($href, '/');
            }
        }
        return '['.$matches['text'].']('.$href.')';
    }

    /**
     * Get the ElasticSchema for the article properties (Content's own elastic).
     * Returns null if the model has no elasticSchemaId or the schema has no mdMapping.
     *
     * @param Content|Tag $model
     * @return ElasticSchema|null
     */
    private function getArticleSchema(Content|Tag $model): ?ElasticSchema
    {
        $elasticSchemaId = $model->getElasticSchemaId();
        if ($elasticSchemaId === null) {
            return null;
        }

        $schema = ElasticSchema::query()
            ->andWhere(['id' => $elasticSchemaId])
            ->andWhere(['not', ['mdMapping' => null]])
            ->one();

        return $schema;
    }

    /**
     * Get allowed ElasticSchemas for blocs, excluding xeo kind and article schema.
     *
     * @param Type $type
     * @param ElasticSchema|null $articleSchema Schema to exclude (article properties)
     * @return ElasticSchema[]
     */
    private function getAllowedBlocSchemas(Type $type, ?ElasticSchema $articleSchema): array
    {
        $schemaIds = [];
        /** @var TypeElasticSchema $pivot */
        foreach ($type->getTypeElasticSchemasQuery()->each() as $pivot) {
            $schemaIds[] = $pivot->getElasticSchemaId();
        }

        if (empty($schemaIds) === true) {
            return [];
        }

        if ($articleSchema !== null) {
            $schemaIds = array_filter($schemaIds, fn($id) => $id !== $articleSchema->getId());
            if (empty($schemaIds) === true) {
                return [];
            }
        }

        $schemasQuery = ElasticSchema::query()
            ->andWhere(['id' => $schemaIds])
            ->andWhere(['not', ['mdMapping' => null]])
            ->andWhere(['!=', 'kind', ElasticSchemaKind::Xeo->value])
            ->orderBy(['order' => SORT_ASC]);

        $schemas = [];
        /** @var ElasticSchema $schema */
        foreach ($schemasQuery->each() as $schema) {
            $schemas[] = $schema;
        }

        return $schemas;
    }

    /**
     * Build YAML front matter section.
     */
    private function buildFrontMatter(string $entityType, ?string $langId, string $typeName, string $prompt, ?string $articleSlug): string
    {
        $md = "---\n";
        $md .= "entity: ".$entityType."\n";
        if ($langId !== null) {
            $md .= "lang: ".$langId."\n";
        }
        $md .= "type: ".$typeName."\n";
        if ($articleSlug !== null) {
            $md .= "article: ".$articleSlug."\n";
        }
        if ($prompt !== '') {
            $md .= "prompt: |\n";
            foreach (explode("\n", $prompt) as $line) {
                $md .= "  ".$line."\n";
            }
        }
        $md .= "---\n\n";
        return $md;
    }

    /**
     * Build the meta prompt section (LLM instructions).
     *
     * @param string|null $langId Language code for the LLM to write in
     * @param string|null $articleSlug Article delimiter name, if any
     */
    private function buildMetaPrompt(?string $langId, ?string $articleSlug): string
    {
        $md = "# Instructions\n\n";
        $md .= "You are a professional writer. You produce structured markdown for a CMS.\n\n";
        $md .= "## Format\n\n";
        $md .= "- Each bloc is delimited by `:::start:type` and `:::end`.\n";
        $md .= "- No content outside the blocs.\n";
        $md .= "- Respect the required and optional fields of the dictionary.\n\n";
        $md .= "## Rich text formatting\n\n";
        $md .= "- **bold** → `**text**`\n";
        $md .= "- *italic* → `*text*`\n";
        $md .= "- underline → `++text++`\n";
        $md .= "- strikethrough → `~~text~~`\n";
        $md .= "- link → `[text](url)`\n";
        $md .= "- list → `- item` (one per line)\n\n";
        $md .= "## Constraints\n\n";
        $md .= "- Use only the blocs defined in the dictionary.\n";
        $md .= "- Do not invent fields.\n";
        $md .= "- Images: use `![alt](description of the desired image)` — the contributor will replace it with the real file.\n";

        if ($articleSlug !== null) {
            $md .= "- The bloc `:::start:".$articleSlug."` holds the article properties. It is mandatory, unique, and always first.\n";
        }

        if ($langId !== null) {
            $md .= "- Write in language: `".$langId."`.\n";
        }

        $md .= "\nNow, fulfil the request described in the front matter (`prompt` field).\n\n";

        return $md;
    }

    /**
     * Build the article properties dictionary section.
     *
     * @param ElasticSchema|null $articleSchema
     */
    private function buildArticleDictionary(?ElasticSchema $articleSchema): string
    {
        if ($articleSchema === null) {
            return '';
        }

        $mapping = $articleSchema->getMdMapping();
        if ($mapping === null) {
            return '';
        }

        $md = "# Article properties\n\n";
        $md .= "> Mandatory article properties. Not repeatable, always first.\n";
        $md .= "> Same syntax as the blocs.\n\n";
        $md .= trim($mapping)."\n\n";

        return $md;
    }

    /**
     * Build the bloc dictionary section (templates from mdMapping).
     *
     * @param ElasticSchema[] $schemas
     * @param string $typeName
     */
    private function buildBlocDictionary(array $schemas, string $typeName): string
    {
        if (empty($schemas) === true) {
            return '';
        }

        $md = "# Bloc dictionary\n\n";
        $md .= "> Blocs available for the type \"".$typeName."\".\n";
        $md .= "> Use the `:::start:type` and `:::end` delimiters for each bloc.\n";
        $md .= "> `{field}` = required, `{?field}` = optional, `{field:rich}` = rich text, `{#field}` = dynamic heading prefix (N x #)\n\n";

        foreach ($schemas as $schema) {
            $mapping = $schema->getMdMapping();
            if ($mapping !== null) {
                $md .= trim($mapping)."\n\n";
            }
        }

        return $md;
    }

    /**
     * Build the existing content section.
     * Exports article properties first (if any), then blocs converted to markdown.
     *
     * @param Content|Tag $model The entity (for article elastic values)
     * @param ElasticSchema|null $articleSchema The article's elastic schema
     * @param Bloc[] $blocs
     */
    private function buildExistingContent(Content|Tag $model, ?ElasticSchema $articleSchema, array $blocs): string
    {
        $hasArticleData = $articleSchema !== null && empty($model->getElasticValues()) === false;
        $hasBlocs = empty($blocs) === false;

        if ($hasArticleData === false && $hasBlocs === false) {
            return '';
        }

        $md = "# Contenu actuel\n\n";

        if ($hasArticleData === true) {
            $articleMd = $this->dataToMarkdown($model->getElasticValues(), $articleSchema);
            if ($articleMd !== '') {
                $md .= $articleMd."\n\n";
            }
        }

        foreach ($blocs as $bloc) {
            $schema = ElasticSchema::query()
                ->andWhere(['id' => $bloc->getElasticSchemaId()])
                ->one();
            if ($schema === null || $schema->getMdMapping() === null) {
                continue;
            }
            $blocMd = $this->dataToMarkdown($bloc->getData(), $schema);
            if ($blocMd !== '') {
                $md .= $blocMd."\n\n";
            }
        }

        return $md;
    }

    /**
     * Convert elastic data to markdown using a schema's mdMapping template.
     *
     * Substitutes placeholders with actual elastic values:
     * - {field} -> value (keep empty string if missing)
     * - {?field} -> value or remove line if empty
     * - {field:rich} -> HTML converted to markdown
     * - {a} OR {b} -> first non-empty value
     *
     * @param array<string, mixed> $data Elastic values
     * @param ElasticSchema $schema
     * @return string Markdown for this data
     */
    private function dataToMarkdown(array $data, ElasticSchema $schema): string
    {
        $template = $schema->getMdMapping();

        $lines = explode("\n", $template);
        $result = [];

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), ':::') === true) {
                $result[] = $line;
                continue;
            }

            $processedLine = $this->processTemplateLine($line, $data);
            if ($processedLine !== null) {
                $result[] = $processedLine;
            }
        }

        $md = implode("\n", $result);

        $md = preg_replace('/\n{3,}/', "\n\n", $md);

        return trim($md);
    }

    /**
     * Process a single template line, substituting all placeholders.
     *
     * @param string $line Template line
     * @param array<string, mixed> $data Bloc elastic values
     * @return string|null Processed line, or null if line should be removed
     */
    private function processTemplateLine(string $line, array $data): ?string
    {
        $removeLine = false;

        $line = preg_replace_callback(
            '/\{(\??)(\w+)(?::rich)?\}\s+OR\s+\{(\??)(\w+)(?::rich)?\}/',
            function ($matches) use ($data, &$removeLine) {
                $field1 = $matches[2];
                $field2 = $matches[4];
                $value1 = $this->getFieldValue($data, $field1);
                $value2 = $this->getFieldValue($data, $field2);
                $value = ($value1 !== '') ? $value1 : $value2;
                if ($value === '') {
                    $removeLine = true;
                }
                return $value;
            },
            $line
        );

        $line = preg_replace_callback(
            '/\{#(\w+)\}/',
            function ($matches) use ($data) {
                $value = (int) $this->getFieldValue($data, $matches[1]);
                return $value > 0 ? str_repeat('#', $value) : '';
            },
            $line
        );

        $line = preg_replace_callback(
            '/\{(\??)(\w+)(:rich)?\}/',
            function ($matches) use ($data, &$removeLine) {
                $optional = $matches[1] === '?';
                $field = $matches[2];
                $rich = isset($matches[3]) === true && $matches[3] === ':rich';

                $value = $this->getFieldValue($data, $field);

                if ($optional && $value === '') {
                    $removeLine = true;
                    return '';
                }

                if ($value !== '' && ($rich || ContentHelper::containsKnownHtml($value))) {
                    $value = ContentHelper::htmlToMarkdown($value);
                }

                return $value;
            },
            $line
        );

        if ($removeLine === true) {
            return null;
        }

        return ContentHelper::cleanHeadingLine($line);
    }

    /**
     * Get a field value from bloc data, with safe fallback.
     *
     * @param array<string, mixed> $data
     * @param string $field
     * @return string
     */
    private function getFieldValue(array $data, string $field): string
    {
        $value = $data[$field] ?? '';
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }

    /**
     * Import structured markdown into a Content or Tag.
     * Phase 1: validates and parses (no DB writes).
     * Phase 2: persists if no blocking errors (single transaction).
     *
     * @param Content|Tag $model The target entity (must have a Type)
     * @param string $markdown The markdown to import
     * @return array{success: bool, errors: string[], warnings: string[], blocsCreated: int}
     */
    public function import(Content|Tag $model, string $markdown): array
    {
        $type = $model->getTypeQuery()->one();
        if ($type === null) {
            return [
                'success' => false,
                'errors' => ['The model must have a Type assigned.'],
                'warnings' => [],
                'blocsCreated' => 0,
            ];
        }

        $frontMatter = $this->parseFrontMatter($markdown);
        $validation = $this->validateFrontMatter($frontMatter, $model);
        $errors = $validation['errors'];
        $warnings = $validation['warnings'];

        if (empty($errors) === false) {
            return ['success' => false, 'errors' => $errors, 'warnings' => $warnings, 'blocsCreated' => 0];
        }

        $articleSchema = $this->getArticleSchema($model);
        $blocSchemas = $this->getAllowedBlocSchemas($type, $articleSchema);

        $rawBlocks = $this->extractBlocksFromMarkdown($markdown);

        $articleData = null;
        $blocsData = [];
        $articlePropertySet = false;

        foreach ($rawBlocks as $rawBlock) {
            $blockSlug = strtolower($rawBlock['type']);

            if ($articlePropertySet === false && $articleSchema !== null
                && in_array($articleSchema->getKind(), [ElasticSchemaKind::Page, ElasticSchemaKind::Common], true) === true
                && $blockSlug === strtolower($articleSchema->getName())) {
                $articleData = $this->markdownBlockToData($rawBlock['content'], $articleSchema);
                $articlePropertySet = true;
                continue;
            }

            $schema = $this->findSchemaBySlug($blockSlug, $blocSchemas, false);
            if ($schema === null) {
                $warnings[] = 'Bloc "'.$rawBlock['type'].'" unknown or not allowed for this type — ignored.';
                continue;
            }

            $blocsData[] = [
                'schemaId' => $schema->getId(),
                'data' => $this->markdownBlockToData($rawBlock['content'], $schema),
            ];
        }

        try {
            $this->persistImport($model, $articleData, $blocsData);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errors' => ['Error while persisting: '.$e->getMessage()],
                'warnings' => $warnings,
                'blocsCreated' => 0,
            ];
        }

        return [
            'success' => true,
            'errors' => [],
            'warnings' => $warnings,
            'blocsCreated' => count($blocsData),
        ];
    }

    /**
     * Extract YAML front matter from markdown.
     * Parses manually (no YAML library): each line is key: value.
     * Special case: prompt: | → indented continuation lines.
     *
     * @param string $markdown
     * @return array{entity: string, lang: string, type: string, article: string, prompt: string}
     */
    private function parseFrontMatter(string $markdown): array
    {
        $result = ['entity' => '', 'lang' => '', 'type' => '', 'article' => '', 'prompt' => ''];

        $lines = explode("\n", $markdown);
        $inFrontMatter = false;
        $inPrompt = false;
        $promptLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '---') {
                if ($inFrontMatter === false) {
                    $inFrontMatter = true;
                    continue;
                }
                if ($inPrompt && empty($promptLines) === false) {
                    $result['prompt'] = implode("\n", $promptLines);
                }
                break;
            }

            if ($inFrontMatter === false) {
                continue;
            }

            if ($inPrompt) {
                if (preg_match('/^  (.*)$/', $line, $m) === 1) {
                    $promptLines[] = $m[1];
                    continue;
                }
                $inPrompt = false;
                $result['prompt'] = implode("\n", $promptLines);
            }

            if (preg_match('/^(\w+):\s*(.*)$/', $trimmed, $m) === 1) {
                $key = $m[1];
                $value = trim($m[2]);

                if ($key === 'prompt' && $value === '|') {
                    $inPrompt = true;
                    $promptLines = [];
                    continue;
                }

                if (array_key_exists($key, $result) === true) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Validate front matter against the target model.
     *
     * @param array $frontMatter Parsed front matter
     * @param Content|Tag $model The target entity
     * @return array{errors: string[], warnings: string[]}
     */
    private function validateFrontMatter(array $frontMatter, Content|Tag $model): array
    {
        $errors = [];
        $warnings = [];

        $expectedEntity = $model instanceof Content ? 'content' : 'tag';
        if ($frontMatter['entity'] !== '' && $frontMatter['entity'] !== $expectedEntity) {
            $errors[] = 'The entity type "'.$frontMatter['entity'].'" does not match (expected: "'.$expectedEntity.'").';
        }

        $type = $model->getTypeQuery()->one();
        if ($type !== null && $frontMatter['type'] !== '' && $frontMatter['type'] !== $type->getName()) {
            $errors[] = 'The type "'.$frontMatter['type'].'" does not match the model type "'.$type->getName().'".';
        }

        if ($frontMatter['article'] !== '') {
            $elasticSchemaId = $model->getElasticSchemaId();
            if ($elasticSchemaId === null) {
                $errors[] = 'The markdown declares an article "'.$frontMatter['article'].'" but the model has no elastic schema.';
            } else {
                $schema = ElasticSchema::query()
                    ->andWhere(['id' => $elasticSchemaId])
                    ->one();
                if ($schema === null || strtolower($schema->getName()) !== strtolower($frontMatter['article'])) {
                    $schemaName = $schema !== null ? $schema->getName() : 'unknown';
                    $errors[] = 'The article schema "'.$frontMatter['article'].'" does not match the model schema "'.$schemaName.'".';
                }
            }
        }

        if ($frontMatter['lang'] !== '' && $model instanceof Content) {
            $langId = $model->getLanguageId();
            if ($langId !== null && $frontMatter['lang'] !== $langId) {
                $warnings[] = 'The language "'.$frontMatter['lang'].'" differs from the model language "'.$langId.'".';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Extract blocks from markdown content.
     * Ignores everything before the last "# Contenu actuel" (or first :::start: if absent).
     * Splits on :::start:TYPE / :::end, respecting code fences.
     *
     * @param string $markdown
     * @return array<array{type: string, content: string}>
     */
    private function extractBlocksFromMarkdown(string $markdown): array
    {
        $lines = explode("\n", $markdown);

        $lastContenuActuel = -1;
        $firstStartDelimiter = -1;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '# Contenu actuel') {
                $lastContenuActuel = $i;
            }
            if ($firstStartDelimiter === -1 && preg_match('/^:::start:\w+$/i', $trimmed) === 1) {
                $firstStartDelimiter = $i;
            }
        }

        if ($lastContenuActuel >= 0) {
            $contentStart = $lastContenuActuel + 1;
        } elseif ($firstStartDelimiter >= 0) {
            $contentStart = $firstStartDelimiter;
        } else {
            return [];
        }

        $blocks = [];
        $currentType = null;
        $currentContent = [];
        $inCodeFence = false;

        for ($i = $contentStart; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);

            if ($inCodeFence === false && str_starts_with($trimmed, '```') === true) {
                $inCodeFence = true;
            } elseif ($inCodeFence === true && $trimmed === '```') {
                $inCodeFence = false;
            }

            if ($inCodeFence === false) {
                if (preg_match('/^:::start:(\w+)$/i', $trimmed, $m) === 1) {
                    if ($currentType !== null) {
                        $blocks[] = ['type' => $currentType, 'content' => implode("\n", $currentContent)];
                    }
                    $currentType = $m[1];
                    $currentContent = [];
                    continue;
                }

                if ($trimmed === ':::end' && $currentType !== null) {
                    $blocks[] = ['type' => $currentType, 'content' => implode("\n", $currentContent)];
                    $currentType = null;
                    $currentContent = [];
                    continue;
                }
            }

            if ($currentType !== null) {
                $currentContent[] = $lines[$i];
            }
        }

        return $blocks;
    }

    /**
     * Analyze a mdMapping template to extract ordered slots.
     *
     * @param string $mdMapping The template string
     * @return array List of slot definitions
     */
    private function analyzeTemplate(string $mdMapping): array
    {
        $lines = explode("\n", $mdMapping);
        $slots = [];
        $inCodeFence = false;
        $codeFenceLanguageField = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || preg_match('/^:::/', $trimmed) === 1) {
                continue;
            }

            if ($inCodeFence === false && preg_match('/^```\{(\w+)\}$/', $trimmed, $m) === 1) {
                $inCodeFence = true;
                $codeFenceLanguageField = $m[1];
                continue;
            }

            if ($inCodeFence === true && $trimmed === '```') {
                $inCodeFence = false;
                continue;
            }

            if ($inCodeFence === true) {
                if (preg_match('/^\{(\??)(\w+)(?::rich)?\}$/', $trimmed, $m) === 1) {
                    $slots[] = ['type' => 'codefence', 'languageField' => $codeFenceLanguageField, 'codeField' => $m[2]];
                }
                continue;
            }

            if (preg_match('/^\{#(\w+)\}\s+\{(\??)(\w+)\}$/', $trimmed, $m) === 1) {
                $slots[] = ['type' => 'heading', 'field' => $m[3], 'levelField' => $m[1], 'optional' => $m[2] === '?'];
                continue;
            }

            if (preg_match('/^(#{1,6})\s+\{(\??)(\w+)\}$/', $trimmed, $m) === 1) {
                $slots[] = ['type' => 'heading', 'field' => $m[3], 'level' => strlen($m[1]), 'optional' => $m[2] === '?'];
                continue;
            }

            if (preg_match('/^!\[\{(\??)(\w+)\}\]\(\{(\??)(\w+)\}\)$/', $trimmed, $m) === 1) {
                $slots[] = ['type' => 'image', 'altField' => $m[2], 'imageField' => $m[4]];
                continue;
            }

            if (preg_match('/^\[\{(\??)(\w+)\}\]\((.+)\)$/', $trimmed, $m) === 1) {
                $labelField = $m[2];
                $urlPart = $m[3];
                $optional = $m[1] === '?';

                $urlFields = [];
                if (preg_match_all('/\{(\??)(\w+)\}/', $urlPart, $urlMatches)) {
                    foreach ($urlMatches[2] as $urlField) {
                        $urlFields[] = $urlField;
                    }
                }

                $slots[] = ['type' => 'link', 'labelField' => $labelField, 'urlFields' => $urlFields, 'optional' => $optional];
                continue;
            }

            if (preg_match('/^\{(\??)(\w+)(:rich)?\}$/', $trimmed, $m) === 1) {
                $slots[] = [
                    'type' => 'text',
                    'field' => $m[2],
                    'optional' => $m[1] === '?',
                    'rich' => isset($m[3]) === true && $m[3] === ':rich',
                ];
                continue;
            }
        }

        return $slots;
    }

    /**
     * Convert a markdown block content to elastic data using the schema's template.
     *
     * @param string $blockContent Markdown content between :::start: and :::end
     * @param ElasticSchema $schema The schema with mdMapping and JSON schema
     * @return array<string, mixed> Elastic data
     */
    private function markdownBlockToData(string $blockContent, ElasticSchema $schema): array
    {
        $slots = $this->analyzeTemplate($schema->getMdMapping());
        $lines = explode("\n", trim($blockContent));
        $data = [];
        $currentLine = 0;
        $totalLines = count($lines);

        foreach ($slots as $slotIndex => $slot) {
            switch ($slot['type']) {
                case 'heading':
                    while ($currentLine < $totalLines) {
                        $trimmed = trim($lines[$currentLine]);
                        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m) === 1) {
                            $data[$slot['field']] = trim($m[2]);
                            if (isset($slot['levelField']) === true) {
                                $data[$slot['levelField']] = strlen($m[1]);
                            }
                            $currentLine++;
                            break;
                        }
                        $currentLine++;
                    }
                    break;

                case 'image':
                    while ($currentLine < $totalLines) {
                        $trimmed = trim($lines[$currentLine]);
                        if (preg_match('/^!\[(.*)\]\((.+)\)$/', $trimmed, $m) === 1) {
                            $alt = $m[1];
                            $src = $m[2];
                            if (Injector::get(FileService::class)->canHandle($src) === true) {
                                $data[$slot['altField']] = $alt;
                                $data[$slot['imageField']] = $src;
                            } else {
                                $data[$slot['altField']] = $alt !== '' ? $alt.' | '.$src : $src;
                                $data[$slot['imageField']] = '';
                            }
                            $currentLine++;
                            break;
                        }
                        $currentLine++;
                    }
                    break;

                case 'link':
                    while ($currentLine < $totalLines) {
                        $trimmed = trim($lines[$currentLine]);
                        if (str_starts_with($trimmed, '!') === false && preg_match('/^\[(.+)\]\((.+)\)$/', $trimmed, $m) === 1) {
                            $data[$slot['labelField']] = $m[1];
                            $url = $m[2];

                            if (count($slot['urlFields']) === 1) {
                                $data[$slot['urlFields'][0]] = $url;
                            } elseif (str_starts_with($url, '/') === true && in_array('route', $slot['urlFields']) === true) {
                                foreach ($slot['urlFields'] as $field) {
                                    $data[$field] = ($field === 'route') ? $url : '';
                                }
                            } else {
                                $lastField = end($slot['urlFields']);
                                foreach ($slot['urlFields'] as $field) {
                                    $data[$field] = ($field === $lastField) ? $url : '';
                                }
                            }

                            $currentLine++;
                            break;
                        }
                        $currentLine++;
                    }
                    break;

                case 'codefence':
                    while ($currentLine < $totalLines) {
                        $trimmed = trim($lines[$currentLine]);
                        if (preg_match('/^```(\w*)$/', $trimmed, $m) === 1) {
                            $data[$slot['languageField']] = $m[1];
                            $currentLine++;
                            $codeLines = [];
                            while ($currentLine < $totalLines) {
                                if (trim($lines[$currentLine]) === '```') {
                                    $currentLine++;
                                    break;
                                }
                                $codeLines[] = $lines[$currentLine];
                                $currentLine++;
                            }
                            $data[$slot['codeField']] = implode("\n", $codeLines);
                            break;
                        }
                        $currentLine++;
                    }
                    break;

                case 'text':
                    $nextStructural = $this->findNextStructuralSlot($slots, $slotIndex + 1);
                    $textLines = [];

                    while ($currentLine < $totalLines) {
                        $trimmed = trim($lines[$currentLine]);
                        if ($nextStructural !== null && $this->lineMatchesSlotType($trimmed, $nextStructural)) {
                            break;
                        }
                        if ($slot['rich'] === false && $trimmed === '' && empty($textLines) === false) {
                            $currentLine++;
                            break;
                        }
                        $textLines[] = $lines[$currentLine];
                        $currentLine++;
                    }

                    $textValue = trim(implode("\n", $textLines));

                    if ($textValue !== '' && $this->containsMarkdownFormatting($textValue)) {
                        if ($this->isWysiwygField($schema->getSchema(), $slot['field']) === true) {
                            $textValue = ContentHelper::markdownToHtml($textValue);
                        }
                    }

                    $data[$slot['field']] = $textValue;
                    break;
            }
        }

        return $data;
    }

    /**
     * Find the next structural (non-text) slot starting from a given index.
     *
     * @param array $slots All template slots
     * @param int $fromIndex Start searching from this index
     * @return array|null The next structural slot, or null
     */
    private function findNextStructuralSlot(array $slots, int $fromIndex): ?array
    {
        for ($i = $fromIndex; $i < count($slots); $i++) {
            if ($slots[$i]['type'] !== 'text') {
                return $slots[$i];
            }
        }
        return null;
    }

    /**
     * Check if a content line matches the expected structural slot type.
     *
     * @param string $line Trimmed content line
     * @param array $slot The structural slot to match against
     * @return bool
     */
    private function lineMatchesSlotType(string $line, array $slot): bool
    {
        return match ($slot['type']) {
            'heading' => preg_match('/^#{1,6}\s+/', $line) === 1,
            'image' => preg_match('/^!\[/', $line) === 1,
            'link' => str_starts_with($line, '!') === false && preg_match('/^\[.+\]\(.+\)$/', $line) === 1,
            'codefence' => str_starts_with($line, '```') === true,
            default => false,
        };
    }

    /**
     * Check if text contains markdown formatting markers.
     *
     * @param string $text
     * @return bool
     */
    private function containsMarkdownFormatting(string $text): bool
    {
        if (str_contains($text, '**') === true) {
            return true;
        }
        if (str_contains($text, '++') === true) {
            return true;
        }
        if (str_contains($text, '~~') === true) {
            return true;
        }
        if (str_contains($text, '==') === true) {
            return true;
        }
        if (str_contains($text, '`') === true) {
            return true;
        }
        if (preg_match('/\[.+?\]\(.+?\)/', $text) === 1) {
            return true;
        }
        if (preg_match('/^- /m', $text) === 1) {
            return true;
        }
        if (preg_match('/(?<!\*)\*(?!\*)/', $text) === 1) {
            return true;
        }
        return false;
    }

    /**
     * Check if a field is defined as wysiwyg in the JSON schema.
     *
     * @param string $jsonSchemaString The JSON schema string
     * @param string $fieldName The field name to check
     * @return bool
     */
    private function isWysiwygField(string $jsonSchemaString, string $fieldName): bool
    {
        $schema = json_decode($jsonSchemaString, true);
        if ($schema === null) {
            return false;
        }

        return isset($schema['properties'][$fieldName]['format']) === true
            && $schema['properties'][$fieldName]['format'] === 'wysiwyg';
    }

    /**
     * Find a schema by slug among allowed schemas, filtered by kind.
     *
     * @param string $slug Lowercase slug to match
     * @param ElasticSchema[] $schemas Schemas to search in
     * @param bool $isArticle true = Page/Common kinds, false = Bloc/Common kinds
     * @return ElasticSchema|null
     */
    private function findSchemaBySlug(string $slug, array $schemas, bool $isArticle): ?ElasticSchema
    {
        $allowedKinds = $isArticle
            ? [ElasticSchemaKind::Page, ElasticSchemaKind::Common]
            : [ElasticSchemaKind::Bloc, ElasticSchemaKind::Common];

        foreach ($schemas as $schema) {
            if (strtolower($schema->getName()) === $slug && in_array($schema->getKind(), $allowedKinds, true) === true) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * Persist import data in a single transaction.
     * Deletes all existing blocs, sets article properties, creates new blocs.
     *
     * @param Content|Tag $model
     * @param array|null $articleData Article elastic properties
     * @param array<array{schemaId: int, data: array}> $blocsData Bloc data entries
     * @throws \Throwable
     */
    private function persistImport(Content|Tag $model, ?array $articleData, array $blocsData): void
    {
        $transaction = $model->db()->beginTransaction();

        try {
            /** @var Bloc $bloc */
            foreach ($model->getBlocsQuery()->each() as $bloc) {
                $model->detachBloc($bloc);
            }

            if ($articleData !== null) {
                foreach ($articleData as $prop => $value) {
                    $model->$prop = $value;
                }
                $model->save();
            }

            foreach ($blocsData as $blocEntry) {
                $bloc = new Bloc();
                $bloc->setElasticSchemaId($blocEntry['schemaId']);
                $bloc->setActive(true);
                foreach ($blocEntry['data'] as $prop => $value) {
                    $bloc->$prop = $value;
                }
                $bloc->save();
                $model->attachBloc($bloc);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
