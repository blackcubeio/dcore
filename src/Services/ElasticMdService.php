<?php

declare(strict_types=1);

/**
 * ElasticMdService.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\FileProvider\CacheFile;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\Type;

/**
 * Service for exporting/importing Content and Tag entities as structured markdown.
 * The markdown format uses typed bloc delimiters (:::start:type / :::end)
 * and is designed for bidirectional LLM <-> CMS exchange.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class ElasticMdService
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
            throw new \RuntimeException('Le modèle doit avoir un Type assigné pour l\'export markdown.');
        }

        $entityType = $model instanceof Content ? 'content' : 'tag';

        // Get language (Content only — Tag has no languageId)
        $langId = null;
        if ($model instanceof Content) {
            $langId = $model->getLanguageId();
        }

        // Get article schema (Content's own elastic, if any)
        $articleSchema = $this->getArticleSchema($model);
        $articleSlug = $articleSchema !== null ? strtolower($articleSchema->getName()) : null;

        // Get allowed bloc schemas (excluding article schema and xeo)
        $blocSchemas = $this->getAllowedBlocSchemas($type, $articleSchema);

        // Get existing blocs
        $blocs = $model->getBlocsQuery()->all();

        // Build markdown
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
        $blocs = $model->getBlocsQuery()->all();

        $md = '';

        // Article properties (content's own elastic schema)
        if ($articleSchema !== null && !empty($model->getElasticValues())) {
            $articleMd = $this->dataToMarkdown($model->getElasticValues(), $articleSchema);
            $articleMd = $this->stripDelimiters($articleMd);
            if ($articleMd !== '') {
                $md .= $articleMd . "\n\n";
            }
        }

        // Blocs (ordered)
        foreach ($blocs as $bloc) {
            $schema = ElasticSchema::query()
                ->andWhere(['id' => $bloc->getElasticSchemaId()])
                ->one();
            if ($schema === null || $schema->getMdMapping() === null) {
                continue;
            }
            $blocMd = $this->dataToMarkdown($bloc->getData(), $schema);
            $blocMd = $this->stripDelimiters($blocMd);
            if ($blocMd !== '') {
                $md .= $blocMd . "\n\n";
            }
        }

        // Post-process: resolve file and route URLs
        $md = $this->resolveFileUrls($md, $scheme, $hostname);
        $md = $this->removeEmptyImages($md);
        $md = $this->resolveInternalLinks($md, $scheme, $hostname);

        return trim($md) . "\n";
    }

    /**
     * Strip :::start:xxx and :::end delimiter lines from rendered markdown.
     */
    private function stripDelimiters(string $md): string
    {
        $lines = explode("\n", $md);
        $filtered = array_filter($lines, fn(string $line) => !str_starts_with(trim($line), ':::'));
        return trim(implode("\n", $filtered));
    }

    /**
     * Resolve @blfs/ file references to public CacheFile URLs.
     */
    private function resolveFileUrls(string $md, string $scheme, string $hostname): string
    {
        return preg_replace_callback(
            '/!\[([^\]]*)\]\((@blfs\/[^)]+)\)/',
            function (array $matches) use ($scheme, $hostname): string {
                $alt = $matches[1];
                $path = $matches[2];
                $url = (string) CacheFile::from($path);
                if ($url === '') {
                    // File does not exist — remove the image entirely
                    return '';
                }
                if (!str_starts_with($url, 'http')) {
                    $url = $scheme . '://' . $hostname . $url;
                }
                return '![' . $alt . '](' . $url . ')';
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
    private function resolveInternalLinks(string $md, string $scheme, string $hostname): string
    {
        $this->linkScheme = $scheme;
        $this->linkHostname = $hostname;
        return preg_replace_callback(
            '/(?<!!)\[(?<text>[^\]]+)\]\((?<scheme>https?://|//|mailto:|tel:|\#)?(?<path>[^)]+)\)/',
            [$this, 'processInternalLink'],
            $md
        );
    }

    private function processInternalLink(array $matches): string
    {
        $href = $matches['scheme'] . $matches['path'];
        if ($matches['scheme'] === '') {
            $resolved = Element::getLink($matches['path']);
            if ($resolved !== null) {
                $href = $resolved;
            }
            if (preg_match('#^(?:https?:)?//#', $href) !== 1) {
                $href = $this->linkScheme . '://' . $this->linkHostname . '/' . ltrim($href, '/');
            }
        }
        return '[' . $matches['text'] . '](' . $href . ')';
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
        // Get schema IDs from pivot
        $schemaIds = [];
        foreach ($type->getTypeElasticSchemasQuery()->each() as $pivot) {
            $schemaIds[] = $pivot->getElasticSchemaId();
        }

        if (empty($schemaIds)) {
            return [];
        }

        // Exclude article schema from bloc list
        if ($articleSchema !== null) {
            $schemaIds = array_filter($schemaIds, fn($id) => $id !== $articleSchema->getId());
            if (empty($schemaIds)) {
                return [];
            }
        }

        return ElasticSchema::query()
            ->andWhere(['id' => $schemaIds])
            ->andWhere(['not', ['mdMapping' => null]])
            ->andWhere(['!=', 'kind', ElasticSchemaKind::Xeo->value])
            ->orderBy(['order' => SORT_ASC])
            ->all();
    }

    /**
     * Build YAML front matter section.
     */
    private function buildFrontMatter(string $entityType, ?string $langId, string $typeName, string $prompt, ?string $articleSlug): string
    {
        $md = "---\n";
        $md .= "entity: " . $entityType . "\n";
        if ($langId !== null) {
            $md .= "lang: " . $langId . "\n";
        }
        $md .= "type: " . $typeName . "\n";
        if ($articleSlug !== null) {
            $md .= "article: " . $articleSlug . "\n";
        }
        if ($prompt !== '') {
            $md .= "prompt: |\n";
            foreach (explode("\n", $prompt) as $line) {
                $md .= "  " . $line . "\n";
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
        $md .= "Tu es un rédacteur professionnel. Tu produis du markdown structuré pour un CMS.\n\n";
        $md .= "## Format\n\n";
        $md .= "- Chaque bloc est délimité par `:::start:type` et `:::end`.\n";
        $md .= "- Aucun contenu en dehors des blocs.\n";
        $md .= "- Respecte les champs requis et optionnels du dictionnaire.\n\n";
        $md .= "## Formatage rich text\n\n";
        $md .= "- **gras** → `**texte**`\n";
        $md .= "- *italique* → `*texte*`\n";
        $md .= "- souligné → `++texte++`\n";
        $md .= "- barré → `~~texte~~`\n";
        $md .= "- lien → `[texte](url)`\n";
        $md .= "- liste → `- élément` (un par ligne)\n\n";
        $md .= "## Contraintes\n\n";
        $md .= "- N'utilise que les blocs définis dans le dictionnaire.\n";
        $md .= "- N'invente pas de champs.\n";
        $md .= "- Les images : utilise `![alt](description de l'image souhaitée)` — le contributeur remplacera par le vrai fichier.\n";

        if ($articleSlug !== null) {
            $md .= "- Le bloc `:::start:" . $articleSlug . "` contient les propriétés de l'article. Il est obligatoire, unique, et toujours en premier.\n";
        }

        if ($langId !== null) {
            $md .= "- Rédige en langue : `" . $langId . "`.\n";
        }

        $md .= "\nMaintenant, réalise la demande décrite dans le front matter (champ `prompt`).\n\n";

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

        $md = "# Propriétés de l'article\n\n";
        $md .= "> Propriétés obligatoires de l'article. Non répétable, toujours en tête.\n";
        $md .= "> Même syntaxe que les blocs.\n\n";
        $md .= trim($mapping) . "\n\n";

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
        if (empty($schemas)) {
            return '';
        }

        $md = "# Dictionnaire des blocs\n\n";
        $md .= "> Blocs disponibles pour le type \"" . $typeName . "\".\n";
        $md .= "> Utilisez les délimiteurs `:::start:type` et `:::end` pour chaque bloc.\n";
        $md .= "> `{field}` = requis, `{?field}` = optionnel, `{field:rich}` = texte riche, `{#field}` = préfixe de heading dynamique (N x #)\n\n";

        foreach ($schemas as $schema) {
            $mapping = $schema->getMdMapping();
            if ($mapping !== null) {
                $md .= trim($mapping) . "\n\n";
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
        $hasArticleData = $articleSchema !== null && !empty($model->getElasticValues());
        $hasBlocs = !empty($blocs);

        if (!$hasArticleData && !$hasBlocs) {
            return '';
        }

        $md = "# Contenu actuel\n\n";

        // Article properties first
        if ($hasArticleData) {
            $articleMd = $this->dataToMarkdown($model->getElasticValues(), $articleSchema);
            if ($articleMd !== '') {
                $md .= $articleMd . "\n\n";
            }
        }

        // Then blocs
        foreach ($blocs as $bloc) {
            $schema = ElasticSchema::query()
                ->andWhere(['id' => $bloc->getElasticSchemaId()])
                ->one();
            if ($schema === null || $schema->getMdMapping() === null) {
                continue;
            }
            $blocMd = $this->dataToMarkdown($bloc->getData(), $schema);
            if ($blocMd !== '') {
                $md .= $blocMd . "\n\n";
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
            // Keep delimiters as-is
            if (str_starts_with(trim($line), ':::')) {
                $result[] = $line;
                continue;
            }

            $processedLine = $this->processTemplateLine($line, $data);
            if ($processedLine !== null) {
                $result[] = $processedLine;
            }
            // null = line removed (optional field was empty)
        }

        $md = implode("\n", $result);

        // Clean up: max 2 consecutive blank lines
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

        // Handle OR patterns first: {fieldA} OR {fieldB}
        // Replace them with the first non-empty value
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

        // Handle dynamic heading prefix: {#field} -> str_repeat('#', (int) $data[field])
        $line = preg_replace_callback(
            '/\{#(\w+)\}/',
            function ($matches) use ($data) {
                $value = (int) $this->getFieldValue($data, $matches[1]);
                return $value > 0 ? str_repeat('#', $value) : '';
            },
            $line
        );

        // Handle remaining placeholders: {?field:rich}, {field:rich}, {?field}, {field}
        $line = preg_replace_callback(
            '/\{(\??)(\w+)(:rich)?\}/',
            function ($matches) use ($data, &$removeLine) {
                $optional = $matches[1] === '?';
                $field = $matches[2];
                $rich = isset($matches[3]) && $matches[3] === ':rich';

                $value = $this->getFieldValue($data, $field);

                if ($optional && $value === '') {
                    $removeLine = true;
                    return '';
                }

                if ($value !== '' && ($rich || $this->containsKnownHtml($value))) {
                    $value = $this->htmlToMarkdown($value);
                }

                return $value;
            },
            $line
        );

        if ($removeLine) {
            return null;
        }

        return $line;
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
     * Detect inline HTML tags supported by htmlToMarkdown.
     * Used to convert Quill-formatted values even when the mdMapping placeholder
     * is not explicitly marked :rich.
     */
    private function containsKnownHtml(string $value): bool
    {
        return (bool) preg_match('#<(?:p|strong|em|u|s|a|ul|ol|li|br|mark|code|del|ins)\b[^>]*>#i', $value);
    }

    private function normalizeQuillListBlock(array $matches): string
    {
        $tag = $matches['type'] === 'bullet' ? 'ul' : 'ol';
        $inner = preg_replace('# data-list="[^"]*"#', '', $matches[1]);
        return '<' . $tag . '>' . $inner . '</' . $tag . '>';
    }

    /**
     * Convert limited HTML (from rich text editor) to markdown.
     *
     * Supported tags: <p>, <strong>, <em>, <u>, <s>, <a>, <ul>, <ol>, <li>,
     * <mark>, <code>, <br>, <del>, <ins>.
     *
     * @param string $html
     * @return string Markdown text
     */
    private function htmlToMarkdown(string $html): string
    {
        $md = $html;

        $md = preg_replace('#<span class="ql-ui"[^>]*></span>#', '', $md);
        $md = preg_replace_callback(
            '#<ol>(\s*<li\s+data-list="(?<type>bullet|ordered)"[^>]*>.*?)</ol>#s',
            [$this, 'normalizeQuillListBlock'],
            $md
        );

        // <p> -> content + double newline
        $md = preg_replace('/<p>(.*?)<\/p>/s', "$1\n\n", $md);

        // <strong> -> **...**
        $md = preg_replace('/<strong>(.*?)<\/strong>/s', '**$1**', $md);

        // <em> -> *...*
        $md = preg_replace('/<em>(.*?)<\/em>/s', '*$1*', $md);

        // <u> / <ins> -> ++...++
        $md = preg_replace('/<u>(.*?)<\/u>/s', '++$1++', $md);
        $md = preg_replace('/<ins>(.*?)<\/ins>/s', '++$1++', $md);

        // <s> / <del> -> ~~...~~
        $md = preg_replace('/<s>(.*?)<\/s>/s', '~~$1~~', $md);
        $md = preg_replace('/<del>(.*?)<\/del>/s', '~~$1~~', $md);

        // <mark> -> ==...==
        $md = preg_replace('/<mark>(.*?)<\/mark>/s', '==$1==', $md);

        // <code> -> `...`
        $md = preg_replace('/<code>(.*?)<\/code>/s', '`$1`', $md);

        // <br> -> two-space + newline (markdown line break)
        $md = preg_replace('/<br\s*\/?>/i', "  \n", $md);

        // <a href="url">text</a> -> [text](url)
        $md = preg_replace('/<a\s+href="([^"]*)"[^>]*>(.*?)<\/a>/s', '[$2]($1)', $md);

        // <li> -> - item
        $md = preg_replace('/<li>(.*?)<\/li>/s', "- $1\n", $md);

        // Remove <ul>, <ol> wrappers
        $md = preg_replace('/<\/?(?:ul|ol)>/s', '', $md);

        // Strip remaining tags
        $md = strip_tags($md);

        // Clean up: max 2 consecutive blank lines, trim trailing spaces per line
        $md = preg_replace('/\n{3,}/', "\n\n", $md);
        $md = preg_replace('/[ \t]+\n/', "\n", $md);

        return trim($md);
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
            return ['success' => false, 'errors' => ['Le modèle doit avoir un Type assigné.'], 'warnings' => [], 'blocsCreated' => 0];
        }

        // Phase 1: Parse and validate front matter
        $frontMatter = $this->parseFrontMatter($markdown);
        $validation = $this->validateFrontMatter($frontMatter, $model);
        $errors = $validation['errors'];
        $warnings = $validation['warnings'];

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'warnings' => $warnings, 'blocsCreated' => 0];
        }

        // Get schemas
        $articleSchema = $this->getArticleSchema($model);
        $blocSchemas = $this->getAllowedBlocSchemas($type, $articleSchema);

        // Extract blocks from markdown
        $rawBlocks = $this->extractBlocksFromMarkdown($markdown);

        // Parse each block
        $articleData = null;
        $blocsData = [];
        $articlePropertySet = false;

        foreach ($rawBlocks as $rawBlock) {
            $blockSlug = strtolower($rawBlock['type']);

            // Check if this is the article block (content/tag elastic properties)
            if (!$articlePropertySet && $articleSchema !== null
                && in_array($articleSchema->getKind(), [ElasticSchemaKind::Page, ElasticSchemaKind::Common], true)
                && $blockSlug === strtolower($articleSchema->getName())) {
                $articleData = $this->markdownBlockToData($rawBlock['content'], $articleSchema);
                $articlePropertySet = true;
                continue;
            }

            // Regular bloc
            $schema = $this->findSchemaBySlug($blockSlug, $blocSchemas, false);
            if ($schema === null) {
                $warnings[] = 'Bloc "' . $rawBlock['type'] . '" inconnu ou non autorisé pour ce type — ignoré.';
                continue;
            }

            $blocsData[] = [
                'schemaId' => $schema->getId(),
                'data' => $this->markdownBlockToData($rawBlock['content'], $schema),
            ];
        }

        // Phase 2: Persist
        try {
            $this->persistImport($model, $articleData, $blocsData);
        } catch (\Throwable $e) {
            return ['success' => false, 'errors' => ['Erreur lors de la persistance : ' . $e->getMessage()], 'warnings' => $warnings, 'blocsCreated' => 0];
        }

        return ['success' => true, 'errors' => [], 'warnings' => $warnings, 'blocsCreated' => count($blocsData)];
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
                if (!$inFrontMatter) {
                    $inFrontMatter = true;
                    continue;
                }
                // End of front matter
                if ($inPrompt && !empty($promptLines)) {
                    $result['prompt'] = implode("\n", $promptLines);
                }
                break;
            }

            if (!$inFrontMatter) {
                continue;
            }

            // Prompt continuation: lines starting with 2+ spaces
            if ($inPrompt) {
                if (preg_match('/^  (.*)$/', $line, $m)) {
                    $promptLines[] = $m[1];
                    continue;
                }
                $inPrompt = false;
                $result['prompt'] = implode("\n", $promptLines);
            }

            // Parse key: value
            if (preg_match('/^(\w+):\s*(.*)$/', $trimmed, $m)) {
                $key = $m[1];
                $value = trim($m[2]);

                if ($key === 'prompt' && $value === '|') {
                    $inPrompt = true;
                    $promptLines = [];
                    continue;
                }

                if (array_key_exists($key, $result)) {
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

        // Validate entity
        $expectedEntity = $model instanceof Content ? 'content' : 'tag';
        if ($frontMatter['entity'] !== '' && $frontMatter['entity'] !== $expectedEntity) {
            $errors[] = 'Le type d\'entité "' . $frontMatter['entity'] . '" ne correspond pas (attendu : "' . $expectedEntity . '").';
        }

        // Validate type
        $type = $model->getTypeQuery()->one();
        if ($type !== null && $frontMatter['type'] !== '' && $frontMatter['type'] !== $type->getName()) {
            $errors[] = 'Le type "' . $frontMatter['type'] . '" ne correspond pas au type du modèle "' . $type->getName() . '".';
        }

        // Validate article
        if ($frontMatter['article'] !== '') {
            $elasticSchemaId = $model->getElasticSchemaId();
            if ($elasticSchemaId === null) {
                $errors[] = 'Le markdown déclare un article "' . $frontMatter['article'] . '" mais le modèle n\'a pas de schema elastic.';
            } else {
                $schema = ElasticSchema::query()
                    ->andWhere(['id' => $elasticSchemaId])
                    ->one();
                if ($schema === null || strtolower($schema->getName()) !== strtolower($frontMatter['article'])) {
                    $schemaName = $schema !== null ? $schema->getName() : 'inconnu';
                    $errors[] = 'Le schema article "' . $frontMatter['article'] . '" ne correspond pas au schema du modèle "' . $schemaName . '".';
                }
            }
        }

        // Validate lang (warning only)
        if ($frontMatter['lang'] !== '' && $model instanceof Content) {
            $langId = $model->getLanguageId();
            if ($langId !== null && $frontMatter['lang'] !== $langId) {
                $warnings[] = 'La langue "' . $frontMatter['lang'] . '" diffère de celle du modèle "' . $langId . '".';
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

        // Find start of actual content
        $lastContenuActuel = -1;
        $firstStartDelimiter = -1;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '# Contenu actuel') {
                $lastContenuActuel = $i;
            }
            if ($firstStartDelimiter === -1 && preg_match('/^:::start:\w+$/i', $trimmed)) {
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

        // Split on :::start:TYPE / :::end, respecting code fences
        $blocks = [];
        $currentType = null;
        $currentContent = [];
        $inCodeFence = false;

        for ($i = $contentStart; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);

            // Track code fence state
            if (!$inCodeFence && str_starts_with($trimmed, '```')) {
                $inCodeFence = true;
            } elseif ($inCodeFence && $trimmed === '```') {
                $inCodeFence = false;
            }

            // Only process delimiters outside code fences
            if (!$inCodeFence) {
                if (preg_match('/^:::start:(\w+)$/i', $trimmed, $m)) {
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

            // Skip delimiters and empty lines
            if ($trimmed === '' || preg_match('/^:::/', $trimmed)) {
                continue;
            }

            // Code fence opening: ```{language}
            if (!$inCodeFence && preg_match('/^```\{(\w+)\}$/', $trimmed, $m)) {
                $inCodeFence = true;
                $codeFenceLanguageField = $m[1];
                continue;
            }

            // Code fence closing: ```
            if ($inCodeFence && $trimmed === '```') {
                $inCodeFence = false;
                continue;
            }

            // Inside code fence: {code} field
            if ($inCodeFence) {
                if (preg_match('/^\{(\??)(\w+)(?::rich)?\}$/', $trimmed, $m)) {
                    $slots[] = ['type' => 'codefence', 'languageField' => $codeFenceLanguageField, 'codeField' => $m[2]];
                }
                continue;
            }

            // Dynamic heading: {#level} {title} — level comes from a data field
            if (preg_match('/^\{#(\w+)\}\s+\{(\??)(\w+)\}$/', $trimmed, $m)) {
                $slots[] = ['type' => 'heading', 'field' => $m[3], 'levelField' => $m[1], 'optional' => $m[2] === '?'];
                continue;
            }

            // Heading: # {title} or ## {title}
            if (preg_match('/^(#{1,6})\s+\{(\??)(\w+)\}$/', $trimmed, $m)) {
                $slots[] = ['type' => 'heading', 'field' => $m[3], 'level' => strlen($m[1]), 'optional' => $m[2] === '?'];
                continue;
            }

            // Image: ![{alt}]({image})
            if (preg_match('/^!\[\{(\??)(\w+)\}\]\(\{(\??)(\w+)\}\)$/', $trimmed, $m)) {
                $slots[] = ['type' => 'image', 'altField' => $m[2], 'imageField' => $m[4]];
                continue;
            }

            // Link: [{label}]({route} OR {url}) or [{label}]({url})
            if (preg_match('/^\[\{(\??)(\w+)\}\]\((.+)\)$/', $trimmed, $m)) {
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

            // Text field: {field}, {?field}, {field:rich}
            if (preg_match('/^\{(\??)(\w+)(:rich)?\}$/', $trimmed, $m)) {
                $slots[] = [
                    'type' => 'text',
                    'field' => $m[2],
                    'optional' => $m[1] === '?',
                    'rich' => isset($m[3]) && $m[3] === ':rich',
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
                        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
                            $data[$slot['field']] = trim($m[2]);
                            if (isset($slot['levelField'])) {
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
                        if (preg_match('/^!\[(.*)\]\((.+)\)$/', $trimmed, $m)) {
                            $alt = $m[1];
                            $src = $m[2];
                            if (str_starts_with($src, '@blfs/')) {
                                $data[$slot['altField']] = $alt;
                                $data[$slot['imageField']] = $src;
                            } else {
                                $data[$slot['altField']] = $alt !== '' ? $alt . ' | ' . $src : $src;
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
                        if (!str_starts_with($trimmed, '!') && preg_match('/^\[(.+)\]\((.+)\)$/', $trimmed, $m)) {
                            $data[$slot['labelField']] = $m[1];
                            $url = $m[2];

                            if (count($slot['urlFields']) === 1) {
                                $data[$slot['urlFields'][0]] = $url;
                            } elseif (str_starts_with($url, '/') && in_array('route', $slot['urlFields'])) {
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
                        if (preg_match('/^```(\w*)$/', $trimmed, $m)) {
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
                        // Non-rich text: blank line = field boundary
                        if (!$slot['rich'] && $trimmed === '' && !empty($textLines)) {
                            $currentLine++;
                            break;
                        }
                        $textLines[] = $lines[$currentLine];
                        $currentLine++;
                    }

                    $textValue = trim(implode("\n", $textLines));

                    if ($textValue !== '' && $this->containsMarkdownFormatting($textValue)) {
                        if ($this->isWysiwygField($schema->getSchema(), $slot['field'])) {
                            $textValue = $this->markdownToHtml($textValue);
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
            'heading' => (bool) preg_match('/^#{1,6}\s+/', $line),
            'image' => (bool) preg_match('/^!\[/', $line),
            'link' => !str_starts_with($line, '!') && (bool) preg_match('/^\[.+\]\(.+\)$/', $line),
            'codefence' => str_starts_with($line, '```'),
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
        if (str_contains($text, '**')) {
            return true;
        }
        if (str_contains($text, '++')) {
            return true;
        }
        if (str_contains($text, '~~')) {
            return true;
        }
        if (str_contains($text, '==')) {
            return true;
        }
        if (str_contains($text, '`')) {
            return true;
        }
        if (preg_match('/\[.+?\]\(.+?\)/', $text)) {
            return true;
        }
        if (preg_match('/^- /m', $text)) {
            return true;
        }
        if (preg_match('/(?<!\*)\*(?!\*)/', $text)) {
            return true;
        }
        return false;
    }

    /**
     * Convert limited markdown to HTML (inverse of htmlToMarkdown).
     *
     * @param string $markdown
     * @return string HTML
     */
    private function markdownToHtml(string $markdown): string
    {
        $md = trim($markdown);
        $lines = explode("\n", $md);

        // Step 1: Group lines into blocks (paragraphs, lists)
        $blocks = [];
        $currentBlock = [];
        $currentType = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if (!empty($currentBlock)) {
                    $blocks[] = ['type' => $currentType, 'lines' => $currentBlock];
                    $currentBlock = [];
                    $currentType = null;
                }
                continue;
            }

            $lineType = str_starts_with($trimmed, '- ') ? 'list' : 'text';

            if ($currentType !== null && $currentType !== $lineType) {
                $blocks[] = ['type' => $currentType, 'lines' => $currentBlock];
                $currentBlock = [];
            }

            $currentType = $lineType;
            $currentBlock[] = $trimmed;
        }

        if (!empty($currentBlock)) {
            $blocks[] = ['type' => $currentType, 'lines' => $currentBlock];
        }

        // Step 2: Convert blocks to HTML
        $html = '';
        foreach ($blocks as $block) {
            if ($block['type'] === 'list') {
                $items = '';
                foreach ($block['lines'] as $line) {
                    $items .= '<li>' . substr($line, 2) . '</li>';
                }
                $html .= '<ul>' . $items . '</ul>';
            } else {
                $content = implode(' ', $block['lines']);
                $html .= '<p>' . $content . '</p>';
            }
        }

        // Step 3: Inline formatting (order: ++, ~~, ==, **, `, *, links)
        $html = preg_replace('/\+\+(.+?)\+\+/', '<u>$1</u>', $html);
        $html = preg_replace('/~~(.+?)~~/', '<s>$1</s>', $html);
        $html = preg_replace('/==(.+?)==/', '<mark>$1</mark>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" target="_blank">$1</a>', $html);

        return $html;
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

        return isset($schema['properties'][$fieldName]['format'])
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
            if (strtolower($schema->getName()) === $slug && in_array($schema->getKind(), $allowedKinds, true)) {
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
            // Delete all existing blocs
            foreach ($model->getBlocsQuery()->all() as $bloc) {
                $model->detachBloc($bloc);
            }

            // Set article properties
            if ($articleData !== null) {
                foreach ($articleData as $prop => $value) {
                    $model->$prop = $value;
                }
                $model->save();
            }

            // Create new blocs
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
