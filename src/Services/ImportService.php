<?php

declare(strict_types=1);

/**
 * ImportService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Models\Author;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ContentAuthor;
use Blackcube\Dcore\Models\ContentBloc;
use Blackcube\Dcore\Models\ContentTag;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Models\Sitemap;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\TagAuthor;
use Blackcube\Dcore\Models\TagBloc;
use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Models\Xeo;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Service for importing Content/Tag from JSON export data.
 * Handles parsing, validation and persistence in two transactions.
 */
class ImportService
{
    public function __construct(
        private ConnectionInterface $db,
        private FileService $fileService,
        private MapperService $mapper,
    ) {}

    /**
     * Parse and validate JSON structure.
     *
     * @return array{valid: bool, elementType: ?string, data: ?array, error: ?string}
     */
    public function parseJson(string $json): array
    {
        $data = json_decode($json, true);
        if ($data === null || is_array($data) === false) {
            return ['valid' => false, 'elementType' => null, 'data' => null, 'error' => 'JSON invalide'];
        }

        $elementType = $data['elementType'] ?? null;
        if ($elementType === null || in_array($elementType, ['content', 'tag'], true) === false) {
            return ['valid' => false, 'elementType' => null, 'data' => null, 'error' => 'elementType manquant ou invalide'];
        }

        return ['valid' => true, 'elementType' => $elementType, 'data' => $data, 'error' => null];
    }

    /**
     * Check if element already exists by id or slug.path.
     *
     * @return array{existsById: bool, existsBySlug: bool, existingModel: ?object, existingSlug: ?Slug}
     */
    public function checkExistence(array $data): array
    {
        $elementType = $data['elementType'];
        $modelClass = $elementType === 'content' ? Content::class : Tag::class;

        $existsById = false;
        $existsBySlug = false;
        $existingModel = null;
        $existingSlug = null;

        $id = $data['id'] ?? null;
        if ($id !== null) {
            $existingModel = $modelClass::query()->andWhere(['id' => $id])->one();
            $existsById = $existingModel !== null;
        }

        $slugPath = ($data['slug'] !== null) ? ($data['slug']['path'] ?? null) : null;
        $slugHostId = ($data['slug'] !== null) ? ($data['slug']['hostId'] ?? 1) : 1;
        if ($slugPath !== null) {
            $existingSlug = Slug::query()
                ->andWhere(['path' => $slugPath, 'hostId' => $slugHostId])
                ->one();
            $existsBySlug = $existingSlug !== null;
        }

        return [
            'existsById' => $existsById,
            'existsBySlug' => $existsBySlug,
            'existingModel' => $existingModel,
            'existingSlug' => $existingSlug,
        ];
    }

