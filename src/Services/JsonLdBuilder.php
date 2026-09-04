<?php

declare(strict_types=1);

/**
 * JsonLdBuilder.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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
use Blackcube\Dcore\Helpers\ContentHelper;
use Blackcube\Dcore\Models\Author;
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Models\Xeo as XeoModel;
use Blackcube\Dcore\Models\XeoBloc;
use Blackcube\FileProvider\CacheFile;

class JsonLdBuilder implements JsonLdBuilderInterface
{
    private string $baseUrl = '';

    public function build(int $slugId, string $host): array
    {
        $slug = Slug::query()->andWhere(['id' => $slugId])->one();
        if ($slug === null) {
            return [];
        }

        $xeoModel = $slug->getXeoQuery()->one();
        if ($xeoModel === null || $xeoModel->isActive() === false) {
            return [];
        }

        $hostId = $slug->getHostId();
        if ($hostId > 1) {
            $hostModel = Host::query()->andWhere(['id' => $hostId])->one();
            $this->baseUrl = 'https://'.($hostModel?->getName() ?? $host);
        } else {
            $hostModel = null;
            $this->baseUrl = 'https://'.$host;
        }
        $element = $slug->getElement();
        $language = ($element instanceof Content) ? $element->getLanguageId() : null;

        $orgData = $this->loadGlobalXeo(JsonLdKind::Organization, $hostId);
        $webSiteData = $this->loadGlobalXeo(JsonLdKind::WebSite, $hostId);

        $xeoBlocs = $this->loadXeoBlocs($xeoModel);

        $graph = [];

        if ($orgData !== null) {
            $graph[] = $this->buildOrganization($orgData);
        }

        if ($webSiteData !== null) {
            $graph[] = $this->buildWebSite($webSiteData, $hostModel, $language);
        }

        $authors = [];
        if ($element !== null) {
            /** @var Author $author */
            foreach ($element->getAuthorsQuery()->each() as $author) {
                $authors[] = $author;
            }
        }

        $heroData = ($xeoBlocs['Hero'] ?? [])[0] ?? null;
        $graph[] = $this->buildType($heroData, $xeoModel, $webSiteData, $language, $authors);

        $faqBlocs = $xeoBlocs['FAQ'] ?? [];
        if (empty($faqBlocs) === false) {
            $graph[] = $this->buildFaq($faqBlocs);
        }

        foreach ($xeoBlocs['Image'] ?? [] as $bloc) {
            $graph[] = $this->buildImage($bloc);
        }

        foreach ($xeoBlocs['Video'] ?? [] as $bloc) {
            $graph[] = $this->buildVideo($bloc);
        }

        if (empty($graph) === true) {
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
            /** @var GlobalXeo $globalXeo */
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
        /** @var XeoBloc $xeoBlocPivot */
        foreach ($xeoModel->getXeoBlocsQuery()->orderBy(['order' => SORT_ASC])->each() as $xeoBlocPivot) {
            $bloc = $xeoBlocPivot->getBlocQuery()->one();
            if ($bloc === null || $bloc->isActive() === false) {
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

    private function imageUrl(?string $path, ?int $width = null): ?string
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

        if (str_starts_with($url, '/') === true) {
            return $this->baseUrl.$url;
        }

        return $url;
    }

    private function buildOrganization(array $data): array
    {
        $org = [
            '@type' => $data['organizationType'] ?? 'Organization',
        ];

        $logo = $this->imageUrl($data['logo'] ?? null);
        if ($logo !== null) {
            $org['logo'] = $logo;
        }
        if (empty($data['email']) === false) {
            $org['email'] = $data['email'];
        }
        if (empty($data['telephone']) === false) {
            $org['telephone'] = $data['telephone'];
        }

        $address = array_filter([
            'streetAddress' => $data['streetAddress'] ?? null,
            'postalCode' => $data['postalCode'] ?? null,
            'addressLocality' => $data['addressLocality'] ?? null,
            'addressRegion' => $data['addressRegion'] ?? null,
            'addressCountry' => $data['addressCountry'] ?? null,
        ]);
        if (empty($address) === false) {
            $org['address'] = ['@type' => 'PostalAddress'] + $address;
        }

        if (empty($data['vatID']) === false) {
            $org['vatID'] = $data['vatID'];
        }
        if (empty($data['iso6523Code']) === false) {
            $org['iso6523Code'] = $data['iso6523Code'];
        }

        if (empty($data['sameAs']) === false) {
            $sameAsLines = explode("\n", $data['sameAs']);
            $urls = array_filter(array_map('trim', $sameAsLines));
            if (empty($urls) === false) {
                $org['sameAs'] = $urls;
            }
        }

        if (empty($data['contactType']) === false) {
            $contact = ['@type' => 'ContactPoint', 'contactType' => $data['contactType']];
            if (empty($data['telephone']) === false) {
                $contact['telephone'] = $data['telephone'];
            }
            if (empty($data['email']) === false) {
                $contact['email'] = $data['email'];
            }
            $org['contactPoint'] = $contact;
        }

        if (empty($data['foundingDate']) === false) {
            $org['foundingDate'] = $data['foundingDate'];
        }
        if (empty($data['numberOfEmployees']) === false) {
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
        if (empty($inLanguage) === false) {
            $ws['inLanguage'] = $inLanguage;
        }

        if (empty($data['hasSearchAction']) === false && empty($data['searchTarget']) === false) {
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

        $name = $heroData['title'] ?? $heroData['name'] ?? $xeoModel->getTitle();
        if (empty($name) === false) {
            $type['name'] = ContentHelper::cleanTitle($name);
        }

        $headline = $heroData['overline'] ?? $name;
        if (empty($headline) === false) {
            $type['headline'] = ContentHelper::cleanTitle($headline);
        }

        $description = $heroData['description'] ?? $xeoModel->getDescription();
        if (empty($description) === false) {
            $type['description'] = ContentHelper::cleanTitle($description);
        }

        $image = $this->imageUrl($heroData['image'] ?? $xeoModel->getImage(), 1200);
        if ($image !== null) {
            $type['image'] = $image;
        }

        $inLanguage = $language ?? ($webSiteData['inLanguage'] ?? null);
        if (empty($inLanguage) === false) {
            $type['inLanguage'] = $inLanguage;
        }

        $authorNodes = array_filter(array_map(fn(Author $author) => $this->buildAuthor($author), $authors));
        if (count($authorNodes) === 1) {
            $type['author'] = $authorNodes[0];
        } elseif (count($authorNodes) > 1) {
            $type['author'] = $authorNodes;
        }

        if ($xeoModel->isSpeakable() === true) {
            $type['speakable'] = [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['article', 'h1', 'h2'],
            ];
        }

        if ($xeoModel->getKeywords() !== null) {
            $keywordLines = explode("\n", $xeoModel->getKeywords());
            $keywords = array_values(array_filter(array_map('trim', $keywordLines)));
            if (empty($keywords) === false) {
                $type['keywords'] = $keywords;
            }
        }

        $type['isAccessibleForFree'] = $xeoModel->isAccessibleForFree();

        return $type;
    }

    private function buildAuthor(Author $author): ?array
    {
        $name = trim($author->getFirstname().' '.$author->getLastname());
        if ($name === '') {
            return null;
        }

        $person = [
            '@type' => 'Person',
            'name' => $name,
        ];

        if (empty($author->getJobTitle()) === false) {
            $person['jobTitle'] = $author->getJobTitle();
        }
        if (empty($author->getWorksFor()) === false) {
            $person['worksFor'] = ['@type' => 'Organization', 'name' => $author->getWorksFor()];
        }
        if (empty($author->getKnowsAbout()) === false) {
            $knowsAboutLines = explode("\n", $author->getKnowsAbout());
            $items = array_filter(array_map('trim', $knowsAboutLines));
            if (empty($items) === false) {
                $person['knowsAbout'] = $items;
            }
        }
        if (empty($author->getUrl()) === false) {
            $person['url'] = $author->getUrl();
        }

        $image = $this->imageUrl($author->getImage(), 96);
        if ($image !== null) {
            $person['image'] = $image;
        }

        if ($author->getSameAs() !== null) {
            $sameAsLines = explode("\n", $author->getSameAs());
            $urls = array_filter(array_map('trim', $sameAsLines));
            if (empty($urls) === false) {
                $person['sameAs'] = $urls;
            }
        }

        return $person;
    }

    private function buildFaq(array $faqBlocs): array
    {
        $questions = [];
        foreach ($faqBlocs as $faq) {
            if (empty($faq['name']) === true) {
                continue;
            }
            $question = [
                '@type' => 'Question',
                'name' => ContentHelper::cleanTitle($faq['name']),
            ];
            if (empty($faq['acceptedAnswer']) === false) {
                $question['acceptedAnswer'] = [
                    '@type' => 'Answer',
                    'text' => ContentHelper::cleanTitle($faq['acceptedAnswer']),
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

        if (empty($data['name']) === false) {
            $img['name'] = ContentHelper::cleanTitle($data['name']);
        }
        if (empty($data['caption']) === false) {
            $img['caption'] = ContentHelper::cleanTitle($data['caption']);
        }

        $contentUrl = $this->imageUrl($data['contentUrl'] ?? null);
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

        if (empty($data['name']) === false) {
            $vid['name'] = ContentHelper::cleanTitle($data['name']);
        }
        if (empty($data['description']) === false) {
            $vid['description'] = ContentHelper::cleanTitle($data['description']);
        }
        if (empty($data['contentUrl']) === false) {
            $vid['contentUrl'] = $data['contentUrl'];
        }

        return $vid;
    }
}