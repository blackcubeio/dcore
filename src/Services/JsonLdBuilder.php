<?php

declare(strict_types=1);

/**
 * JsonLdBuilder.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\GlobalXeo;
use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Enums\JsonLdKind;
use Blackcube\Dcore\Models\Author;
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Models\Xeo as XeoModel;
use Blackcube\FileProvider\CacheFile;

final class JsonLdBuilder implements JsonLdBuilderInterface
{
    private string $baseUrl = '';

    public function build(int $slugId, string $host): array
    {
        $slug = Slug::query()->andWhere(['id' => $slugId])->one();
        if ($slug === null) {
            return [];
        }

        $xeoModel = $slug->getXeoQuery()->one();
        if ($xeoModel === null || !$xeoModel->isActive()) {
            return [];
        }

        $hostId = $slug->getHostId();
        if ($hostId > 1) {
            $hostModel = Host::query()->andWhere(['id' => $hostId])->one();
            $this->baseUrl = 'https://' . ($hostModel?->getName() ?? $host);
        } else {
            $hostModel = null;
            $this->baseUrl = 'https://' . $host;
        }
        $element = $slug->getElement();
        $language = ($element instanceof Content) ? $element->getLanguageId() : null;

        // Load GlobalXeo data
        $orgData = $this->loadGlobalXeo(JsonLdKind::Organization, $hostId);
        $webSiteData = $this->loadGlobalXeo(JsonLdKind::WebSite, $hostId);

        // Load XeoBlocs grouped by schema name
        $xeoBlocs = $this->loadXeoBlocs($xeoModel);

        $graph = [];

        // Organization
        if ($orgData !== null) {
            $graph[] = $this->buildOrganization($orgData);
        }

        // WebSite
        if ($webSiteData !== null) {
            $graph[] = $this->buildWebSite($webSiteData, $hostModel, $language);
        }

        // Authors from Content or Tag
        $authors = ($element !== null) ? $element->getAuthorsQuery()->all() : [];

        // Page type — always built from Xeo model, enriched by Hero bloc if present
        $heroData = ($xeoBlocs['Hero'] ?? [])[0] ?? null;
        $graph[] = $this->buildType($heroData, $xeoModel, $webSiteData, $language, $authors);

        // FAQ → consolidated
        $faqBlocs = $xeoBlocs['FAQ'] ?? [];
        if (!empty($faqBlocs)) {
            $graph[] = $this->buildFaq($faqBlocs);
        }

        // Image
        foreach ($xeoBlocs['Image'] ?? [] as $bloc) {
            $graph[] = $this->buildImage($bloc);
        }

        // Video
        foreach ($xeoBlocs['Video'] ?? [] as $bloc) {
            $graph[] = $this->buildVideo($bloc);
        }

        if (empty($graph)) {
            return [];
        }

        return [[
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ]];
    }


    private static ?array $globalXeosCache = null;
    /**
     * Load GlobalXeo elastic values for a kind, with fallback to hostId=1.
     */
    private function loadGlobalXeo(JsonLdKind $kind, int $hostId): ?array
    {
        if (self::$globalXeosCache === null) {
            self::$globalXeosCache = [];
            foreach (GlobalXeo::query()->each() as $globalXeo) {
                self::$globalXeosCache[$globalXeo->getKind()][$globalXeo->getHostId()] = $globalXeo;
            }
        }
        $globalXeo = self::$globalXeosCache[$kind->value][$hostId] ?? self::$globalXeosCache[$kind->value][1] ?? null;
        return $globalXeo?->getElasticValues();
    }

    /**
     * Load XeoBlocs grouped by schema name.
     *
     * @return array<string, array<int, array>> schema name => [elastic values, ...]
     */
    private function loadXeoBlocs(XeoModel $xeoModel): array
    {
        $grouped = [];
        foreach ($xeoModel->getXeoBlocsQuery()->orderBy(['order' => SORT_ASC])->each() as $xeoBlocPivot) {
            $bloc = $xeoBlocPivot->getBlocQuery()->one();
            if ($bloc === null || !$bloc->isActive()) {
                continue;
            }
            $schema = $bloc->getElasticSchemaQuery()->one();
            if ($schema === null) {
                continue;
            }
            $grouped[$schema->getName()][] = $bloc->getElasticValues();
        }
        return $grouped;
    }

    private function cleanText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[`*_~#\[\]>]+/u', '', $text);
        $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function resolveImage(?string $path, ?int $width = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $file = CacheFile::from($path);
        if ($width !== null) {
            $file = $file->scale($width);
        }

        $url = (string) $file;
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $this->baseUrl . $url;
        }

        return $url;
    }

    private function buildOrganization(array $data): array
    {
        $org = [
            '@type' => $data['organizationType'] ?? 'Organization',
        ];

        $logo = $this->resolveImage($data['logo'] ?? null);
        if ($logo !== null) {
            $org['logo'] = $logo;
        }
        if (!empty($data['email'])) {
            $org['email'] = $data['email'];
        }
        if (!empty($data['telephone'])) {
            $org['telephone'] = $data['telephone'];
        }

        // Address
        $address = array_filter([
            'streetAddress' => $data['streetAddress'] ?? null,
            'postalCode' => $data['postalCode'] ?? null,
            'addressLocality' => $data['addressLocality'] ?? null,
            'addressRegion' => $data['addressRegion'] ?? null,
            'addressCountry' => $data['addressCountry'] ?? null,
        ]);
        if (!empty($address)) {
            $org['address'] = ['@type' => 'PostalAddress'] + $address;
        }

        if (!empty($data['vatID'])) {
            $org['vatID'] = $data['vatID'];
        }
        if (!empty($data['iso6523Code'])) {
            $org['iso6523Code'] = $data['iso6523Code'];
        }

        // sameAs — one URL per line
        if (!empty($data['sameAs'])) {
            $urls = array_filter(array_map('trim', explode("\n", $data['sameAs'])));
            if (!empty($urls)) {
                $org['sameAs'] = $urls;
            }
        }

        // ContactPoint
        if (!empty($data['contactType'])) {
            $contact = ['@type' => 'ContactPoint', 'contactType' => $data['contactType']];
            if (!empty($data['telephone'])) {
                $contact['telephone'] = $data['telephone'];
            }
            if (!empty($data['email'])) {
                $contact['email'] = $data['email'];
            }
            $org['contactPoint'] = $contact;
        }

        if (!empty($data['foundingDate'])) {
            $org['foundingDate'] = $data['foundingDate'];
        }
        if (!empty($data['numberOfEmployees'])) {
            $org['numberOfEmployees'] = ['@type' => 'QuantitativeValue', 'value' => $data['numberOfEmployees']];
        }

        return $org;
    }

    private function buildWebSite(array $data, ?Host $hostModel, ?string $language): array
    {
        $ws = [
            '@type' => 'WebSite',
        ];

        if ($hostModel !== null) {
            if ($hostModel->getSiteName() !== null) {
                $ws['name'] = $hostModel->getSiteName();
            }
            if ($hostModel->getSiteAlternateName() !== null) {
                $ws['alternateName'] = $hostModel->getSiteAlternateName();
            }
            if ($hostModel->getSiteDescription() !== null) {
                $ws['description'] = $hostModel->getSiteDescription();
            }
        }

        $inLanguage = $data['inLanguage'] ?? $language;
        if (!empty($inLanguage)) {
            $ws['inLanguage'] = $inLanguage;
        }

        // SearchAction
        if (!empty($data['hasSearchAction']) && !empty($data['searchTarget'])) {
            $ws['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $data['searchTarget'],
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $ws;
    }

    /**
     * @param Author[] $authors
     */
    private function buildType(?array $heroData, XeoModel $xeoModel, ?array $webSiteData, ?string $language, array $authors = []): array
    {
        $type = [
            '@type' => $xeoModel->getJsonldType(),
        ];

        // Xeo model = base, Hero bloc = enrichment
        $name = $heroData['title'] ?? $heroData['name'] ?? $xeoModel->getTitle();
        if (!empty($name)) {
            $type['name'] = $this->cleanText($name);
        }

        $headline = $heroData['overline'] ?? $name;
        if (!empty($headline)) {
            $type['headline'] = $this->cleanText($headline);
        }

        $description = $heroData['description'] ?? $xeoModel->getDescription();
        if (!empty($description)) {
            $type['description'] = $this->cleanText($description);
        }

        $image = $this->resolveImage($heroData['image'] ?? $xeoModel->getImage(), 1200);
        if ($image !== null) {
            $type['image'] = $image;
        }

        // Language from Content, fallback WebSite
        $inLanguage = $language ?? ($webSiteData['inLanguage'] ?? null);
        if (!empty($inLanguage)) {
            $type['inLanguage'] = $inLanguage;
        }

        // Authors
        $authorNodes = array_filter(array_map(fn(Author $author) => $this->buildAuthor($author), $authors));
        if (count($authorNodes) === 1) {
            $type['author'] = $authorNodes[0];
        } elseif (count($authorNodes) > 1) {
            $type['author'] = $authorNodes;
        }

        // Xeo enrichments
        if ($xeoModel->isSpeakable()) {
            $type['speakable'] = [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['article', 'h1', 'h2'],
            ];
        }

        if ($xeoModel->getKeywords() !== null) {
            $keywords = array_values(array_filter(array_map('trim', explode("\n", $xeoModel->getKeywords()))));
            if (!empty($keywords)) {
                $type['keywords'] = $keywords;
            }
        }

        $type['isAccessibleForFree'] = $xeoModel->isAccessibleForFree();

        return $type;
    }

    private function buildAuthor(Author $author): ?array
    {
        $name = trim($author->getFirstname() . ' ' . $author->getLastname());
        if ($name === '') {
            return null;
        }

        $person = [
            '@type' => 'Person',
            'name' => $name,
        ];

        if (!empty($author->getJobTitle())) {
            $person['jobTitle'] = $author->getJobTitle();
        }
        if (!empty($author->getWorksFor())) {
            $person['worksFor'] = ['@type' => 'Organization', 'name' => $author->getWorksFor()];
        }
        if (!empty($author->getKnowsAbout())) {
            $items = array_filter(array_map('trim', explode("\n", $author->getKnowsAbout())));
            if (!empty($items)) {
                $person['knowsAbout'] = $items;
            }
        }
        if (!empty($author->getUrl())) {
            $person['url'] = $author->getUrl();
        }

        $image = $this->resolveImage($author->getImage(), 96);
        if ($image !== null) {
            $person['image'] = $image;
        }

        // sameAs — one URL per line
        if ($author->getSameAs() !== null) {
            $urls = array_filter(array_map('trim', explode("\n", $author->getSameAs())));
            if (!empty($urls)) {
                $person['sameAs'] = $urls;
            }
        }

        return $person;
    }

    private function buildFaq(array $faqBlocs): array
    {
        $questions = [];
        foreach ($faqBlocs as $faq) {
            if (empty($faq['name'])) {
                continue;
            }
            $question = [
                '@type' => 'Question',
                'name' => $this->cleanText($faq['name']),
            ];
            if (!empty($faq['acceptedAnswer'])) {
                $question['acceptedAnswer'] = [
                    '@type' => 'Answer',
                    'text' => $this->cleanText($faq['acceptedAnswer']),
                ];
            }
            $questions[] = $question;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $questions,
        ];
    }

    private function buildImage(array $data): array
    {
        $img = [
            '@type' => 'ImageObject',
        ];

        if (!empty($data['name'])) {
            $img['name'] = $this->cleanText($data['name']);
        }
        if (!empty($data['caption'])) {
            $img['caption'] = $this->cleanText($data['caption']);
        }

        $contentUrl = $this->resolveImage($data['contentUrl'] ?? null);
        if ($contentUrl !== null) {
            $img['contentUrl'] = $contentUrl;
        }

        return $img;
    }

    private function buildVideo(array $data): array
    {
        $vid = [
            '@type' => 'VideoObject',
        ];

        if (!empty($data['name'])) {
            $vid['name'] = $this->cleanText($data['name']);
        }
        if (!empty($data['description'])) {
            $vid['description'] = $this->cleanText($data['description']);
        }
        if (!empty($data['contentUrl'])) {
            $vid['contentUrl'] = $data['contentUrl'];
        }

        return $vid;
    }
}