    /**
     * Validate foreign references and return missing ones.
     *
     * @return array{missing: array, warnings: array}
     */
    public function validateReferences(array $data, array $corrections = []): array
    {
        $missing = [];
        $warnings = [];
        $elementType = $data['elementType'];

        if ($elementType === 'content') {
            $langId = $corrections['languageId'] ?? $data['languageId'] ?? null;
            if ($langId !== null && Language::query()->andWhere(['id' => $langId])->one() === null) {
                $missing['languageId'] = $langId;
            }
        }

        $typeId = $corrections['typeId'] ?? $data['typeId'] ?? null;
        if ($typeId !== null && Type::query()->andWhere(['id' => $typeId])->one() === null) {
            $missing['typeId'] = $typeId;
        }

        if ($data['slug'] !== null) {
            $hostId = $corrections['hostId'] ?? $data['slug']['hostId'] ?? 1;
            if (Host::query()->andWhere(['id' => $hostId])->one() === null) {
                $missing['hostId'] = $hostId;
            }
        }

        $authors = $data['authors'] ?? [];
        $missingAuthors = [];
        $authorIds = [];
        foreach ($authors as $i => $authorData) {
            $authorId = $corrections['authors'][$i] ?? $authorData['id'] ?? null;
            if ($authorId !== null) {
                $authorIds[$i] = $authorId;
            }
        }
        $foundAuthorIds = [];
        if (empty($authorIds) === false) {
            $authorQuery = Author::query()->andWhere(['in', 'id', array_values($authorIds)]);
            /** @var Author $author */
            foreach ($authorQuery->each() as $author) {
                $foundAuthorIds[$author->getId()] = true;
            }
        }
        foreach ($authorIds as $i => $authorId) {
            if (isset($foundAuthorIds[$authorId]) === false) {
                $missingAuthors[$i] = $authors[$i];
            }
        }
        if (empty($missingAuthors) === false) {
            $missing['authors'] = $missingAuthors;
        }

        if ($elementType === 'content') {
            $tags = $data['tags'] ?? [];
            $missingTags = [];
            $tagIds = [];
            foreach ($tags as $tagIndex => $tagData) {
                $tagId = $tagData['id'] ?? null;
                if ($tagId !== null) {
                    $tagIds[$tagIndex] = $tagId;
                }
            }
            $foundTagIds = [];
            if (empty($tagIds) === false) {
                $tagQuery = Tag::query()->andWhere(['in', 'id', array_values($tagIds)]);
                /** @var Tag $tag */
                foreach ($tagQuery->each() as $tag) {
                    $foundTagIds[$tag->getId()] = true;
                }
            }
            foreach ($tagIds as $tagIndex => $tagId) {
                if (isset($foundTagIds[$tagId]) === false) {
                    $missingTags[] = $tags[$tagIndex];
                }
            }
            if (empty($missingTags) === false) {
                $warnings['tags'] = $missingTags;
            }
        }

        $typeId = $corrections['typeId'] ?? $data['typeId'] ?? null;
        $blocs = $data['blocs'] ?? [];
        $missingSchemas = [];
        $schemaIds = [];
        foreach ($blocs as $i => $blocData) {
            $schemaId = $corrections['blocs'][$i] ?? $blocData['elasticSchemaId'] ?? null;
            if ($schemaId !== null) {
                $schemaIds[$i] = $schemaId;
            }
        }
        $foundSchemaIds = [];
        if (empty($schemaIds) === false) {
            $schemaQuery = ElasticSchema::query()->andWhere(['in', 'id', array_values($schemaIds)]);
            /** @var ElasticSchema $elasticSchema */
            foreach ($schemaQuery->each() as $elasticSchema) {
                $foundSchemaIds[$elasticSchema->getId()] = true;
            }
        }
        foreach ($schemaIds as $i => $schemaId) {
            if (isset($foundSchemaIds[$schemaId]) === false) {
                $missingSchemas[$i] = $blocs[$i];
            }
        }
        if (empty($missingSchemas) === false) {
            $missing['blocs'] = $missingSchemas;
        }

        $xeoBlocs = ($data['slug'] !== null) ? ($data['slug']['xeo']['blocs'] ?? []) : [];
        $missingXeoSchemas = [];
        $xeoSchemaIds = [];
        foreach ($xeoBlocs as $i => $blocData) {
            $schemaId = $corrections['xeoBlocs'][$i] ?? $blocData['elasticSchemaId'] ?? null;
            if ($schemaId !== null) {
                $xeoSchemaIds[$i] = $schemaId;
            }
        }
        $foundXeoSchemaIds = [];
        if (empty($xeoSchemaIds) === false) {
            $xeoSchemaQuery = ElasticSchema::query()->andWhere(['in', 'id', array_values($xeoSchemaIds)]);
            /** @var ElasticSchema $elasticSchema */
            foreach ($xeoSchemaQuery->each() as $elasticSchema) {
                $foundXeoSchemaIds[$elasticSchema->getId()] = true;
            }
        }
        foreach ($xeoSchemaIds as $i => $schemaId) {
            if (isset($foundXeoSchemaIds[$schemaId]) === false) {
                $missingXeoSchemas[$i] = $xeoBlocs[$i];
            }
        }
        if (empty($missingXeoSchemas) === false) {
            $missing['xeoBlocs'] = $missingXeoSchemas;
        }

        return ['missing' => $missing, 'warnings' => $warnings];
    }

