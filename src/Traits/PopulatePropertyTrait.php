<?php

declare(strict_types=1);

/**
 * PopulatePropertyTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

/**
 * Minimal trait to override populateProperty for AR with protected properties.
 * Does NOT provide magic __get/__set - all property access via explicit getters/setters.
 */
trait PopulatePropertyTrait
{
    protected function populateProperty(string $name, mixed $value): void
    {
        $setter = 'set' . ucfirst($name);
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } elseif(property_exists($this, $name)) {
            $this->$name = $value;
        }
    }
}
