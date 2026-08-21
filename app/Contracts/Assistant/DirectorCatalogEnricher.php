<?php

declare(strict_types=1);

namespace App\Contracts\Assistant;

/**
 * Extends each director catalog entry with extra recommendation signals.
 * New criteria = new enricher; assistant core stays unchanged.
 */
interface DirectorCatalogEnricher
{
    /**
     * @param  list<array<string, mixed>>  $directors
     * @return list<array<string, mixed>>
     */
    public function enrich(array $directors): array;
}