    /**
     * Execute the import.
     *
     * @param array $data The parsed JSON data
     * @param string $mode 'overwrite' or 'create'
     * @param string|null $targetPath Hazeltree target path for positioning
     * @param array $corrections Corrected references from step 3
     * @return array{success: bool, model: ?object, error: ?string}
     */
    public function execute(array $data, string $mode, ?string $targetPath, array $corrections): array
    {
        $elementType = $data['elementType'];
        $isContent = $elementType === 'content';
        $modelClass = $isContent ? Content::class : Tag::class;

        $data = $this->applyCorrections($data, $corrections);

        $transaction = $this->db->beginTransaction();
        try {
            $model = null;
            $slug = null;
            $xeo = null;
            $sitemap = null;
            $createdBlocs = [];
            $createdXeoBlocs = [];

            if ($mode === 'overwrite') {
                $model = $this->loadExistingModel($data, $modelClass);
                if ($model === null) {
                    throw new \RuntimeException('Existing model not found for overwrite');
                }
                $this->cleanExistingData($model, $isContent);
                $slug = $model->getSlugQuery()->one();
            }

            if ($data['slug'] !== null) {
                $slug = $this->saveSlug($data, $slug);
                $xeo = $this->saveXeo($data, $slug);
                $sitemap = $this->saveSitemap($data, $slug);
            }

            if ($mode === 'overwrite') {
                $model = $this->updateModel($model, $data, $slug);
            } else {
                $model = $this->createModel($data, $slug, $modelClass, $targetPath);
            }

            $blocs = $data['blocs'] ?? [];
            foreach ($blocs as $i => $blocData) {
                $bloc = $this->createBloc($blocData, true);
                $createdBlocs[$i] = $bloc;
                if ($isContent === true) {
                    $model->attachBloc($bloc);
                } else {
                    $model->attachBloc($bloc);
                }
            }

            $xeoBlocs = ($xeo !== null) ? ($data['slug']['xeo']['blocs'] ?? []) : [];
            foreach ($xeoBlocs as $i => $blocData) {
                $bloc = $this->createBloc($blocData, true);
                $createdXeoBlocs[$i] = $bloc;
                $xeo->attachBloc($bloc, $i + 1);
            }

            if ($isContent === true) {
                $this->attachTags($model, $data['tags'] ?? []);
            }

            $this->attachAuthors($model, $data['authors'] ?? [], $isContent);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            return ['success' => false, 'model' => null, 'error' => $e->getMessage()];
        }

        $entityType = $isContent ? 'contents' : 'tags';
        $modelId = $model->getId();

        $transaction2 = $this->db->beginTransaction();
        try {
            foreach ($createdBlocs as $i => $bloc) {
                $blocData = $blocs[$i];
                $this->processBlocFiles($bloc, $blocData['data'] ?? [], $entityType, $modelId);
            }

            foreach ($createdXeoBlocs as $i => $bloc) {
                $blocData = $xeoBlocs[$i];
                $this->processBlocFiles($bloc, $blocData['data'] ?? [], $entityType, $modelId);
            }

            $modelElasticData = $data['data'] ?? [];
            $this->processModelFiles($model, $modelElasticData, $entityType, $modelId);

            if ($xeo !== null) {
                $xeoData = $data['slug']['xeo'] ?? [];
                $imageData = $xeoData['image'] ?? null;
                if ($imageData !== null) {
                    $basePath = $entityType.'/'.$modelId.'/xeo/'.$xeo->getId();
                    $dataUri = null;
                    $filename = null;

                    if (is_array($imageData) === true && isset($imageData['data']) === true) {
                        $dataUri = $imageData['data'];
                        $filename = $imageData['name'] ?? null;
                    } elseif (is_string($imageData) === true && str_starts_with($imageData, 'data:') === true) {
                        $dataUri = $imageData;
                    }

                    if ($dataUri !== null) {
                        $path = $this->decodeBase64FileRaw($dataUri, $basePath, $filename);
                        if ($path !== null) {
                            $xeo->setImage($path);
                            $xeo->save();
                        }
                    }
                }
            }

            $transaction2->commit();
        } catch (\Throwable $e) {
            $transaction2->rollBack();
            return ['success' => false, 'model' => $model, 'error' => 'Import partiel — erreur fichiers : '.$e->getMessage()];
        }

        return ['success' => true, 'model' => $model, 'error' => null];
    }

