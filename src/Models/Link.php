<?php

declare(strict_types=1);

/**
 * Link.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Psr\Link\EvolvableLinkInterface;

final class Link implements EvolvableLinkInterface
{

    private array $rel = [];

    private array $attributes = [];

    public function __construct(
        string $rel = '',
        private string $href = ''
    )
    {
        if (!empty($rel)) {
            $this->rel[$rel] = true;
        }
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function isTemplated(): bool
    {
        return $this->hrefIsTemplated($this->href);
    }

    public function getRels(): array
    {
        return array_keys($this->rel);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withHref(\Stringable|string $href): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        $clone->href = $href;

        return $clone;
    }

    private function hrefIsTemplated(string $href): bool
    {
        return str_contains($href, '{') || str_contains($href, '}');
    }

    public function withTemplate(string $template, string $value): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        $href = $clone->getHref();
        if ($this->hrefIsTemplated($href)) {
            $clone->href = str_replace('{' . $template . '}', $value, $href);
        }
        return $clone;
    }

    public function withRel(string $rel): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        $clone->rel[$rel] = true;
        return $clone;
    }

    public function withoutRel(string $rel): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        unset($clone->rel[$rel]);
        return $clone;
    }

    public function withAttribute(string $attribute, string|\Stringable|int|float|bool|array $value): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        $clone->attributes[$attribute] = $value;
        return $clone;
    }

    public function withoutAttribute(string $attribute): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        unset($clone->attributes[$attribute]);
        return $clone;
    }
}