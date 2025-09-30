<?php

declare(strict_types=1);

namespace Daycry\Relations\Contracts;

/**
 * Marker interface to indicate a Model or Entity supports Relations.
 * Implemented by any class using ModelTrait or EntityTrait.
 */
interface RelatableInterface
{
    // Intentionally empty; used only for type checks in BaseTrait.
}