    /**
     * Apply corrections from step 3 onto the data.
     */
    private function applyCorrections(array $data, array $corrections): array
    {
        if (isset($corrections['languageId']) === true) {
            $data['languageId'] = $corrections['languageId'];
        }
        if (isset($corrections['typeId']) === true) {
            $data['typeId'] = $corrections['typeId'];
        }
        if (isset($corrections['hostId']) === true && $data['slug'] !== null) {
            $data['slug']['hostId'] = $corrections['hostId'];
        }
        if (isset($corrections['slugPath']) === true && $data['slug'] !== null) {
            $data['slug']['path'] = $corrections['slugPath'];
        }
        if (isset($corrections['authors']) === true) {
            foreach ($corrections['authors'] as $i => $authorId) {
                if (isset($data['authors'][$i]) === true) {
                    $data['authors'][$i]['id'] = $authorId;
                }
            }
        }
        if (isset($corrections['blocs']) === true) {
            foreach ($corrections['blocs'] as $i => $schemaId) {
                if (isset($data['blocs'][$i]) === true) {
                    $data['blocs'][$i]['elasticSchemaId'] = $schemaId;
                }
            }
        }
        if (isset($corrections['xeoBlocs']) === true && $data['slug'] !== null) {
            foreach ($corrections['xeoBlocs'] as $i => $schemaId) {
                if (isset($data['slug']['xeo']['blocs'][$i]) === true) {
                    $data['slug']['xeo']['blocs'][$i]['elasticSchemaId'] = $schemaId;
                }
            }
        }

        return $data;
    }

    private function loadExistingModel(array $data, string $modelClass): ?object
    {
        $id = $data['id'] ?? null;
        if ($id !== null) {
            $model = $modelClass::query()->andWhere(['id' => $id])->one();
            if ($model !== null) {
                return $model;
            }
        }

        $slugPath = ($data['slug'] !== null) ? ($data['slug']['path'] ?? null) : null;
        if ($slugPath !== null) {
            $slug = Slug::query()->andWhere(['path' => $slugPath])->one();
            if ($slug !== null) {
                return $slug->getElement();
            }
        }

        return null;
    }

    /**
     * Clean existing data before overwrite.
     */
    private function cleanExistingData(object $model, bool $isContent): void
    {
        $blocsQuery = $model->getBlocsQuery();
        /** @var Bloc $bloc */
        foreach ($blocsQuery->each() as $bloc) {
            $model->detachBloc($bloc);
        }

        $slug = $model->getSlugQuery()->one();
        if ($slug !== null) {
            $xeo = $slug->getXeoQuery()->one();
            if ($xeo !== null) {
                $existingBlocs = [];
                foreach ($xeo->getBlocsQuery()->each() as $bloc) {
                    $existingBlocs[] = $bloc;
                }
                foreach ($existingBlocs as $bloc) {
                    $xeo->detachBloc($bloc);
                }
            }
        }

        if ($isContent && method_exists($model, 'syncTags') === true) {
            $model->syncTags([]);
        }

        $pivotClass = $isContent ? ContentAuthor::class : TagAuthor::class;
        $fkColumn = $isContent ? 'contentId' : 'tagId';
        $pivotsQuery = $pivotClass::query()->andWhere([$fkColumn => $model->getId()]);
        /** @var pivotClass $pivot */
        foreach ($pivotsQuery->each() as $pivot) {
            $pivot->delete();
        }
    }

    private function saveSlug(array $data, ?Slug $existing): Slug
    {
        $slugData = $data['slug'] ?? [];
        $slug = $existing ?? new Slug();

        $this->mapper->import($slug, $slugData);
        $slug->save();

        return $slug;
    }

