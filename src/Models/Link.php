<?php

declare(strict_types=1);

/**
 * Link.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Psr\Link\EvolvableLinkInterface;

class Link implements EvolvableLinkInterface
{

    public const BASE_HREF = '//{host}';

    private array $rel = [];

    private array $attributes = [];

    public function __construct(
        string $rel = '',
        private string $href = ''
    )
    {
        if (empty($rel) === false) {
            $this->rel[$rel] = true;
        }
    }

    public function getHref(): string
    {
        return str_replace(self::BASE_HREF, '', $this->href);
    }

    public function isTemplated(): bool
    {
        return str_contains($this->href, '{') === true || str_contains($this->href, '}') === true;
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

    private function applyTemplate(string $template, string $value): void
    {
        $token = '{'.$template.'}';
        if (str_contains($this->href, $token) === true) {
            $this->href = str_replace($token, $value, $this->href);
        }
    }

    public function withTemplate(string $template, string $value): static
    {
        /** @var EvolvableLinkInterface $clone */
        $clone = clone($this);
        $clone->applyTemplate($template, $value);
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