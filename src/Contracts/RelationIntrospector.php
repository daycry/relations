<?php

declare(strict_types=1);

namespace Daycry\Relations\Contracts;

/**
 * Optional interface (no runtime requirement) to aid static analysis for classes using ModelTrait/EntityTrait.
 */
interface RelationIntrospector extends RelatableInterface
{
    /** @return string[] */
    public function listRelations(): array;

    /**
     * @param string|string[]|null $names
     * @return array<string,array<string,mixed>>
     */
    public function relationDetails(string|array|null $names = null): array;
}