    private function saveXeo(array $data, Slug $slug): ?Xeo
    {
        $xeoData = $data['slug']['xeo'] ?? null;
        if ($xeoData === null) {
            return null;
        }

        $xeo = Xeo::query()->andWhere(['slugId' => $slug->getId()])->one();
        if ($xeo === null) {
            $xeo = new Xeo();
            $xeo->setSlugId($slug->getId());
        }

        $this->mapper->import($xeo, $xeoData);

        $canonical = $xeoData['canonical'] ?? false;
        $xeo->setCanonicalSlugId($canonical ? $slug->getId() : null);

        $xeo->save();

        return $xeo;
    }

    private function saveSitemap(array $data, Slug $slug): ?Sitemap
    {
        $sitemapData = $data['slug']['sitemap'] ?? null;
        if ($sitemapData === null) {
            return null;
        }

        $sitemap = Sitemap::query()->andWhere(['slugId' => $slug->getId()])->one();
        if ($sitemap === null) {
            $sitemap = new Sitemap();
            $sitemap->setSlugId($slug->getId());
        }

        $this->mapper->import($sitemap, $sitemapData);
        $sitemap->save();

        return $sitemap;
    }

    private function createModel(array $data, ?Slug $slug, string $modelClass, ?string $targetPath): object
    {
        $model = new $modelClass();

        $this->mapper->import($model, $data);
        $model->setSlugId($slug?->getId());
        $model->setActive(false);

        if ($targetPath !== null) {
            $target = $modelClass::query()->andWhere(['path' => $targetPath])->one();
            if ($target !== null) {
                $jsonPath = $data['path'] ?? null;
                if ($jsonPath !== null) {
                    $existing = $modelClass::query()->andWhere(['path' => $jsonPath])->one();
                    if ($existing !== null) {
                        $model->saveAfter($target);
                    } else {
                        $model->saveInto($target);
                    }
                } else {
                    $model->saveInto($target);
                }
            } else {
                $model->save();
            }
        } else {
            $model->save();
        }

        $elasticData = $data['data'] ?? [];
        if (empty($elasticData) === false) {
            $stripped = $this->stripFileValues($elasticData);
            foreach ($stripped as $key => $value) {
                $model->$key = $value;
            }
            $model->save();
        }

        return $model;
    }

    private function updateModel(object $model, array $data, ?Slug $slug): object
    {
        $this->mapper->import($model, $data);
        $model->setSlugId($slug?->getId());
        $model->setActive(false);

        $model->save();

        $elasticData = $data['data'] ?? [];
        if (empty($elasticData) === false) {
            $stripped = $this->stripFileValues($elasticData);
            foreach ($stripped as $key => $value) {
                $model->$key = $value;
            }
            $model->save();
        }

        return $model;
    }

    /**
     * Create a bloc with elastic data but without processing files.
     */
    private function createBloc(array $blocData, bool $skipFiles): Bloc
    {
        $bloc = new Bloc();
        $this->mapper->import($bloc, $blocData);
        $bloc->setActive(true);
        $bloc->save();

        $elasticData = $blocData['data'] ?? [];
        if ($skipFiles === true) {
            $elasticData = $this->stripFileValues($elasticData);
        }
        foreach ($elasticData as $key => $value) {
            $bloc->$key = $value;
        }
        $bloc->save();

        return $bloc;
    }

