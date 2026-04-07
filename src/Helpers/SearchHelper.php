<?php

declare(strict_types=1);

/**
 * SearchHelper.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ContentBloc;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\TagBloc;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\Db\Expression\Expression;

use function array_keys;
use function array_merge;
use function in_array;
use function json_decode;

/**
 * Full-text search helper across elastic JSON fields with relevance scoring.
 *
 * Builds ActiveQuery instances with occurrence-based relevance ranking on Content/Tag
 * and their associated Blocs. Results are ordered by total occurrence count (DESC).
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class SearchHelper
{
    /**
     * Occurrence counting callback for buildFormulae.
     *
     * Counts how many times the :search param appears in a column value.
     */
    private static function occurrenceCallback(): \Closure
    {
        return fn (string $col) =>
            "(CHAR_LENGTH(LOWER(COALESCE($col, ''))) "
            . "- CHAR_LENGTH(REPLACE(LOWER(COALESCE($col, '')), LOWER(:search), ''))) "
            . "/ CHAR_LENGTH(:search)";
    }

    /**
     * Collect searchable text field names from all active elastic schemas,
     * grouped by kind. Loaded once per request.
     *
     * @return list<string>
     */
    private static function getTextFields(array $kinds): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            $schemas = ElasticSchema::query()
                ->andWhere(['active' => true])
                ->all();

            foreach ($schemas as $schema) {
                $kind = $schema->getKind();
                $parsed = json_decode($schema->getSchema(), true);

                foreach ($parsed['properties'] ?? [] as $name => $def) {
                    $format = $def['format'] ?? null;
                    if (($def['type'] ?? '') === 'string'
                        && ($format === null || in_array($format, ['textarea', 'wysiwyg'], true))) {
                        $cache[$kind->value][$name] = true;
                    }
                }
            }
        }

        $fields = [];
        foreach ($kinds as $kind) {
            foreach ($cache[$kind->value] ?? [] as $name => $_) {
                $fields[$name] = true;
            }
        }

        return array_keys($fields);
    }

    /**
     * Text fields from Page + Common schemas (for Content/Tag direct search).
     *
     * @return list<string>
     */
    private static function getPageTextFields(): array
    {
        static $fields = null;

        return $fields ??= self::getTextFields([
            ElasticSchemaKind::Page,
            ElasticSchemaKind::Common,
        ]);
    }

    /**
     * Text fields from Bloc + Common schemas (for Bloc search).
     *
     * @return list<string>
     */
    private static function getBlocTextFields(): array
    {
        static $fields = null;

        return $fields ??= self::getTextFields([
            ElasticSchemaKind::Bloc,
            ElasticSchemaKind::Common,
        ]);
    }

    /**
     * Return a Content query ranked by relevance.
     *
     * Scores occurrences across:
     * - Content.name (physical column)
     * - Content elastic text fields (Page + Common schemas)
     * - Bloc elastic text fields via ContentBloc pivot (correlated subquery)
     *
     * Results are ordered by total occurrence count (DESC).
     * The returned query has no pagination or publishable scope.
     */
    public static function contentQuery(string $search): ActiveQueryInterface
    {
        $pageFields = self::getPageTextFields();
        $blocFields = self::getBlocTextFields();
        $occurrences = self::occurrenceCallback();
        $params = [':search' => $search];

        $query = Content::query();
        $contentScore = $query->buildFormulae($occurrences, array_merge(['name'], $pageFields), $params);

        if (!empty($blocFields)) {
            $blocQuery = Bloc::query();
            $blocScore = $blocQuery->buildFormulae($occurrences, $blocFields, $params);

            $blocSubquery = "(SELECT COALESCE(SUM($blocScore), 0) "
                . "FROM {{%contents_blocs}} "
                . "INNER JOIN {{%blocs}} ON {{%blocs}}.[[id]] = {{%contents_blocs}}.[[blocId]] "
                . "WHERE {{%contents_blocs}}.[[contentId]] = {{%contents}}.[[id]])";

            $relevanceExpr = new Expression(
                "($contentScore) + $blocSubquery",
                $params,
            );
        } else {
            $relevanceExpr = $contentScore;
        }

        return $query
            ->addSelect(['{{%contents}}.*', 'relevance' => $relevanceExpr])
            ->having(['>', 'relevance', 0])
            ->orderBy(['relevance' => SORT_DESC]);
    }

    /**
     * Return a Tag query ranked by relevance.
     *
     * Scores occurrences across:
     * - Tag.name (physical column)
     * - Tag elastic text fields (Page + Common schemas)
     * - Bloc elastic text fields via TagBloc pivot (correlated subquery)
     *
     * Results are ordered by total occurrence count (DESC).
     * The returned query has no pagination or publishable scope.
     */
    public static function tagQuery(string $search): ActiveQueryInterface
    {
        $pageFields = self::getPageTextFields();
        $blocFields = self::getBlocTextFields();
        $occurrences = self::occurrenceCallback();
        $params = [':search' => $search];

        $query = Tag::query();
        $tagScore = $query->buildFormulae($occurrences, array_merge(['name'], $pageFields), $params);

        if (!empty($blocFields)) {
            $blocQuery = Bloc::query();
            $blocScore = $blocQuery->buildFormulae($occurrences, $blocFields, $params);

            $blocSubquery = "(SELECT COALESCE(SUM($blocScore), 0) "
                . "FROM {{%tags_blocs}} "
                . "INNER JOIN {{%blocs}} ON {{%blocs}}.[[id]] = {{%tags_blocs}}.[[blocId]] "
                . "WHERE {{%tags_blocs}}.[[tagId]] = {{%tags}}.[[id]])";

            $relevanceExpr = new Expression(
                "($tagScore) + $blocSubquery",
                $params,
            );
        } else {
            $relevanceExpr = $tagScore;
        }

        return $query
            ->addSelect(['{{%tags}}.*', 'relevance' => $relevanceExpr])
            ->having(['>', 'relevance', 0])
            ->orderBy(['relevance' => SORT_DESC]);
    }
}
