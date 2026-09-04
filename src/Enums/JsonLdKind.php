<?php

declare(strict_types=1);

/**
 * XeoKind.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Enums;

enum JsonLdKind: string
{
    case Organization = 'Organization';
    case Robots = 'Robots';
    case WebSite = 'WebSite';
    // case BreadcrumbList = 'BreadcrumbList';
    // case HowTo = 'HowTo';
    // case Article = 'Article';
    // case BlogPosting = 'BlogPosting';
    // case NewsArticle = 'NewsArticle';
    // case Product = 'Product';
    // case Event = 'Event';
    // case Recipe = 'Recipe';
    // case Review = 'Review';
    // case AggregateRating = 'AggregateRating';
}