    /**
     * Strip base64 file values from elastic data.
     * Handles both {name, data} objects and legacy data: strings.
     */
    private function stripFileValues(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($value) === true && str_starts_with($value, 'data:') === true) {
                $result[$key] = null;
            } elseif (is_array($value) === true && isset($value['data']) === true && is_string($value['data']) === true) {
                $result[$key] = null;
            } elseif (is_array($value) === true && isset($value[0]) === true && is_array($value[0]) === true && isset($value[0]['data']) === true) {
                $result[$key] = null;
            } elseif (is_array($value) === true) {
                $result[$key] = $this->stripFileValues($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Process base64 files for a bloc and update elastic values.
     * Handles {name, data} objects, [{name, data}, ...] arrays, and legacy data: strings.
     *
     * @param string $entityType e.g. 'contents', 'tags'
     * @param int $entityId The parent model id
     */
    private function processBlocFiles(Bloc $bloc, array $elasticData, string $entityType, int $entityId): void
    {
        $hasFiles = false;

        foreach ($elasticData as $key => $value) {
            if (is_array($value) === true && isset($value['data']) === true && is_string($value['data']) === true) {
                $path = $this->decodeBase64File($value['data'], $entityType, $entityId, $bloc->getId(), $value['name'] ?? null);
                if ($path !== null) {
                    $bloc->$key = $path;
                    $hasFiles = true;
                }
            } elseif (is_array($value) === true && isset($value[0]) === true && is_array($value[0]) === true && isset($value[0]['data']) === true) {
                $paths = [];
                foreach ($value as $fileObj) {
                    $path = $this->decodeBase64File($fileObj['data'], $entityType, $entityId, $bloc->getId(), $fileObj['name'] ?? null);
                    if ($path !== null) {
                        $paths[] = $path;
                    }
                }
                if (empty($paths) === false) {
                    $bloc->$key = implode(', ', $paths);
                    $hasFiles = true;
                }
            } elseif (is_string($value) === true && str_starts_with($value, 'data:') === true) {
                $path = $this->decodeBase64File($value, $entityType, $entityId, $bloc->getId());
                if ($path !== null) {
                    $bloc->$key = $path;
                    $hasFiles = true;
                }
            }
        }

        if ($hasFiles === true) {
            $bloc->save();
        }
    }

    /**
     * Process base64 files for a model's elastic data (no blocId).
     * Files go to @blfs/{entityType}/{entityId}/{filename}
     */
    private function processModelFiles(object $model, array $elasticData, string $entityType, int $entityId): void
    {
        $hasFiles = false;

        foreach ($elasticData as $key => $value) {
            if (is_array($value) === true && isset($value['data']) === true && is_string($value['data']) === true) {
                $path = $this->decodeBase64File($value['data'], $entityType, $entityId, null, $value['name'] ?? null);
                if ($path !== null) {
                    $model->$key = $path;
                    $hasFiles = true;
                }
            } elseif (is_array($value) === true && isset($value[0]) === true && is_array($value[0]) === true && isset($value[0]['data']) === true) {
                $paths = [];
                foreach ($value as $fileObj) {
                    $path = $this->decodeBase64File($fileObj['data'], $entityType, $entityId, null, $fileObj['name'] ?? null);
                    if ($path !== null) {
                        $paths[] = $path;
                    }
                }
                if (empty($paths) === false) {
                    $model->$key = implode(', ', $paths);
                    $hasFiles = true;
                }
            } elseif (is_string($value) === true && str_starts_with($value, 'data:') === true) {
                $path = $this->decodeBase64File($value, $entityType, $entityId);
                if ($path !== null) {
                    $model->$key = $path;
                    $hasFiles = true;
                }
            }
        }

        if ($hasFiles === true) {
            $model->save();
        }
    }

    /**
     * Decode a base64 data URI and write the file via FileService.
     *
     * @param string $entityType e.g. 'contents', 'tags'
     * @param int $entityId The model id
     * @param int|null $blocId If file belongs to a bloc
     * @param string|null $filename Original filename (used instead of hash if provided)
     * @return string|null The @blfs/ path or null on failure
     */
    private function decodeBase64File(string $dataUri, string $entityType, int $entityId, ?int $blocId = null, ?string $filename = null): ?string
    {
        if (preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUri, $matches) !== 1) {
            return null;
        }

        $mimeType = $matches[1];
        $content = base64_decode($matches[2], true);
        if ($content === false) {
            return null;
        }

        if ($filename === null || $filename === '') {
            $ext = $this->mimeToExtension($mimeType);
            $filename = bin2hex(random_bytes(8)).'.'.$ext;
        }

        $path = $this->fileService->getDefaultAlias().'/'.$entityType.'/'.$entityId;
        if ($blocId !== null) {
            $path .= '/blocs/'.$blocId;
        }
        $path .= '/'.$filename;

        $this->fileService->write($path, $content);

        return $path;
    }

    /**
     * Decode a base64 data URI and write to a custom base path.
     *
     * @param string|null $filename Original filename (used instead of hash if provided)
     */
    private function decodeBase64FileRaw(string $dataUri, string $basePath, ?string $filename = null): ?string
    {
        if (preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUri, $matches) !== 1) {
            return null;
        }

        $mimeType = $matches[1];
        $content = base64_decode($matches[2], true);
        if ($content === false) {
            return null;
        }

        if ($filename === null || $filename === '') {
            $ext = $this->mimeToExtension($mimeType);
            $filename = bin2hex(random_bytes(8)).'.'.$ext;
        }

        $path = $this->fileService->getDefaultAlias().'/'.$basePath.'/'.$filename;

        $this->fileService->write($path, $content);

        return $path;
    }

    private function mimeToExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => 'bin',
        };
    }

    private function attachTags(object $model, array $tagsData): void
    {
        foreach ($tagsData as $tagData) {
            $tagId = $tagData['id'] ?? null;
            if ($tagId === null) {
                continue;
            }
            $tag = Tag::query()->andWhere(['id' => $tagId])->one();
            if ($tag !== null) {
                $model->attachTag($tag);
            }
        }
    }

    private function attachAuthors(object $model, array $authorsData, bool $isContent): void
    {
        $pivotClass = $isContent ? ContentAuthor::class : TagAuthor::class;
        $fkColumn = $isContent ? 'contentId' : 'tagId';
        $order = 1;

        foreach ($authorsData as $authorData) {
            $authorId = $authorData['id'] ?? null;
            if ($authorId === null) {
                continue;
            }
            $author = Author::query()->andWhere(['id' => $authorId])->one();
            if ($author !== null) {
                $pivot = new $pivotClass();
                if ($isContent === true) {
                    $pivot->setContentId($model->getId());
                } else {
                    $pivot->setTagId($model->getId());
                }
                $pivot->setAuthorId($author->getId());
                $pivot->setOrder($order++);
                $pivot->save();
            }
        }
    }

    /**
     * Check if a slug path is already taken for a given host.
     */
    public function isSlugPathTaken(string $path, int $hostId): bool
    {
        return Slug::query()
            ->andWhere(['path' => $path, 'hostId' => $hostId])
            ->one() !== null;
    }

    /**
     * Get available tree targets for positioning.
     *
     * @return array<int, array{path: string, name: string, level: int}>
     */
    public function getTreeTargets(string $elementType): array
    {
        $modelClass = $elementType === 'content' ? Content::class : Tag::class;
        $targets = [];

        /** @var modelClass $item */
        foreach ($modelClass::query()->orderBy(['left' => SORT_ASC])->each() as $item) {
            $targets[] = [
                'path' => $item->path,
                'name' => $item->getName() ?? '(sans nom)',
                'level' => $item->level,
            ];
        }

        return $targets;
    }

    /**
     * Get dropdown options for references.
     */
    public function getReferenceOptions(): array
    {
        $languages = [];
        /** @var Language $lang */
        foreach (Language::query()->active()->orderBy(['name' => SORT_ASC])->each() as $lang) {
            $languages[$lang->getId()] = $lang->getName();
        }

        $types = [];
        /** @var Type $type */
        foreach (Type::query()->active()->orderBy(['name' => SORT_ASC])->each() as $type) {
            $types[$type->getId()] = $type->getName();
        }

        $hosts = [];
        /** @var Host $host */
        foreach (Host::query()->active()->orderBy(['name' => SORT_ASC])->each() as $host) {
            $hosts[$host->getId()] = $host->getName();
        }

        $authors = [];
        /** @var Author $author */
        foreach (Author::query()->active()->orderBy(['lastname' => SORT_ASC, 'firstname' => SORT_ASC])->each() as $author) {
            $authors[$author->getId()] = trim($author->getFirstname().' '.$author->getLastname());
        }

        $elasticSchemas = [];
        /** @var ElasticSchema $schema */
        foreach (ElasticSchema::query()->active()->orderBy(['name' => SORT_ASC])->each() as $schema) {
            $elasticSchemas[$schema->getId()] = $schema->getName();
        }

        return [
            'languages' => $languages,
            'types' => $types,
            'hosts' => $hosts,
            'authors' => $authors,
            'elasticSchemas' => $elasticSchemas,
        ];
    }
}
